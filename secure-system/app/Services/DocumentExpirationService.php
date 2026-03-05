<?php

namespace App\Services;

use App\Models\SubmittedRequirement;
use App\Models\VerificationActivityLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class DocumentExpirationService
{
    /**
     * Expiration periods (in months) per requirement type.
     * null = does not expire.
     */
    private const EXPIRATION_MONTHS = [
        'birth_certificate' => null,    // Never expires
        'school_record'     => 12,      // 1 year
        'health_record'     => 6,       // 6 months
        'proof_of_income'   => 12,      // 1 year
        'valid_id'          => null,    // Per-ID: handled by expiration_date field
        'other'             => 12,      // Default 1 year
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // setExpirationDate
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Calculate and return the expiration date for a given requirement type.
     * Returns null if the document type never expires.
     */
    public function setExpirationDate(string $requirementType, Carbon $submittedDate): ?Carbon
    {
        $months = self::EXPIRATION_MONTHS[$requirementType] ?? 12;

        if ($months === null) {
            return null; // No expiration
        }

        return (clone $submittedDate)->addMonths($months);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // checkExpiration
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Check whether a requirement document has expired.
     *
     * @return array{is_expired: bool, days_until_expiration: int|null, expiration_date: string|null}
     */
    public function checkExpiration(int $requirementId): array
    {
        $requirement = SubmittedRequirement::findOrFail($requirementId);

        if (! $requirement->expiration_date) {
            return [
                'is_expired'            => false,
                'days_until_expiration' => null,
                'expiration_date'       => null,
                'message'               => 'This document type does not expire.',
            ];
        }

        $today      = Carbon::today();
        $expDate    = Carbon::parse($requirement->expiration_date);
        $isExpired  = $expDate->isPast() || $expDate->isToday() === false && $expDate->lt($today);
        $daysLeft   = $isExpired ? 0 : (int) $today->diffInDays($expDate);

        return [
            'is_expired'            => $isExpired,
            'days_until_expiration' => $isExpired ? null : $daysLeft,
            'expiration_date'       => $expDate->toDateString(),
            'message'               => $isExpired
                ? 'Document has expired on ' . $expDate->format('M d, Y') . '.'
                : "Document expires in {$daysLeft} day(s) on " . $expDate->format('M d, Y') . '.',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // sendExpirationNotifications
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Find documents expiring within the next 30 days and log/notify.
     *
     * @return array{notified: int, documents: array}
     */
    public function sendExpirationNotifications(int $days = 30): array
    {
        $expiring = SubmittedRequirement::with(['beneficiary', 'submittedBy'])
            ->expiringSoon($days)
            ->where('approval_status', 'approved')
            ->get();

        $notified  = 0;
        $documents = [];

        foreach ($expiring as $req) {
            $daysLeft = $req->daysUntilExpiration();

            try {
                // Log the notification event
                VerificationActivityLog::create([
                    'user_id'              => $req->submitted_by_user_id,
                    'beneficiary_id'       => $req->beneficiary_id,
                    'activity_type'        => 'expiration_alert',
                    'activity_description' => sprintf(
                        'Document expiration alert: %s for beneficiary %s expires in %d day(s) on %s.',
                        $req->requirement_type,
                        $req->beneficiary?->bin ?? $req->beneficiary_id,
                        $daysLeft,
                        $req->expiration_date->format('M d, Y')
                    ),
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'System/ExpirationJob',
                    'status'     => 'success',
                    'remarks'    => "Automated expiration notification. Days remaining: {$daysLeft}",
                ]);

                $notified++;
                $documents[] = [
                    'requirement_id'    => $req->id,
                    'requirement_type'  => $req->requirement_type,
                    'beneficiary_id'    => $req->beneficiary_id,
                    'beneficiary_bin'   => $req->beneficiary?->bin,
                    'expiration_date'   => $req->expiration_date->toDateString(),
                    'days_remaining'    => $daysLeft,
                    'submitted_by'      => $req->submittedBy?->name,
                ];
            } catch (\Throwable $e) {
                Log::error("DocumentExpirationService: Failed to notify for requirement #{$req->id}: " . $e->getMessage());
            }
        }

        return [
            'notified'  => $notified,
            'documents' => $documents,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // markAsExpired
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Mark a specific requirement as expired (sets approval_status = 'rejected' with reason).
     */
    public function markAsExpired(int $requirementId): bool
    {
        try {
            $requirement = SubmittedRequirement::findOrFail($requirementId);

            if (! $requirement->expiration_date || ! $requirement->isExpired()) {
                return false;
            }

            $requirement->update([
                'approval_status'  => 'rejected',
                'rejection_reason' => 'Document expired on ' . $requirement->expiration_date->format('M d, Y') . '.',
                'approval_date'    => now(),
            ]);

            VerificationActivityLog::create([
                'user_id'              => null,
                'beneficiary_id'       => $requirement->beneficiary_id,
                'activity_type'        => 'document_expired',
                'activity_description' => sprintf(
                    'Document automatically marked as expired: %s (ID #%d) expired on %s.',
                    $requirement->requirement_type,
                    $requirement->id,
                    $requirement->expiration_date->format('M d, Y')
                ),
                'ip_address' => '127.0.0.1',
                'user_agent' => 'System/ExpirationJob',
                'status'     => 'success',
                'remarks'    => 'Automated expiration marking.',
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error("DocumentExpirationService::markAsExpired failed for ID #{$requirementId}: " . $e->getMessage());
            return false;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // processExpiredDocuments (batch)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Find all expired documents across all beneficiaries and mark them.
     *
     * @return array{marked: int, skipped: int}
     */
    public function processExpiredDocuments(): array
    {
        $expired = SubmittedRequirement::expired()
            ->where('approval_status', 'approved')
            ->get();

        $marked  = 0;
        $skipped = 0;

        foreach ($expired as $req) {
            if ($this->markAsExpired($req->id)) {
                $marked++;
            } else {
                $skipped++;
            }
        }

        return ['marked' => $marked, 'skipped' => $skipped];
    }
}
