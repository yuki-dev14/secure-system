<?php

namespace App\Http\Controllers;

use App\Models\Beneficiary;
use App\Models\CashGrantDistribution;
use App\Models\ComplianceSummaryCache;
use App\Models\SubmittedRequirement;
use App\Models\VerificationActivityLog;
use App\Services\ComplianceCheckingService;
use App\Services\DigitalSignatureService;
use App\Services\DuplicateDetectionService;
use App\Services\PayoutCalculationService;
use App\Services\ReceiptGenerationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class DistributionController extends Controller
{
    public function __construct(
        private PayoutCalculationService  $payoutCalc,
        private DigitalSignatureService   $signatureSvc,
        private ReceiptGenerationService  $receiptSvc,
        private ComplianceCheckingService $complianceSvc,
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // approveForDistribution — POST /distribution/approve/{beneficiaryId}
    // Authorization: Compliance Verifier | Administrator
    // ─────────────────────────────────────────────────────────────────────────

    public function approveForDistribution(Request $request, $beneficiaryId)
    {
        $actor = Auth::user();
        abort_if(
            ! $actor->hasRole(['Compliance Verifier', 'Administrator']),
            403,
            'Only Compliance Verifiers and Administrators may approve distributions.'
        );

        // Validate input
        $request->validate([
            'payout_month' => ['required', 'integer', 'min:1', 'max:12'],
            'payout_year'  => ['required', 'integer', 'min:2020'],
        ]);

        // 1. Beneficiary must exist and be active
        $beneficiary = Beneficiary::with([
            'familyMembers',
            'complianceSummary',
            'submittedRequirements',
        ])->find($beneficiaryId);

        if (! $beneficiary) {
            return response()->json(['success' => false, 'message' => 'Beneficiary not found.'], 404);
        }

        if (! $beneficiary->is_active) {
            return response()->json(['success' => false, 'message' => 'Beneficiary is not active.'], 422);
        }

        $month = (int) $request->payout_month;
        $year  = (int) $request->payout_year;

        // 2. Not already distributed for this period
        $alreadyDistributed = CashGrantDistribution::forBeneficiary($beneficiaryId)
            ->forPeriod($month, $year)
            ->exists();

        if ($alreadyDistributed) {
            return response()->json([
                'success'         => false,
                'already_distributed' => true,
                'message'         => "Beneficiary has already received a cash grant for this period ({$month}/{$year}).",
            ], 422);
        }

        // 3. Eligibility checks
        $issues        = [];
        $checksResults = [];

        // 3a. Overall compliance status from cache
        $summaryCache = $beneficiary->complianceSummary;
        $complianceStatus = $summaryCache?->overall_compliance_status ?? 'unknown';
        $isCompliantStatus = $complianceStatus === 'compliant';

        $checksResults[] = [
            'check'   => 'Overall Compliance Status',
            'passed'  => $isCompliantStatus,
            'detail'  => "Status: " . ucfirst(str_replace('_', ' ', $complianceStatus)),
        ];

        if (! $isCompliantStatus) {
            $issues[] = "Overall compliance status is '{$complianceStatus}', must be 'compliant'.";
        }

        // 3b. Education compliance >= 85%
        $educationPct   = (float) ($summaryCache?->education_compliance_percentage ?? 0);
        $eduPass        = $educationPct >= 85.0;
        $checksResults[] = [
            'check'  => 'Education Compliance (≥ 85%)',
            'passed' => $eduPass,
            'detail' => number_format($educationPct, 1) . '%',
        ];
        if (! $eduPass) {
            $issues[] = "Education compliance is {$educationPct}% (minimum 85% required).";
        }

        // 3c. Health compliance >= 80%
        $healthPct      = (float) ($summaryCache?->health_compliance_percentage ?? 0);
        $healthPass     = $healthPct >= 80.0;
        $checksResults[] = [
            'check'  => 'Health Compliance (≥ 80%)',
            'passed' => $healthPass,
            'detail' => number_format($healthPct, 1) . '%',
        ];
        if (! $healthPass) {
            $issues[] = "Health compliance is {$healthPct}% (minimum 80% required).";
        }

        // 3d. FDS compliance
        $fdsPct         = (float) ($summaryCache?->fds_compliance_percentage ?? 0);
        $fdsPass        = $fdsPct > 0;
        $checksResults[] = [
            'check'  => 'FDS Session Compliance',
            'passed' => $fdsPass,
            'detail' => number_format($fdsPct, 1) . '%',
        ];
        if (! $fdsPass) {
            $issues[] = 'No FDS sessions recorded for the current period.';
        }

        // 3e. All required documents approved
        $requirements = $beneficiary->submittedRequirements;
        $totalReqs    = $requirements->count();
        $approvedReqs = $requirements->where('status', 'approved')->count();
        $docsPass     = $totalReqs > 0 && $approvedReqs === $totalReqs;
        $checksResults[] = [
            'check'  => 'All Required Documents Approved',
            'passed' => $docsPass,
            'detail' => "{$approvedReqs}/{$totalReqs} documents approved",
        ];
        if (! $docsPass) {
            $pending = $totalReqs - $approvedReqs;
            $issues[] = "{$pending} document(s) are not yet approved.";
        }

        // 3f. No active duplicate/ghost flags
        $duplicateFlag = \App\Models\DuplicateDetectionLog::where(function ($q) use ($beneficiaryId) {
                $q->where('primary_beneficiary_id', $beneficiaryId)
                  ->orWhere('duplicate_beneficiary_id', $beneficiaryId);
            })
            ->where('resolution_status', 'pending')
            ->where('confidence_score', '>=', 0.7)
            ->exists();

        $checksResults[] = [
            'check'  => 'No Duplicate / Ghost Flags',
            'passed' => ! $duplicateFlag,
            'detail' => $duplicateFlag ? 'Active high-confidence duplicate flag detected' : 'No active flags',
        ];
        if ($duplicateFlag) {
            $issues[] = 'Beneficiary has an active duplicate/ghost detection flag that must be resolved first.';
        }

        // ── Determine eligibility ─────────────────────────────────────────────
        $eligible = empty($issues);

        if ($eligible) {
            // Calculate payout
            $calculation = $this->payoutCalc->calculatePayoutAmount((int) $beneficiaryId);

            // Log approval
            $this->logActivity(
                (int) $beneficiaryId,
                'approve',
                "Distribution pre-approved for {$beneficiary->family_head_name} (BIN: {$beneficiary->bin}). "
                . "Period: {$month}/{$year}. Calculated amount: ₱" . number_format($calculation['total_amount'], 2) . '.',
                'success',
                $request
            );

            return response()->json([
                'success'          => true,
                'eligible'         => true,
                'message'          => 'Beneficiary is eligible for cash grant distribution.',
                'beneficiary'      => [
                    'id'               => $beneficiary->id,
                    'bin'              => $beneficiary->bin,
                    'family_head_name' => $beneficiary->family_head_name,
                    'municipality'     => $beneficiary->municipality,
                ],
                'period'           => ['month' => $month, 'year' => $year],
                'checks'           => $checksResults,
                'calculation'      => $calculation,
            ]);
        }

        // Not eligible
        $this->logActivity(
            (int) $beneficiaryId,
            'approve',
            "Distribution approval denied for {$beneficiary->family_head_name} (BIN: {$beneficiary->bin}). "
            . 'Reasons: ' . implode('; ', $issues),
            'failed',
            $request
        );

        return response()->json([
            'success'           => false,
            'eligible'          => false,
            'message'           => 'Beneficiary is not eligible for cash grant distribution.',
            'issues'            => $issues,
            'checks'            => $checksResults,
        ], 422);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // recordDistribution — POST /distribution/record
    // Authorization: Compliance Verifier | Administrator
    // ─────────────────────────────────────────────────────────────────────────

    public function recordDistribution(Request $request)
    {
        $actor = Auth::user();
        abort_if(
            ! $actor->hasRole(['Compliance Verifier', 'Administrator']),
            403,
            'Only Compliance Verifiers and Administrators may record distributions.'
        );

        $validated = $request->validate([
            'beneficiary_id'                => ['required', 'integer', 'exists:beneficiaries,id'],
            'payout_amount'                 => ['required', 'numeric', 'min:0'],
            'payout_period'                 => ['required', 'string', 'max:100'],
            'payout_month'                  => ['required', 'integer', 'min:1', 'max:12'],
            'payout_year'                   => ['required', 'integer', 'min:2020'],
            'payment_method'                => ['required', Rule::in(['cash', 'e-wallet', 'bank_transfer'])],
            'transaction_reference_number'  => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('cash_grant_distributions', 'transaction_reference_number'),
            ],
            'remarks'                       => ['nullable', 'string', 'max:2000'],
            'signature_data_url'            => ['nullable', 'string'], // base64 PNG Data URL
        ]);

        $beneficiaryId = (int) $validated['beneficiary_id'];
        $month         = (int) $validated['payout_month'];
        $year          = (int) $validated['payout_year'];

        // 1. Beneficiary must be active
        $beneficiary = Beneficiary::findOrFail($beneficiaryId);
        if (! $beneficiary->is_active) {
            return response()->json(['success' => false, 'message' => 'Beneficiary is not active.'], 422);
        }

        // 2. Not already distributed this period
        $existing = CashGrantDistribution::forBeneficiary($beneficiaryId)
            ->forPeriod($month, $year)->first();

        if ($existing) {
            return response()->json([
                'success'      => false,
                'message'      => 'Distribution already recorded for this period.',
                'distribution' => $existing,
            ], 422);
        }

        // 3. Validate payout amount vs calculation (1% tolerance)
        $amountValidation = $this->payoutCalc->validateAmount($beneficiaryId, (float) $validated['payout_amount']);
        if (! $amountValidation['is_valid'] && ! $actor->isAdministrator()) {
            return response()->json([
                'success'            => false,
                'message'            => 'Payout amount deviates from calculated amount by more than 1%.',
                'amount_validation'  => $amountValidation,
            ], 422);
        }

        // 4. Generate transaction reference number if not provided
        $transactionRef = $validated['transaction_reference_number']
            ?? 'TXN-' . strtoupper(Str::random(6)) . '-' . now()->format('YmdHis');

        // 5. Save signature if provided
        $signaturePath = null;
        if (! empty($validated['signature_data_url'])) {
            try {
                $signaturePath = $this->signatureSvc->saveSignature(
                    $validated['signature_data_url'],
                    $beneficiary->bin
                );
            } catch (\Throwable $e) {
                Log::warning("DistributionController: Signature save failed: " . $e->getMessage());
            }
        }

        // 6. Create distribution record inside a transaction
        $distribution = DB::transaction(function () use (
            $validated, $beneficiaryId, $month, $year,
            $transactionRef, $signaturePath, $actor
        ) {
            return CashGrantDistribution::create([
                'beneficiary_id'               => $beneficiaryId,
                'payout_amount'                => $validated['payout_amount'],
                'payout_period'                => $validated['payout_period'],
                'payout_month'                 => $month,
                'payout_year'                  => $year,
                'distributed_at'               => now(),
                'distributed_by_user_id'       => $actor->id,
                'approved_by_user_id'          => $actor->id,
                'payment_method'               => $validated['payment_method'],
                'transaction_reference_number' => $transactionRef,
                'received_signature_path'      => $signaturePath,
                'remarks'                      => $validated['remarks'] ?? null,
            ]);
        });

        // 7. Log activity
        $this->logActivity(
            $beneficiaryId,
            'distribution',
            "Cash grant distributed to {$beneficiary->family_head_name} (BIN: {$beneficiary->bin}). "
            . "Amount: ₱" . number_format($distribution->payout_amount, 2) . ". "
            . "Period: {$validated['payout_period']}. Ref: {$transactionRef}. "
            . "Method: {$validated['payment_method']}.",
            'success',
            $request
        );

        // 8. Generate receipt (non-blocking — failure doesn't abort)
        $receiptInfo = null;
        try {
            $receiptInfo = $this->receiptSvc->generateReceipt($distribution->id);
        } catch (\Throwable $e) {
            Log::error("DistributionController: Receipt generation failed for distribution #{$distribution->id}: " . $e->getMessage());
        }

        return response()->json([
            'success'      => true,
            'message'      => 'Cash grant distribution recorded successfully.',
            'distribution' => $this->formatDistribution($distribution->load('beneficiary', 'distributedBy', 'approvedBy')),
            'receipt_url'  => $receiptInfo['url'] ?? null,
            'receipt_number' => $receiptInfo['receipt_number'] ?? null,
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // index — GET /distribution
    // Authorization: All authenticated users
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $query = CashGrantDistribution::with([
            'beneficiary:id,bin,family_head_name,barangay,municipality,province',
            'distributedBy:id,name,role,office_location',
        ])->orderByDesc('distributed_at');

        // Filters
        if ($request->filled('from')) {
            $query->whereDate('distributed_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('distributed_at', '<=', $request->to);
        }
        if ($request->filled('search')) {
            $term = $request->search;
            $query->whereHas('beneficiary', fn ($q) =>
                $q->where('family_head_name', 'ilike', "%{$term}%")
                  ->orWhere('bin', 'ilike', "%{$term}%")
            );
        }
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }
        if ($request->filled('office')) {
            $query->whereHas('distributedBy', fn ($q) =>
                $q->where('office_location', 'ilike', "%{$request->office}%")
            );
        }
        if ($request->filled('distributed_by')) {
            $query->where('distributed_by_user_id', $request->distributed_by);
        }
        if ($request->filled('month')) {
            $query->where('payout_month', $request->month);
        }
        if ($request->filled('year')) {
            $query->where('payout_year', $request->year);
        }

        $paginated   = $query->paginate(20)->withQueryString();
        $totalAmount = (clone $query)->sum('payout_amount');

        return response()->json([
            'success'      => true,
            'data'         => $paginated->getCollection()->map(fn ($d) => $this->formatDistribution($d)),
            'pagination'   => [
                'total'        => $paginated->total(),
                'per_page'     => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'from'         => $paginated->firstItem(),
                'to'           => $paginated->lastItem(),
            ],
            'total_amount' => (float) $totalAmount,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // show — GET /distribution/{id}
    // Authorization: All authenticated users
    // ─────────────────────────────────────────────────────────────────────────

    public function show($distributionId)
    {
        $distribution = CashGrantDistribution::with([
            'beneficiary',
            'distributedBy:id,name,role,office_location',
            'approvedBy:id,name,role',
        ])->findOrFail($distributionId);

        return response()->json([
            'success'      => true,
            'distribution' => $this->formatDistribution($distribution, detailed: true),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // history — GET /distribution/history/{beneficiaryId}
    // Authorization: All authenticated users
    // ─────────────────────────────────────────────────────────────────────────

    public function history(Request $request, $beneficiaryId)
    {
        $beneficiary = Beneficiary::findOrFail($beneficiaryId);

        $distributions = CashGrantDistribution::with([
            'distributedBy:id,name,role',
            'approvedBy:id,name,role',
        ])
            ->where('beneficiary_id', $beneficiaryId)
            ->orderByDesc('distributed_at')
            ->get();

        $totalLifetime    = $distributions->sum('payout_amount');
        $distributionCount = $distributions->count();

        // Frequency analysis
        $byYear = $distributions->groupBy('payout_year')->map->count();

        return response()->json([
            'success'             => true,
            'beneficiary'         => [
                'id'               => $beneficiary->id,
                'bin'              => $beneficiary->bin,
                'family_head_name' => $beneficiary->family_head_name,
            ],
            'distributions'       => $distributions->map(fn ($d) => $this->formatDistribution($d)),
            'total_lifetime'      => (float) $totalLifetime,
            'distribution_count'  => $distributionCount,
            'frequency_by_year'   => $byYear,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // reconcile — POST /distribution/reconcile
    // Authorization: Administrator
    // ─────────────────────────────────────────────────────────────────────────

    public function reconcile(Request $request)
    {
        abort_if(! Auth::user()->isAdministrator(), 403, 'Administrator access required.');

        $validated = $request->validate([
            'from'   => ['required', 'date'],
            'to'     => ['required', 'date', 'after_or_equal:from'],
            'office' => ['nullable', 'string', 'max:255'],
        ]);

        $query = CashGrantDistribution::with(['distributedBy:id,name,office_location'])
            ->whereBetween('distributed_at', [
                Carbon::parse($validated['from'])->startOfDay(),
                Carbon::parse($validated['to'])->endOfDay(),
            ]);

        if (! empty($validated['office'])) {
            $query->whereHas('distributedBy', fn ($q) =>
                $q->where('office_location', 'ilike', "%{$validated['office']}%")
            );
        }

        $distributions = $query->get();

        $actualTotal   = $distributions->sum('payout_amount');
        $count         = $distributions->count();

        // Recalculate expected amounts per beneficiary
        $expectedTotal = 0.0;
        foreach ($distributions as $d) {
            try {
                $calc           = $this->payoutCalc->calculatePayoutAmount($d->beneficiary_id);
                $expectedTotal  += $calc['total_amount'];
            } catch (\Throwable) {
                $expectedTotal += (float) $d->payout_amount; // fallback
            }
        }

        $discrepancy    = $actualTotal - $expectedTotal;
        $discrepancyPct = $expectedTotal > 0 ? abs($discrepancy / $expectedTotal) * 100 : 0;
        $flagged        = $discrepancyPct > 0.01;

        // Breakdown by payment method
        $byMethod = $distributions->groupBy('payment_method')->map(fn ($group) => [
            'count'  => $group->count(),
            'amount' => (float) $group->sum('payout_amount'),
        ]);

        // Breakdown by office
        $byOffice = $distributions->groupBy(fn ($d) => $d->distributedBy?->office_location ?? 'Unknown')
            ->map(fn ($group) => [
                'count'  => $group->count(),
                'amount' => (float) $group->sum('payout_amount'),
            ]);

        return response()->json([
            'success'        => true,
            'period'         => ['from' => $validated['from'], 'to' => $validated['to']],
            'count'          => $count,
            'expected_total' => round($expectedTotal, 2),
            'actual_total'   => round($actualTotal, 2),
            'discrepancy'    => round($discrepancy, 2),
            'discrepancy_pct'=> round($discrepancyPct, 4),
            'flagged'        => $flagged,
            'by_method'      => $byMethod,
            'by_office'      => $byOffice,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // bulkDistribute — POST /distribution/bulk
    // Authorization: Administrator
    // ─────────────────────────────────────────────────────────────────────────

    public function bulkDistribute(Request $request)
    {
        abort_if(! Auth::user()->isAdministrator(), 403, 'Administrator access required.');

        $request->validate([
            'records'                => ['required', 'array', 'min:1', 'max:200'],
            'records.*.beneficiary_id'=> ['required', 'integer'],
            'records.*.payout_amount' => ['required', 'numeric', 'min:0'],
            'records.*.payout_period' => ['required', 'string', 'max:100'],
            'records.*.payout_month'  => ['required', 'integer', 'min:1', 'max:12'],
            'records.*.payout_year'   => ['required', 'integer', 'min:2020'],
            'records.*.payment_method'=> ['required', Rule::in(['cash', 'e-wallet', 'bank_transfer'])],
        ]);

        $actor   = Auth::user();
        $records = $request->records;

        // Check for duplicates within the batch
        $seenKeys = [];
        foreach ($records as $idx => $r) {
            $key = "{$r['beneficiary_id']}-{$r['payout_month']}-{$r['payout_year']}";
            if (in_array($key, $seenKeys)) {
                return response()->json([
                    'success' => false,
                    'message' => "Duplicate entry in batch at index {$idx} for beneficiary #{$r['beneficiary_id']} in the same period.",
                ], 422);
            }
            $seenKeys[] = $key;
        }

        $successes   = [];
        $failures    = [];
        $createdBatch = [];

        DB::beginTransaction();
        try {
            foreach ($records as $idx => $r) {
                $beneficiaryId = (int) $r['beneficiary_id'];
                $month         = (int) $r['payout_month'];
                $year          = (int) $r['payout_year'];

                // a. Beneficiary exists and is active
                $beneficiary = Beneficiary::find($beneficiaryId);
                if (! $beneficiary || ! $beneficiary->is_active) {
                    $failures[] = ['index' => $idx, 'beneficiary_id' => $beneficiaryId, 'reason' => 'Beneficiary not found or inactive.'];
                    continue;
                }

                // b. Not already distributed
                if (CashGrantDistribution::forBeneficiary($beneficiaryId)->forPeriod($month, $year)->exists()) {
                    $failures[] = ['index' => $idx, 'beneficiary_id' => $beneficiaryId, 'reason' => 'Already distributed for this period.'];
                    continue;
                }

                // c. Generate reference
                $transactionRef = 'BULK-' . strtoupper(Str::random(6)) . '-' . $beneficiaryId . '-' . now()->format('YmdHis');

                $distribution = CashGrantDistribution::create([
                    'beneficiary_id'               => $beneficiaryId,
                    'payout_amount'                => $r['payout_amount'],
                    'payout_period'                => $r['payout_period'],
                    'payout_month'                 => $month,
                    'payout_year'                  => $year,
                    'distributed_at'               => now(),
                    'distributed_by_user_id'       => $actor->id,
                    'approved_by_user_id'          => $actor->id,
                    'payment_method'               => $r['payment_method'],
                    'transaction_reference_number' => $transactionRef,
                    'remarks'                      => $r['remarks'] ?? 'Bulk distribution',
                ]);

                $createdBatch[] = $distribution->id;
                $successes[]    = [
                    'index'            => $idx,
                    'beneficiary_id'   => $beneficiaryId,
                    'distribution_id'  => $distribution->id,
                    'transaction_ref'  => $transactionRef,
                    'amount'           => $r['payout_amount'],
                ];
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("DistributionController@bulkDistribute: Critical error — " . $e->getMessage());

            return response()->json([
                'success'  => false,
                'message'  => 'Bulk distribution failed due to a critical error. All records rolled back.',
                'error'    => $e->getMessage(),
            ], 500);
        }

        // Log batch
        $this->logActivity(
            null,
            'bulk_distribution',
            "Bulk distribution processed by {$actor->name}. "
            . "Success: " . count($successes) . ". Failures: " . count($failures) . ".",
            count($failures) === 0 ? 'success' : 'partial',
            $request
        );

        return response()->json([
            'success'       => true,
            'message'       => count($successes) . ' distributions recorded, ' . count($failures) . ' failed.',
            'success_count' => count($successes),
            'failure_count' => count($failures),
            'successes'     => $successes,
            'failures'      => $failures,
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // page — Inertia page GET /distribution/page
    // ─────────────────────────────────────────────────────────────────────────

    public function page()
    {
        return Inertia::render('Distribution/Index', [
            'canRecord'     => Auth::user()->hasRole(['Compliance Verifier', 'Administrator']),
            'canReconcile'  => Auth::user()->isAdministrator(),
            'canBulk'       => Auth::user()->isAdministrator(),
            'isAdmin'       => Auth::user()->isAdministrator(),
            'currentUser'   => [
                'id'              => Auth::id(),
                'name'            => Auth::user()->name,
                'role'            => Auth::user()->role,
                'office_location' => Auth::user()->office_location,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // approvalPage — Inertia page GET /distribution/approve-page/{beneficiaryId}
    // ─────────────────────────────────────────────────────────────────────────

    public function approvalPage($beneficiaryId)
    {
        $beneficiary = Beneficiary::with(['familyMembers', 'complianceSummary'])->findOrFail($beneficiaryId);

        $calculation = null;
        try {
            $calculation = $this->payoutCalc->calculatePayoutAmount((int) $beneficiaryId);
        } catch (\Throwable) {}

        return Inertia::render('Distribution/ApprovalForm', [
            'beneficiary'   => [
                'id'               => $beneficiary->id,
                'bin'              => $beneficiary->bin,
                'family_head_name' => $beneficiary->family_head_name,
                'barangay'         => $beneficiary->barangay,
                'municipality'     => $beneficiary->municipality,
                'province'         => $beneficiary->province,
                'is_active'        => $beneficiary->is_active,
                'household_size'   => $beneficiary->household_size,
            ],
            'calculation'   => $calculation,
            'summary'       => $beneficiary->complianceSummary ? [
                'education_compliance_percentage' => $beneficiary->complianceSummary->education_compliance_percentage,
                'health_compliance_percentage'    => $beneficiary->complianceSummary->health_compliance_percentage,
                'fds_compliance_percentage'       => $beneficiary->complianceSummary->fds_compliance_percentage,
                'overall_compliance_status'       => $beneficiary->complianceSummary->overall_compliance_status,
            ] : null,
            'canApprove'    => Auth::user()->hasRole(['Compliance Verifier', 'Administrator']),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // downloadReceipt — GET /distribution/{id}/receipt
    // ─────────────────────────────────────────────────────────────────────────

    public function downloadReceipt($distributionId)
    {
        CashGrantDistribution::findOrFail($distributionId); // guard — 404 if not found

        return $this->receiptSvc->streamReceipt((int) $distributionId);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // serveSignature — GET /distribution/signature/{path}
    // ─────────────────────────────────────────────────────────────────────────

    public function serveSignature(Request $request, $encodedPath)
    {
        $path = base64_decode($encodedPath);

        // Security: ensure path stays within signatures directory
        if (! str_starts_with($path, 'private/signatures/')) {
            abort(403, 'Access denied.');
        }

        if (! \Illuminate\Support\Facades\Storage::disk('local')->exists($path)) {
            abort(404, 'Signature not found.');
        }

        return \Illuminate\Support\Facades\Storage::disk('local')->response($path, 'signature.png', [
            'Content-Type' => 'image/png',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function formatDistribution(CashGrantDistribution $d, bool $detailed = false): array
    {
        $data = [
            'id'                           => $d->id,
            'beneficiary_id'               => $d->beneficiary_id,
            'payout_amount'                => (float) $d->payout_amount,
            'payout_period'                => $d->payout_period,
            'payout_month'                 => $d->payout_month,
            'payout_year'                  => $d->payout_year,
            'distributed_at'               => $d->distributed_at?->toIso8601String(),
            'distributed_at_human'         => $d->distributed_at?->diffForHumans(),
            'payment_method'               => $d->payment_method,
            'transaction_reference_number' => $d->transaction_reference_number,
            'remarks'                      => $d->remarks,
            'receipt_url'                  => route('distribution.receipt.download', $d->id),
            'beneficiary'                  => $d->relationLoaded('beneficiary') ? [
                'id'               => $d->beneficiary?->id,
                'bin'              => $d->beneficiary?->bin,
                'family_head_name' => $d->beneficiary?->family_head_name,
                'barangay'         => $d->beneficiary?->barangay,
                'municipality'     => $d->beneficiary?->municipality,
            ] : null,
            'distributed_by' => $d->relationLoaded('distributedBy') ? [
                'id'              => $d->distributedBy?->id,
                'name'            => $d->distributedBy?->name,
                'role'            => $d->distributedBy?->role,
                'office_location' => $d->distributedBy?->office_location,
            ] : null,
        ];

        if ($detailed) {
            $data['approved_by'] = $d->relationLoaded('approvedBy') ? [
                'id'   => $d->approvedBy?->id,
                'name' => $d->approvedBy?->name,
                'role' => $d->approvedBy?->role,
            ] : null;
            $data['has_signature'] = ! empty($d->received_signature_path);
            $data['signature_url'] = $d->received_signature_path
                ? $this->signatureSvc->getSignatureUrl($d->received_signature_path)
                : null;
        }

        return $data;
    }

    private function logActivity(
        ?int    $beneficiaryId,
        string  $activityType,
        string  $description,
        string  $status,
        Request $request,
        ?string $remarks = null
    ): void {
        try {
            VerificationActivityLog::create([
                'user_id'              => Auth::id(),
                'beneficiary_id'       => $beneficiaryId,
                'activity_type'        => $activityType,
                'activity_description' => $description,
                'ip_address'           => $request->ip(),
                'user_agent'           => $request->userAgent(),
                'status'               => $status,
                'remarks'              => $remarks,
            ]);
        } catch (\Throwable $e) {
            Log::error('DistributionController: Failed to write activity log: ' . $e->getMessage());
        }
    }
}
