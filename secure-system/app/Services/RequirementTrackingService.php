<?php

namespace App\Services;

use App\Models\Beneficiary;
use App\Models\FamilyMember;
use App\Models\SubmittedRequirement;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RequirementTrackingService
 *
 * Determines which 4Ps program documents a beneficiary household
 * is required to submit, cross-references them against what has
 * actually been submitted/approved, and keeps the compliance
 * summary cache up-to-date when the document status changes.
 */
class RequirementTrackingService
{
    // ─────────────────────────────────────────────────────────────────────────
    // Requirement Definitions
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Document types that are always required (household-level).
     */
    private const ALWAYS_REQUIRED = [
        'proof_of_income',
        'valid_id',
        'birth_certificate',   // For the household head at minimum
    ];

    /**
     * Document types required per school-age child (5–21 y/o).
     */
    private const SCHOOL_AGE_REQUIRED = [
        'school_record',
    ];

    /**
     * Document types required per child under 5.
     */
    private const UNDER_FIVE_REQUIRED = [
        'health_record',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // getCompletionStatus
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Full completion status for a beneficiary's 4Ps documents.
     *
     * @return array{
     *   completion_percentage: float,
     *   total_required: int,
     *   approved: int,
     *   pending: int,
     *   rejected: int,
     *   missing: int,
     *   eligible_for_distribution: bool,
     *   items: array,
     *   family_context: array,
     * }
     */
    public function getCompletionStatus(int $beneficiaryId): array
    {
        $beneficiary   = Beneficiary::with('familyMembers')->findOrFail($beneficiaryId);
        $familyMembers = $beneficiary->familyMembers;

        // Build requirement checklist
        $requiredItems = $this->buildRequiredItemList($beneficiary, $familyMembers);

        // Load all submitted docs, indexed by type
        $submitted = SubmittedRequirement::where('beneficiary_id', $beneficiaryId)
            ->orderByDesc('submitted_at')
            ->get()
            ->groupBy('requirement_type');

        // Evaluate status of each required item
        $items = [];
        $approved = $pending = $rejected = $missing = 0;

        foreach ($requiredItems as $item) {
            $type      = $item['type'];
            $docs      = $submitted->get($type, collect());
            $bestDoc   = $this->bestDocumentForType($docs);
            $status    = $this->resolveStatus($bestDoc);

            match ($status) {
                'approved' => $approved++,
                'pending'  => $pending++,
                'rejected' => $rejected++,
                default    => $missing++,
            };

            $items[] = array_merge($item, [
                'status'           => $status,
                'document'         => $bestDoc ? [
                    'id'              => $bestDoc->id,
                    'file_name'       => $bestDoc->file_name,
                    'file_size_human' => $bestDoc->fileSizeFormatted(),
                    'file_type'       => $bestDoc->file_type,
                    'submitted_at'    => $bestDoc->submitted_at?->format('M d, Y h:i A'),
                    'approval_date'   => $bestDoc->approval_date?->format('M d, Y'),
                    'rejection_reason'=> $bestDoc->rejection_reason,
                    'is_expired'      => $bestDoc->isExpired(),
                    'expiration_date' => $bestDoc->expiration_date?->toDateString(),
                    'download_url'    => route('requirements.download', $bestDoc->id),
                ] : null,
                'alternatives_count' => $docs->count(),
            ]);
        }

        $total              = count($requiredItems);
        $completionPct      = $total > 0 ? round(($approved / $total) * 100, 1) : 0.0;
        $eligibleForDist    = $approved === $total && $missing === 0 && $rejected === 0;

        return [
            'completion_percentage'     => $completionPct,
            'total_required'            => $total,
            'approved'                  => $approved,
            'pending'                   => $pending,
            'rejected'                  => $rejected,
            'missing'                   => $missing,
            'eligible_for_distribution' => $eligibleForDist,
            'items'                     => $items,
            'family_context'            => [
                'household_size'      => $beneficiary->household_size,
                'family_member_count' => $familyMembers->count(),
                'school_age_children' => $familyMembers->filter(fn($m) => $m->is_school_age)->count(),
                'under_five_children' => $familyMembers->filter(fn($m) => $m->needs_health_monitoring)->count(),
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // getMissingRequirements
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Return an array of requirement types that are missing or rejected.
     *
     * @return array<array{type: string, label: string, reason: string}>
     */
    public function getMissingRequirements(int $beneficiaryId): array
    {
        $status  = $this->getCompletionStatus($beneficiaryId);
        $missing = [];

        foreach ($status['items'] as $item) {
            if (in_array($item['status'], ['missing', 'rejected', 'not_submitted'])) {
                $missing[] = [
                    'type'   => $item['type'],
                    'label'  => $item['label'],
                    'reason' => $item['status'] === 'rejected'
                        ? ($item['document']['rejection_reason'] ?? 'Document was rejected.')
                        : 'Document has not been submitted.',
                    'required_for'  => $item['required_for'] ?? 'household',
                    'member_name'   => $item['member_name'] ?? null,
                ];
            }
        }

        return $missing;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // updateComplianceCache
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Recompute and upsert a row in compliance_summary_cache for the given
     * beneficiary, incorporating the current requirement completion status.
     *
     * This is called after every approve / reject event so the cache always
     * reflects the latest state without a full real-time calculation on each
     * dashboard request.
     */
    public function updateComplianceCache(int $beneficiaryId): void
    {
        try {
            $completionStatus  = $this->getCompletionStatus($beneficiaryId);
            $missingItems      = $this->getMissingRequirements($beneficiaryId);
            $complianceService = app(ComplianceCheckingService::class);
            $complianceData    = $complianceService->checkOverallCompliance($beneficiaryId);

            // Derive percentages for the dimensions we track
            $educationPct = $this->extractPct($complianceData, 'education');
            $healthPct    = $this->extractPct($complianceData, 'health');
            $fdsPct       = $this->extractPct($complianceData, 'fds');

            // Roll in document completion weight (30% weighting — configurable)
            $docWeight         = (float) config('secure.document_weight', 0.3);
            $complianceWeight  = 1.0 - $docWeight;

            $overallStatus = $complianceData['compliant'] && $completionStatus['eligible_for_distribution']
                ? 'compliant'
                : ($complianceData['compliant'] || $completionStatus['completion_percentage'] > 0
                    ? 'partial'
                    : 'non_compliant');

            // Build the full missing requirements JSON — combine compliance and document gaps
            $missingJson = array_merge(
                $missingItems,
                $this->formatComplianceMissing($complianceData['details'] ?? [])
            );

            DB::table('compliance_summary_cache')->upsert(
                [
                    'beneficiary_id'               => $beneficiaryId,
                    'education_compliance_percentage' => $educationPct,
                    'health_compliance_percentage'    => $healthPct,
                    'fds_compliance_percentage'       => $fdsPct,
                    'overall_compliance_status'       => $overallStatus,
                    'missing_requirements'            => json_encode($missingJson),
                    'last_updated_at'                 => now(),
                    'cache_validity'                  => now()->addHours(6),
                ],
                ['beneficiary_id'],
                [
                    'education_compliance_percentage',
                    'health_compliance_percentage',
                    'fds_compliance_percentage',
                    'overall_compliance_status',
                    'missing_requirements',
                    'last_updated_at',
                    'cache_validity',
                ]
            );

            Log::info("RequirementTrackingService: Cache updated for beneficiary #{$beneficiaryId}. Status: {$overallStatus}");
        } catch (\Throwable $e) {
            Log::error("RequirementTrackingService::updateComplianceCache failed for #{$beneficiaryId}: " . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // checkEligibilityForDistribution
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Full eligibility gate check for cash grant distribution.
     *
     * @return array{
     *   eligible: bool,
     *   missing_items: array,
     *   blocking_reasons: string[],
     *   completion_percentage: float,
     *   compliance_met: bool,
     *   has_expired_documents: bool,
     * }
     */
    public function checkEligibilityForDistribution(int $beneficiaryId): array
    {
        $status         = $this->getCompletionStatus($beneficiaryId);
        $missing        = $this->getMissingRequirements($beneficiaryId);
        $blockingReasons = [];

        // ── 1. Required documents all approved? ──────────────────────────────
        if ($status['missing'] > 0) {
            $blockingReasons[] = "{$status['missing']} required document(s) not yet submitted.";
        }
        if ($status['pending'] > 0) {
            $blockingReasons[] = "{$status['pending']} document(s) awaiting review approval.";
        }
        if ($status['rejected'] > 0) {
            $blockingReasons[] = "{$status['rejected']} document(s) have been rejected and need resubmission.";
        }

        // ── 2. No expired documents ──────────────────────────────────────────
        $hasExpired = SubmittedRequirement::where('beneficiary_id', $beneficiaryId)
            ->where('approval_status', 'approved')
            ->expired()
            ->exists();

        if ($hasExpired) {
            $blockingReasons[] = 'One or more approved documents have expired and must be renewed.';
        }

        // ── 3. Compliance check ──────────────────────────────────────────────
        try {
            $complianceService = app(ComplianceCheckingService::class);
            $complianceData    = $complianceService->checkOverallCompliance($beneficiaryId);
            $complianceMet     = $complianceData['compliant'];
            if (! $complianceMet) {
                foreach ($complianceData['details'] as $detail) {
                    if (! $detail['compliant'] && ! empty($detail['reason'])) {
                        $blockingReasons[] = '[Compliance] ' . $detail['reason'];
                    }
                }
            }
        } catch (\Throwable $e) {
            $complianceMet = false;
            $blockingReasons[] = 'Compliance check could not be completed.';
        }

        return [
            'eligible'              => empty($blockingReasons),
            'missing_items'         => $missing,
            'blocking_reasons'      => $blockingReasons,
            'completion_percentage' => $status['completion_percentage'],
            'compliance_met'        => $complianceMet ?? false,
            'has_expired_documents' => $hasExpired,
            'total_required'        => $status['total_required'],
            'approved_count'        => $status['approved'],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build the full list of required documents for the household,
     * taking into account family composition (school-age, under-5 children).
     */
    private function buildRequiredItemList(Beneficiary $beneficiary, $familyMembers): array
    {
        $items = [];

        // Household-level requirements
        foreach (self::ALWAYS_REQUIRED as $type) {
            $items[] = [
                'type'         => $type,
                'label'        => $this->typeLabel($type),
                'required_for' => 'household',
                'member_name'  => null,
                'member_id'    => null,
                'is_required'  => true,
                'description'  => $this->typeDescription($type),
            ];
        }

        // Per school-age child
        $schoolAgeChildren = $familyMembers->filter(fn($m) => $m->is_school_age);
        foreach ($schoolAgeChildren as $member) {
            foreach (self::SCHOOL_AGE_REQUIRED as $type) {
                $items[] = [
                    'type'         => $type,
                    'label'        => $this->typeLabel($type),
                    'required_for' => 'school_age_child',
                    'member_name'  => $member->full_name,
                    'member_id'    => $member->id,
                    'is_required'  => true,
                    'description'  => "Required for {$member->full_name} (age {$member->age}) — school enrollment record.",
                ];
            }
        }

        // Per under-5 child
        $underFiveChildren = $familyMembers->filter(fn($m) => $m->needs_health_monitoring && $m->age < 5);
        foreach ($underFiveChildren as $member) {
            foreach (self::UNDER_FIVE_REQUIRED as $type) {
                $items[] = [
                    'type'         => $type,
                    'label'        => $this->typeLabel($type),
                    'required_for' => 'under_five_child',
                    'member_name'  => $member->full_name,
                    'member_id'    => $member->id,
                    'is_required'  => true,
                    'description'  => "Required for {$member->full_name} (age {$member->age}) — health monitoring.",
                ];
            }
        }

        // If no school-age/under-5 children, still require health_record and school_record
        // at least once for the household to avoid zero-item checklists
        $types = array_column($items, 'type');
        if (! in_array('school_record', $types)) {
            $items[] = [
                'type'         => 'school_record',
                'label'        => $this->typeLabel('school_record'),
                'required_for' => 'household',
                'member_name'  => null,
                'member_id'    => null,
                'is_required'  => false, // Not strictly required if no school-age kids
                'description'  => 'Not required — no school-age children in household.',
            ];
        }
        if (! in_array('health_record', $types)) {
            $items[] = [
                'type'         => 'health_record',
                'label'        => $this->typeLabel('health_record'),
                'required_for' => 'household',
                'member_name'  => null,
                'member_id'    => null,
                'is_required'  => false,
                'description'  => 'Not required — no children under 5 in household.',
            ];
        }
        if (! in_array('other', $types)) {
            $items[] = [
                'type'         => 'other',
                'label'        => $this->typeLabel('other'),
                'required_for' => 'household',
                'member_name'  => null,
                'member_id'    => null,
                'is_required'  => false,
                'description'  => 'Optional supplemental document.',
            ];
        }

        return $items;
    }

    /**
     * Among multiple submissions for the same type, pick the "best" one:
     * approved > pending > rejected, all ordered desc by submitted_at.
     */
    private function bestDocumentForType($docs): ?SubmittedRequirement
    {
        if ($docs->isEmpty()) return null;

        $approved = $docs->firstWhere('approval_status', 'approved');
        if ($approved && ! $approved->isExpired()) return $approved;

        $pending  = $docs->firstWhere('approval_status', 'pending');
        if ($pending) return $pending;

        return $docs->first(); // rejected — most recent
    }

    private function resolveStatus(?SubmittedRequirement $doc): string
    {
        if (! $doc) return 'missing';
        if ($doc->isExpired()) return 'expired';
        return $doc->approval_status; // approved / pending / rejected
    }

    private function extractPct(array $complianceData, string $type): float
    {
        $detail = collect($complianceData['details'] ?? [])->firstWhere('type', $type);
        if (! $detail) return 0.0;
        return $detail['compliant'] ? 100.0 : 0.0;
    }

    private function formatComplianceMissing(array $details): array
    {
        $items = [];
        foreach ($details as $detail) {
            if (! $detail['compliant']) {
                $items[] = [
                    'type'  => $detail['type'],
                    'label' => $detail['label'],
                    'reason'=> $detail['reason'] ?? 'Compliance condition not met.',
                    'required_for' => 'compliance',
                    'member_name'  => null,
                ];
            }
        }
        return $items;
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'birth_certificate' => 'Birth Certificate',
            'school_record'     => 'School Record',
            'health_record'     => 'Health Record',
            'proof_of_income'   => 'Proof of Income',
            'valid_id'          => 'Valid ID',
            'other'             => 'Other Document',
            default             => ucwords(str_replace('_', ' ', $type)),
        };
    }

    private function typeDescription(string $type): string
    {
        return match ($type) {
            'birth_certificate' => 'Birth certificate of the household head and all family members.',
            'school_record'     => 'School enrollment record or report card for school-age children (5–21).',
            'health_record'     => 'Health monitoring record / immunization card for children under 5.',
            'proof_of_income'   => 'Income documentation (payslip, certificate of indigency, etc.).',
            'valid_id'          => 'Government-issued ID of the household head.',
            'other'             => 'Any other supporting document required by the DSWD case worker.',
            default             => 'Required document for 4Ps program compliance.',
        };
    }
}
