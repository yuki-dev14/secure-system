<?php

namespace App\Http\Controllers;

use App\Models\Beneficiary;
use App\Models\SubmittedRequirement;
use App\Models\VerificationActivityLog;
use App\Notifications\DocumentApprovedNotification;
use App\Notifications\DocumentRejectedNotification;
use App\Services\ComplianceCheckingService;
use App\Services\DocumentExpirationService;
use App\Services\FileSecurityService;
use App\Services\RequirementTrackingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class RequirementsController extends Controller
{
    private const REQUIREMENT_TYPES = [
        'birth_certificate',
        'school_record',
        'health_record',
        'proof_of_income',
        'valid_id',
        'other',
    ];

    public function __construct(
        private FileSecurityService       $fileSecurity,
        private DocumentExpirationService $expiration,
        private ComplianceCheckingService $compliance,
        private RequirementTrackingService $tracking,
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // upload — POST /requirements/upload/{beneficiaryId}
    // ─────────────────────────────────────────────────────────────────────────

    public function upload(Request $request, $beneficiaryId)
    {
        // ── Validate inputs ─────────────────────────────────────────────────
        $request->validate([
            'beneficiary_id'   => ['sometimes', 'integer', 'exists:beneficiaries,id'],
            'requirement_type' => ['required', Rule::in(self::REQUIREMENT_TYPES)],
            'file'             => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png,doc,docx',
                'max:5120',
            ],
        ]);

        $beneficiary = Beneficiary::findOrFail($beneficiaryId);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('file');

        // ── Security checks ─────────────────────────────────────────────────
        $securityResult = $this->fileSecurity->validateFile($file);
        if (! $securityResult['is_valid']) {
            return response()->json([
                'success' => false,
                'message' => 'File failed security validation.',
                'errors'  => $securityResult['errors'],
            ], 422);
        }

        // ── Generate safe filename & store ──────────────────────────────────
        $type      = $request->input('requirement_type');
        $extension = strtolower($file->getClientOriginalExtension());
        $safeFilename = $this->fileSecurity->generateSecureFilename($type, $beneficiaryId, $extension);

        $storagePath = "documents/{$beneficiaryId}";

        try {
            $path = $file->storeAs($storagePath, $safeFilename, 'private');
        } catch (\Throwable $e) {
            Log::error("RequirementsController::upload - storage failed: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'File storage failed. Please try again.',
            ], 500);
        }

        // ── Calculate expiration date ───────────────────────────────────────
        $expirationDate = $this->expiration->setExpirationDate($type, Carbon::now());

        // ── Create DB record ────────────────────────────────────────────────
        try {
            $requirement = DB::transaction(function () use (
                $beneficiaryId, $type, $path, $file, $safeFilename, $expirationDate
            ) {
                return SubmittedRequirement::create([
                    'beneficiary_id'       => $beneficiaryId,
                    'requirement_type'     => $type,
                    'file_path'            => $path,
                    'file_name'            => $file->getClientOriginalName(),
                    'file_size'            => $file->getSize(),
                    'file_type'            => $file->getMimeType(),
                    'submitted_at'         => now(),
                    'submitted_by_user_id' => Auth::id(),
                    'approval_status'      => 'pending',
                    'expiration_date'      => $expirationDate?->toDateString(),
                ]);
            });
        } catch (\Throwable $e) {
            // Rollback: remove stored file
            Storage::disk('private')->delete($path);
            Log::error("RequirementsController::upload - DB insert failed: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to record the uploaded document.',
            ], 500);
        }

        // ── Activity log ────────────────────────────────────────────────────
        $this->logActivity(
            $beneficiary->id,
            'upload',
            sprintf(
                'Document uploaded: %s (%s, %.1f KB) for beneficiary %s by %s.',
                $type,
                $file->getClientOriginalName(),
                round($file->getSize() / 1024, 1),
                $beneficiary->bin,
                Auth::user()->name
            ),
            'success',
            $request
        );

        return response()->json([
            'success'        => true,
            'message'        => 'Document uploaded successfully.',
            'requirement'    => [
                'id'               => $requirement->id,
                'requirement_type' => $requirement->requirement_type,
                'file_name'        => $requirement->file_name,
                'file_size'        => $requirement->file_size,
                'file_size_human'  => $requirement->fileSizeFormatted(),
                'file_type'        => $requirement->file_type,
                'submitted_at'     => $requirement->submitted_at->toIso8601String(),
                'approval_status'  => $requirement->approval_status,
                'expiration_date'  => $expirationDate?->toDateString(),
            ],
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // index — GET /requirements/{beneficiaryId}
    // ─────────────────────────────────────────────────────────────────────────

    public function index($beneficiaryId)
    {
        $beneficiary = Beneficiary::findOrFail($beneficiaryId);

        $requirements = SubmittedRequirement::with(['submittedBy:id,name,role', 'approvedBy:id,name,role'])
            ->where('beneficiary_id', $beneficiaryId)
            ->orderByDesc('submitted_at')
            ->get();

        // Group by requirement_type
        $grouped = $requirements->groupBy('requirement_type')->map(function ($items, $type) {
            return [
                'type'      => $type,
                'count'     => $items->count(),
                'documents' => $items->map(fn($r) => $this->formatRequirement($r))->values(),
            ];
        })->values();

        // Missing types
        $submittedTypes = $requirements->pluck('requirement_type')->unique()->values();
        $missingTypes   = collect(self::REQUIREMENT_TYPES)->diff($submittedTypes)->values();

        return response()->json([
            'success'        => true,
            'beneficiary_id' => $beneficiary->id,
            'bin'            => $beneficiary->bin,
            'grouped'        => $grouped,
            'missing_types'  => $missingTypes,
            'total'          => $requirements->count(),
            'pending_count'  => $requirements->where('approval_status', 'pending')->count(),
            'approved_count' => $requirements->where('approval_status', 'approved')->count(),
            'rejected_count' => $requirements->where('approval_status', 'rejected')->count(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // show — GET /requirements/show/{requirementId}
    // ─────────────────────────────────────────────────────────────────────────

    public function show($requirementId)
    {
        $requirement = SubmittedRequirement::with([
            'beneficiary:id,bin,family_head_name,municipality',
            'submittedBy:id,name,role',
            'approvedBy:id,name,role',
        ])->findOrFail($requirementId);

        // Permission: all authenticated users can view
        return response()->json([
            'success'     => true,
            'requirement' => $this->formatRequirement($requirement),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // download — GET /requirements/download/{requirementId}
    // ─────────────────────────────────────────────────────────────────────────

    public function download(Request $request, $requirementId)
    {
        $requirement = SubmittedRequirement::findOrFail($requirementId);

        // Check file exists on disk
        if (! Storage::disk('private')->exists($requirement->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'File not found on server.',
            ], 404);
        }

        // Log download activity
        $this->logActivity(
            $requirement->beneficiary_id,
            'download',
            sprintf(
                'Document downloaded: %s (%s) by %s.',
                $requirement->requirement_type,
                $requirement->file_name,
                Auth::user()->name
            ),
            'success',
            $request
        );

        $disk     = Storage::disk('private');
        $filePath = $requirement->file_path;
        $fileName = $requirement->file_name;
        $mimeType = $requirement->file_type;

        return response()->streamDownload(function () use ($disk, $filePath) {
            echo $disk->get($filePath);
        }, $fileName, [
            'Content-Type'        => $mimeType,
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // approve — POST /requirements/approve/{requirementId}
    // ─────────────────────────────────────────────────────────────────────────

    public function approve(Request $request, $requirementId)
    {
        $actor = Auth::user();
        abort_if(
            ! $actor->hasRole(['Compliance Verifier', 'Administrator']),
            403,
            'Only Compliance Verifiers and Administrators can approve documents.'
        );

        $requirement = SubmittedRequirement::findOrFail($requirementId);

        if ($requirement->approval_status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending documents can be approved. Current status: ' . $requirement->approval_status,
            ], 422);
        }

        DB::transaction(function () use ($requirement, $actor) {
            $requirement->update([
                'approval_status'     => 'approved',
                'approval_date'       => now(),
                'approved_by_user_id' => $actor->id,
                'rejection_reason'    => null,
            ]);
        });

        // Trigger compliance recalculation and cache update
        try {
            $this->compliance->checkOverallCompliance($requirement->beneficiary_id);
            $this->tracking->updateComplianceCache($requirement->beneficiary_id);
        } catch (\Throwable $e) {
            Log::warning("Compliance/cache update failed after approval: " . $e->getMessage());
        }

        // Notify the submitter
        try {
            $submitter = $requirement->submittedBy;
            if ($submitter && $submitter->id !== $actor->id) {
                $requirement->load('beneficiary');
                $submitter->notify(new DocumentApprovedNotification($requirement, $actor->name));
            }
        } catch (\Throwable $e) {
            Log::warning("DocumentApprovedNotification failed: " . $e->getMessage());
        }

        $this->logActivity(
            $requirement->beneficiary_id,
            'approve',
            sprintf(
                'Document approved: %s (ID #%d) by %s.',
                $requirement->requirement_type,
                $requirement->id,
                $actor->name
            ),
            'success',
            $request
        );

        return response()->json([
            'success'      => true,
            'message'      => 'Document approved successfully.',
            'requirement'  => $this->formatRequirement($requirement->fresh(['submittedBy', 'approvedBy'])),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // reject — POST /requirements/reject/{requirementId}
    // ─────────────────────────────────────────────────────────────────────────

    public function reject(Request $request, $requirementId)
    {
        $actor = Auth::user();
        abort_if(
            ! $actor->hasRole(['Compliance Verifier', 'Administrator']),
            403,
            'Only Compliance Verifiers and Administrators can reject documents.'
        );

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $requirement = SubmittedRequirement::findOrFail($requirementId);

        if ($requirement->approval_status === 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Approved documents cannot be rejected. Please contact an Administrator.',
            ], 422);
        }

        DB::transaction(function () use ($requirement, $actor, $validated) {
            $requirement->update([
                'approval_status'     => 'rejected',
                'rejection_reason'    => $validated['rejection_reason'],
                'approval_date'       => now(),
                'approved_by_user_id' => $actor->id,
            ]);
        });

        // Notify submitter of rejection
        try {
            $submitter = $requirement->submittedBy;
            if ($submitter && $submitter->id !== $actor->id) {
                $requirement->load('beneficiary');
                $submitter->notify(new DocumentRejectedNotification(
                    $requirement,
                    $actor->name,
                    $validated['rejection_reason']
                ));
            }
        } catch (\Throwable $e) {
            Log::warning("DocumentRejectedNotification failed: " . $e->getMessage());
        }

        // Update compliance cache
        try {
            $this->tracking->updateComplianceCache($requirement->beneficiary_id);
        } catch (\Throwable $e) {
            Log::warning("Cache update failed after rejection: " . $e->getMessage());
        }

        $this->logActivity(
            $requirement->beneficiary_id,
            'reject',
            sprintf(
                'Document rejected: %s (ID #%d) by %s. Reason: %s',
                $requirement->requirement_type,
                $requirement->id,
                $actor->name,
                $validated['rejection_reason']
            ),
            'success',
            $request
        );

        return response()->json([
            'success'          => true,
            'message'          => 'Document rejected.',
            'rejection_reason' => $validated['rejection_reason'],
            'requirement'      => $this->formatRequirement($requirement->fresh(['submittedBy', 'approvedBy'])),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // bulkApprove — POST /requirements/bulk-approve
    // ─────────────────────────────────────────────────────────────────────────

    public function bulkApprove(Request $request)
    {
        $actor = Auth::user();
        abort_if(
            ! $actor->hasRole(['Compliance Verifier', 'Administrator']),
            403,
            'Only Compliance Verifiers and Administrators can bulk approve documents.'
        );

        $validated = $request->validate([
            'requirement_ids'   => ['required', 'array', 'min:1', 'max:100'],
            'requirement_ids.*' => ['required', 'integer', 'exists:submitted_requirements,id'],
        ]);

        $ids           = $validated['requirement_ids'];
        $approvedCount = 0;
        $failedCount   = 0;
        $failed        = [];

        // Load all pending requirements matching the IDs
        $requirements = SubmittedRequirement::whereIn('id', $ids)
            ->where('approval_status', 'pending')
            ->get();

        DB::transaction(function () use ($requirements, $actor, &$approvedCount) {
            foreach ($requirements as $req) {
                $req->update([
                    'approval_status'     => 'approved',
                    'approval_date'       => now(),
                    'approved_by_user_id' => $actor->id,
                    'rejection_reason'    => null,
                ]);
                $approvedCount++;
            }
        });

        // Identify IDs that were not pending (failed)
        $approvedIds = $requirements->pluck('id')->toArray();
        $failedIds   = array_diff($ids, $approvedIds);
        $failedCount = count($failedIds);

        // Log batch approval
        $this->logActivity(
            null,
            'bulk_approve',
            sprintf(
                'Bulk approval by %s: %d document(s) approved, %d skipped (not pending).',
                $actor->name,
                $approvedCount,
                $failedCount
            ),
            'success',
            $request,
        );

        return response()->json([
            'success'        => true,
            'message'        => "Bulk approval complete.",
            'approved_count' => $approvedCount,
            'failed_count'   => $failedCount,
            'failed_ids'     => array_values($failedIds),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // checkExpiredDocuments — GET /requirements/expired
    // ─────────────────────────────────────────────────────────────────────────

    public function checkExpiredDocuments()
    {
        $actor = Auth::user();
        abort_if(! $actor->isAdministrator(), 403, 'Only Administrators can access expired documents.');

        $expired = SubmittedRequirement::with(['beneficiary:id,bin,family_head_name', 'submittedBy:id,name'])
            ->expired()
            ->orderByDesc('expiration_date')
            ->get()
            ->map(fn($r) => [
                'id'                => $r->id,
                'requirement_type'  => $r->requirement_type,
                'file_name'         => $r->file_name,
                'approval_status'   => $r->approval_status,
                'expiration_date'   => $r->expiration_date?->toDateString(),
                'submitted_at'      => $r->submitted_at?->toIso8601String(),
                'submitted_by'      => $r->submittedBy?->name,
                'beneficiary'       => $r->beneficiary ? [
                    'id'               => $r->beneficiary->id,
                    'bin'              => $r->beneficiary->bin,
                    'family_head_name' => $r->beneficiary->family_head_name,
                ] : null,
            ]);

        // Auto-mark expired
        $marked = 0;
        foreach ($expired as $doc) {
            if ($this->expiration->markAsExpired($doc['id'])) {
                $marked++;
            }
        }

        return response()->json([
            'success'       => true,
            'expired_count' => $expired->count(),
            'marked_count'  => $marked,
            'documents'     => $expired->values(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // requirementsPage — Inertia page
    // ─────────────────────────────────────────────────────────────────────────

    public function requirementsPage($beneficiaryId)
    {
        $beneficiary   = Beneficiary::findOrFail($beneficiaryId);
        $eligibility   = $this->tracking->checkEligibilityForDistribution((int) $beneficiaryId);

        return Inertia::render('Requirements/Index', [
            'beneficiary'      => [
                'id'               => $beneficiary->id,
                'bin'              => $beneficiary->bin,
                'family_head_name' => $beneficiary->family_head_name,
                'municipality'     => $beneficiary->municipality,
            ],
            'requirementTypes' => self::REQUIREMENT_TYPES,
            'canApprove'       => Auth::user()->hasRole(['Compliance Verifier', 'Administrator']),
            'isAdmin'          => Auth::user()->isAdministrator(),
            'eligibility'      => $eligibility,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // completionStatus — GET /requirements/completion/{beneficiaryId}
    // ─────────────────────────────────────────────────────────────────────────

    public function completionStatus($beneficiaryId)
    {
        $status      = $this->tracking->getCompletionStatus((int) $beneficiaryId);
        $missing     = $this->tracking->getMissingRequirements((int) $beneficiaryId);
        $eligibility = $this->tracking->checkEligibilityForDistribution((int) $beneficiaryId);

        return response()->json([
            'success'            => true,
            'beneficiary_id'     => (int) $beneficiaryId,
            'completion_status'  => $status,
            'missing_requirements' => $missing,
            'eligibility'        => $eligibility,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function formatRequirement(SubmittedRequirement $r): array
    {
        $expCheck = $r->expiration_date ? $this->expiration->checkExpiration($r->id) : null;

        return [
            'id'                => $r->id,
            'requirement_type'  => $r->requirement_type,
            'file_name'         => $r->file_name,
            'file_size'         => $r->file_size,
            'file_size_human'   => $r->fileSizeFormatted(),
            'file_type'         => $r->file_type,
            'submitted_at'      => $r->submitted_at?->toIso8601String(),
            'submitted_at_human'=> $r->submitted_at?->diffForHumans(),
            'submitted_at_fmt'  => $r->submitted_at?->format('M d, Y h:i A'),
            'approval_status'   => $r->approval_status,
            'approval_date'     => $r->approval_date?->toIso8601String(),
            'rejection_reason'  => $r->rejection_reason,
            'expiration_date'   => $r->expiration_date?->toDateString(),
            'is_expired'        => $expCheck['is_expired'] ?? false,
            'days_until_expiry' => $expCheck['days_until_expiration'] ?? null,
            'submitted_by'      => $r->submittedBy ? ['id' => $r->submittedBy->id, 'name' => $r->submittedBy->name] : null,
            'approved_by'       => $r->approvedBy  ? ['id' => $r->approvedBy->id,  'name' => $r->approvedBy->name]  : null,
            'beneficiary'       => $r->relationLoaded('beneficiary') && $r->beneficiary ? [
                'id'               => $r->beneficiary->id,
                'bin'              => $r->beneficiary->bin,
                'family_head_name' => $r->beneficiary->family_head_name,
            ] : null,
            'download_url'      => route('requirements.download', $r->id),
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
            Log::error('RequirementsController: Failed to write activity log: ' . $e->getMessage());
        }
    }
}
