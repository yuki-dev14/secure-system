<?php

namespace App\Http\Controllers;

use App\Models\Beneficiary;
use App\Models\DuplicateDetectionLog;
use App\Models\QrCode;
use App\Models\VerificationActivityLog;
use App\Services\ComplianceCheckingService;
use App\Services\DuplicateDetectionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class VerificationController extends Controller
{
    public function __construct(
        private ComplianceCheckingService $compliance,
        private DuplicateDetectionService $duplicates,
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // scan — process a scanned QR token
    // ─────────────────────────────────────────────────────────────────────────

    public function scan(Request $request)
    {
        $validated = $request->validate([
            'verification_token' => [
                'required',
                'string',
                'size:64',
                'regex:/^[a-zA-Z0-9]+$/',
            ],
        ]);

        $token = $validated['verification_token'];

        // ── Basic rapid-scan cache flag (simple rate check) ──────────────────
        $scanCacheKey = 'scan_token_' . md5($token) . '_user_' . Auth::id();
        $isDuplicateScan = (bool) Cache::get($scanCacheKey);
        Cache::put($scanCacheKey, now()->toIso8601String(), 300);

        // ── Lookup QR record ─────────────────────────────────────────────────
        $qrCode = QrCode::where('verification_token', $token)
            ->latest('generated_at')
            ->first();

        if (! $qrCode) {
            $this->logActivity(null, 'scan', 'QR scan failed — token not found', 'failed', $request, 'Token does not exist in the system.');
            return response()->json([
                'success' => false,
                'message' => 'Invalid QR code. Token not found.',
            ], 404);
        }

        if (! $qrCode->is_valid) {
            $this->logActivity($qrCode->beneficiary_id, 'scan', 'QR scan failed — token revoked', 'failed', $request, 'QR code has been revoked.');
            return response()->json([
                'success' => false,
                'message' => 'This QR code has been revoked.',
            ], 422);
        }

        if ($qrCode->expires_at && $qrCode->expires_at->isPast()) {
            $this->logActivity($qrCode->beneficiary_id, 'scan', 'QR scan failed — token expired', 'failed', $request, 'QR code expired on ' . $qrCode->expires_at->format('M d, Y'));
            return response()->json([
                'success' => false,
                'message' => 'This QR code has expired on ' . $qrCode->expires_at->format('M d, Y') . '.',
            ], 422);
        }

        // ── Beneficiary checks ───────────────────────────────────────────────
        $beneficiary = Beneficiary::with(['familyMembers', 'qrCodes'])
            ->find($qrCode->beneficiary_id);

        if (! $beneficiary) {
            $this->logActivity(null, 'scan', 'QR scan failed — beneficiary not found', 'failed', $request);
            return response()->json([
                'success' => false,
                'message' => 'Beneficiary record not found.',
            ], 404);
        }

        if (! $beneficiary->is_active) {
            $this->logActivity($beneficiary->id, 'scan', 'QR scan failed — beneficiary inactive', 'failed', $request, 'Beneficiary account is deactivated.');
            return response()->json([
                'success' => false,
                'message' => 'Beneficiary is inactive and cannot be verified.',
            ], 422);
        }

        // ── Real-time duplicate & ghost detection ────────────────────────────
        $duplicateResult = $this->duplicates->checkDuringVerification(
            $beneficiary->id,
            $token
        );

        // Block on high-confidence duplicate if no active override exists
        if ($duplicateResult['recommendation'] === 'block') {
            $hasOverride = $this->hasActiveOverride($beneficiary->id);

            if (! $hasOverride) {
                $this->logActivity(
                    $beneficiary->id,
                    'scan',
                    'QR scan BLOCKED — high-confidence duplicate detected (score: ' . $duplicateResult['confidence_score'] . ')',
                    'failed',
                    $request,
                    'Duplicate detection: ' . $duplicateResult['duplicate_type']
                );

                return response()->json([
                    'success'          => false,
                    'blocked'          => true,
                    'message'          => 'Distribution blocked: High-confidence duplicate detected. Administrator approval required to proceed.',
                    'duplicate_result' => $duplicateResult,
                ], 422);
            }
        }

        // ── Compliance + Eligibility ─────────────────────────────────────────
        $complianceStatus = $this->compliance->checkOverallCompliance($beneficiary->id);
        $eligibility      = $this->checkEligibilityData($beneficiary->id);

        // ── Recent distribution history ──────────────────────────────────────
        $distributions = DB::table('cash_grant_distributions')
            ->where('beneficiary_id', $beneficiary->id)
            ->orderByDesc('distribution_date')
            ->limit(5)
            ->get(['id', 'distribution_date', 'amount', 'status', 'distribution_period'])
            ->map(fn($d) => [
                'id'                  => $d->id,
                'distribution_date'   => $d->distribution_date,
                'amount'              => $d->amount,
                'status'              => $d->status,
                'distribution_period' => $d->distribution_period,
            ])
            ->toArray();

        // ── Log successful scan ──────────────────────────────────────────────
        $description = 'QR code scanned for beneficiary ' . $beneficiary->bin . ' by ' . Auth::user()->name;
        $remarks     = null;
        if ($isDuplicateScan) {
            $description .= ' [RAPID RESCAN DETECTED]';
            $remarks      = 'Rapid rescan detected within 5 minutes.';
        }
        if ($duplicateResult['is_duplicate']) {
            $description .= ' [DUPLICATE FLAG: ' . $duplicateResult['duplicate_type'] . ' score=' . $duplicateResult['confidence_score'] . ']';
            $remarks .= ($remarks ? ' | ' : '') . 'Duplicate detection triggered.';
        }

        $this->logActivity($beneficiary->id, 'scan', $description, 'success', $request, $remarks);

        return response()->json([
            'success'            => true,
            'is_duplicate_scan'  => $isDuplicateScan,
            'message'            => 'QR code scanned successfully.',
            'beneficiary'        => [
                'id'                    => $beneficiary->id,
                'bin'                   => $beneficiary->bin,
                'family_head_name'      => $beneficiary->family_head_name,
                'family_head_birthdate' => $beneficiary->family_head_birthdate
                    ? \Carbon\Carbon::parse($beneficiary->family_head_birthdate)->format('M d, Y')
                    : null,
                'gender'                => $beneficiary->gender,
                'civil_status'          => $beneficiary->civil_status,
                'contact_number'        => $beneficiary->contact_number,
                'barangay'              => $beneficiary->barangay,
                'municipality'          => $beneficiary->municipality,
                'province'              => $beneficiary->province,
                'is_active'             => $beneficiary->is_active,
                'household_size'        => $beneficiary->household_size,
                'family_members_count'  => $beneficiary->familyMembers->count(),
                'family_members'        => $beneficiary->familyMembers->map(fn($m) => [
                    'id'                   => $m->id,
                    'full_name'            => $m->full_name,
                    'relationship_to_head' => $m->relationship_to_head,
                    'age'                  => $m->age,
                    'gender'               => $m->gender,
                ])->toArray(),
            ],
            'qr_code' => [
                'id'         => $qrCode->id,
                'expires_at' => $qrCode->expires_at?->format('M d, Y'),
                'is_valid'   => $qrCode->is_valid,
            ],
            'compliance'       => $complianceStatus,
            'eligibility'      => $eligibility,
            'distributions'    => $distributions,
            'duplicate_result' => $duplicateResult,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // verify — manual identity confirmation
    // ─────────────────────────────────────────────────────────────────────────

    public function verify(Request $request, $beneficiaryId)
    {
        /** @var \App\Models\User $actor */
        $actor = Auth::user();
        abort_if(
            ! $actor->hasRole(['Compliance Verifier', 'Administrator']),
            403,
            'Only Compliance Verifiers and Administrators can perform manual verifications.'
        );

        $validated = $request->validate([
            'verification_notes' => 'nullable|string|max:1000',
        ]);

        $beneficiary = Beneficiary::findOrFail($beneficiaryId);
        $eligibility = $this->checkEligibilityData($beneficiary->id);

        $this->logActivity(
            $beneficiary->id,
            'verify',
            'Manual verification performed for beneficiary ' . $beneficiary->bin . ' by ' . $actor->name,
            'success',
            $request,
            $validated['verification_notes'] ?? null
        );

        return response()->json([
            'success'        => true,
            'message'        => 'Beneficiary manually verified.',
            'verified_by'    => $actor->name,
            'verified_at'    => now()->toIso8601String(),
            'beneficiary_id' => $beneficiary->id,
            'eligibility'    => $eligibility,
            'notes'          => $validated['verification_notes'] ?? null,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // overrideDuplicate — Administrator approves a blocked scan
    // ─────────────────────────────────────────────────────────────────────────

    public function overrideDuplicate(Request $request, $beneficiaryId)
    {
        /** @var \App\Models\User $admin */
        $admin = Auth::user();
        abort_if(! $admin->isAdministrator(), 403, 'Only Administrators can override duplicate blocks.');

        $validated = $request->validate([
            'override_reason' => 'required|string|min:10|max:1000',
            'action'          => 'required|in:different_person,review_later',
        ]);

        $beneficiary = Beneficiary::findOrFail($beneficiaryId);

        // Mark all active duplicate logs for this beneficiary as overridden
        DuplicateDetectionLog::where('primary_beneficiary_id', $beneficiary->id)
            ->where('status', 'active')
            ->update([
                'status'             => 'overridden',
                'override_reason'    => $validated['override_reason'],
                'override_by_user_id'=> Auth::id(),
                'override_at'        => now(),
                'resolved_at'        => now(),
                'resolver_user_id'   => Auth::id(),
            ]);

        // Cache the override for 30 minutes so next scan allows through
        Cache::put($this->overrideCacheKey($beneficiary->id), [
            'user_id'   => Auth::id(),
            'user_name' => Auth::user()->name,
            'reason'    => $validated['override_reason'],
            'action'    => $validated['action'],
            'at'        => now()->toIso8601String(),
        ], 1800);

        $this->logActivity(
            $beneficiary->id,
            'duplicate_override',
            'Duplicate block overridden for ' . $beneficiary->bin . ' by Administrator ' . Auth::user()->name
                . '. Action: ' . $validated['action'],
            'success',
            $request,
            $validated['override_reason']
        );

        return response()->json([
            'success'       => true,
            'message'       => 'Duplicate override approved. Proceed with caution.',
            'override_by'   => Auth::user()->name,
            'override_at'   => now()->toIso8601String(),
            'action'        => $validated['action'],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // getDuplicateFlags — list active duplicate flags for a beneficiary
    // ─────────────────────────────────────────────────────────────────────────

    public function getDuplicateFlags($beneficiaryId)
    {
        $beneficiary = Beneficiary::findOrFail($beneficiaryId);

        $flags = DuplicateDetectionLog::with(['duplicateBeneficiary', 'overrideBy'])
            ->where('primary_beneficiary_id', $beneficiary->id)
            ->orderByDesc('detection_date')
            ->limit(20)
            ->get()
            ->map(fn($log) => [
                'id'                    => $log->id,
                'duplicate_type'        => $log->duplicate_type,
                'confidence_score'      => $log->confidence_score,
                'severity'              => $log->severity,
                'recommendation'        => $log->recommendation,
                'status'                => $log->status,
                'detection_date'        => optional($log->detection_date)->format('M d, Y h:i A'),
                'detection_date_human'  => optional($log->detection_date)->diffForHumans(),
                'detection_details'     => $log->detection_details,
                'override_reason'       => $log->override_reason,
                'override_by'           => $log->overrideBy?->name,
                'override_at'           => optional($log->override_at)?->format('M d, Y h:i A'),
                'similar_beneficiary'   => $log->duplicateBeneficiary ? [
                    'id'               => $log->duplicateBeneficiary->id,
                    'bin'              => $log->duplicateBeneficiary->bin,
                    'family_head_name' => $log->duplicateBeneficiary->family_head_name,
                    'municipality'     => $log->duplicateBeneficiary->municipality,
                    'is_active'        => $log->duplicateBeneficiary->is_active,
                ] : null,
            ]);

        return response()->json([
            'success'          => true,
            'beneficiary_id'   => $beneficiary->id,
            'active_flags'     => $flags->where('status', 'active')->values(),
            'all_flags'        => $flags->values(),
            'has_block'        => $flags->where('status', 'active')->where('recommendation', 'block')->isNotEmpty(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // checkEligibility — public JSON endpoint
    // ─────────────────────────────────────────────────────────────────────────

    public function checkEligibility($beneficiaryId)
    {
        $beneficiary = Beneficiary::findOrFail($beneficiaryId);
        $eligibility = $this->checkEligibilityData($beneficiary->id);

        return response()->json([
            'success'        => true,
            'beneficiary_id' => $beneficiary->id,
            'bin'            => $beneficiary->bin,
            'eligibility'    => $eligibility,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // getVerificationHistory — paginated with filters
    // ─────────────────────────────────────────────────────────────────────────

    public function getVerificationHistory(Request $request, $beneficiaryId)
    {
        $beneficiary = Beneficiary::findOrFail($beneficiaryId);

        $query = VerificationActivityLog::with('user')
            ->where('beneficiary_id', $beneficiary->id)
            ->orderByDesc('created_at');

        if ($request->filled('status'))        $query->where('status', $request->status);
        if ($request->filled('activity_type')) $query->where('activity_type', $request->activity_type);
        if ($request->filled('date_from'))     $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->filled('date_to'))       $query->whereDate('created_at', '<=', $request->date_to);
        if ($request->filled('operator_id'))   $query->where('user_id', $request->operator_id);

        $logs = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => collect($logs->items())->map(fn($log) => [
                'id'                   => $log->id,
                'activity_type'        => $log->activity_type,
                'activity_description' => $log->activity_description,
                'status'               => $log->status,
                'remarks'              => $log->remarks,
                'ip_address'           => $log->ip_address,
                'operator'             => $log->user ? ['id' => $log->user->id, 'name' => $log->user->name] : null,
                'created_at'           => $log->created_at->toIso8601String(),
                'created_at_human'     => $log->created_at->diffForHumans(),
                'created_at_formatted' => $log->created_at->format('M d, Y h:i A'),
            ])->toArray(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'per_page'     => $logs->perPage(),
                'total'        => $logs->total(),
                'from'         => $logs->firstItem(),
                'to'           => $logs->lastItem(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // showScannerPage — Inertia page
    // ─────────────────────────────────────────────────────────────────────────

    public function showScannerPage()
    {
        return Inertia::render('Verification/Scanner', [
            'canVerify' => Auth::user()->hasRole(['Compliance Verifier', 'Administrator']),
            'isAdmin'   => Auth::user()->isAdministrator(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function hasActiveOverride(int $beneficiaryId): bool
    {
        return Cache::has($this->overrideCacheKey($beneficiaryId));
    }

    private function overrideCacheKey(int $beneficiaryId): string
    {
        return 'dup_override_' . $beneficiaryId;
    }

    private function checkEligibilityData(int $beneficiaryId): array
    {
        $beneficiary = Beneficiary::with('familyMembers')->find($beneficiaryId);
        $reasons  = [];
        $eligible = true;

        if (! $beneficiary->is_active) {
            $eligible  = false;
            $reasons[] = 'Beneficiary account is inactive.';
        }

        $hasDuplicateFlag = DB::table('duplicate_detection_logs')
            ->where(function ($q) use ($beneficiaryId) {
                $q->where('primary_beneficiary_id', $beneficiaryId)
                  ->orWhere('duplicate_beneficiary_id', $beneficiaryId);
            })
            ->where('status', 'active')
            ->where('recommendation', 'block')
            ->exists();

        if ($hasDuplicateFlag) {
            $eligible  = false;
            $reasons[] = 'Beneficiary has an active high-confidence duplicate/ghost flag.';
        }

        $compliance = $this->compliance->checkOverallCompliance($beneficiaryId);
        if (! $compliance['compliant']) {
            $eligible = false;
            foreach ($compliance['details'] as $detail) {
                if (! $detail['compliant']) {
                    $reasons[] = $detail['reason'] ?? ('Non-compliant: ' . $detail['type']);
                }
            }
        }

        $currentPeriod      = Carbon::now()->format('Y-m');
        $alreadyDistributed = DB::table('cash_grant_distributions')
            ->where('beneficiary_id', $beneficiaryId)
            ->where('status', 'completed')
            ->where('distribution_period', $currentPeriod)
            ->exists();

        if ($alreadyDistributed) {
            $eligible  = false;
            $reasons[] = 'Already received distribution for the current period (' . Carbon::now()->format('F Y') . ').';
        }

        return [
            'eligible'   => $eligible,
            'reasons'    => $reasons,
            'compliance' => $compliance,
            'period'     => Carbon::now()->format('F Y'),
        ];
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
            Log::error('Failed to write verification activity log: ' . $e->getMessage());
        }
    }
}
