<?php

namespace App\Console\Commands;

use App\Models\SubmittedRequirement;
use App\Models\User;
use App\Notifications\DocumentExpiringNotification;
use App\Services\DocumentExpirationService;
use App\Services\RequirementTrackingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Scheduled command to:
 *  1. Mark expired documents in submitted_requirements
 *  2. Send expiration notifications (30-day window)
 *  3. Update compliance cache for affected beneficiaries
 *
 * Schedule: run daily (via app/Console/Kernel.php or bootstrap/app.php)
 *   $schedule->command('requirements:process-expirations')->dailyAt('07:00');
 */
class ProcessDocumentExpirations extends Command
{
    protected $signature   = 'requirements:process-expirations
                                {--notify-days=30 : Number of days before expiry to notify}
                                {--dry-run        : Report only — do not update or notify}';

    protected $description = 'Mark expired documents, send expiration notifications, and refresh compliance cache.';

    public function __construct(
        private DocumentExpirationService  $expirationService,
        private RequirementTrackingService $trackingService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $notifyDays = (int) $this->option('notify-days');
        $dryRun     = (bool) $this->option('dry-run');

        $this->info($dryRun ? '🔍 DRY RUN — no changes will be made.' : '⚙ Processing document expirations…');

        // ── Step 1: Mark expired documents ──────────────────────────────────
        $expiredDocs = SubmittedRequirement::expired()
            ->where('approval_status', 'approved')
            ->with(['submittedBy', 'beneficiary'])
            ->get();

        $markedCount = 0;
        $affectedBeneficiaries = [];

        foreach ($expiredDocs as $req) {
            $this->line("  [EXPIRED] #{$req->id} — {$req->requirement_type} — {$req->beneficiary?->bin}");

            if (! $dryRun) {
                if ($this->expirationService->markAsExpired($req->id)) {
                    $markedCount++;
                    $affectedBeneficiaries[$req->beneficiary_id] = true;

                    // Notify submitter
                    if ($req->submittedBy) {
                        try {
                            $req->submittedBy->notify(new DocumentExpiringNotification($req, 0));
                        } catch (\Throwable $e) {
                            Log::warning("ProcessDocumentExpirations: Notify failed for req #{$req->id}: " . $e->getMessage());
                        }
                    }
                }
            }
        }

        // ── Step 2: Send upcoming expiration notifications ──────────────────
        $expiringSoon = SubmittedRequirement::expiringSoon($notifyDays)
            ->where('approval_status', 'approved')
            ->with(['submittedBy', 'beneficiary'])
            ->get();

        $notifiedCount = 0;

        foreach ($expiringSoon as $req) {
            $daysLeft = $req->daysUntilExpiration();
            $this->line("  [SOON] #{$req->id} — {$req->requirement_type} — {$daysLeft}d — {$req->beneficiary?->bin}");

            if (! $dryRun && $req->submittedBy) {
                try {
                    $req->submittedBy->notify(new DocumentExpiringNotification($req, $daysLeft));
                    $notifiedCount++;
                } catch (\Throwable $e) {
                    Log::warning("ProcessDocumentExpirations: Notify-soon failed for req #{$req->id}: " . $e->getMessage());
                }
            }
        }

        // ── Step 3: Refresh compliance cache for affected beneficiaries ─────
        $cacheRefreshed = 0;
        if (! $dryRun) {
            foreach (array_keys($affectedBeneficiaries) as $beneficiaryId) {
                try {
                    $this->trackingService->updateComplianceCache((int) $beneficiaryId);
                    $cacheRefreshed++;
                } catch (\Throwable $e) {
                    Log::error("ProcessDocumentExpirations: Cache refresh failed for #{$beneficiaryId}: " . $e->getMessage());
                }
            }
        }

        // ── Summary ──────────────────────────────────────────────────────────
        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Expired documents found',        $expiredDocs->count()],
                ['Marked as expired',               $markedCount],
                ['Expiring soon (notified)',         $expiringSoon->count()],
                ['Notifications sent (expiring)',    $notifiedCount],
                ['Compliance caches refreshed',     $cacheRefreshed],
            ]
        );

        $this->info($dryRun ? 'Dry run complete.' : '✅ Expiration processing complete.');

        return Command::SUCCESS;
    }
}
