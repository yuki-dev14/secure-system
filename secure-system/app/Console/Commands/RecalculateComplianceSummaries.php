<?php

namespace App\Console\Commands;

use App\Models\Beneficiary;
use App\Services\ComplianceSummaryService;
use Illuminate\Console\Command;

class RecalculateComplianceSummaries extends Command
{
    protected $signature = 'compliance:recalculate-summaries {--beneficiary= : Only recalculate for a specific beneficiary ID}';
    protected $description = 'Recalculate and refresh the compliance summary cache for all (or one) beneficiaries';

    public function __construct(
        private ComplianceSummaryService $summaryService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('[SECURE] Compliance Summary Recalculation — ' . now()->toDateTimeString());

        $specificId = $this->option('beneficiary');

        if ($specificId) {
            $this->processSingle((int)$specificId);
            return Command::SUCCESS;
        }

        $beneficiaries = Beneficiary::select('id', 'bin', 'family_head_name')->get();
        $this->info("Processing {$beneficiaries->count()} beneficiaries…");

        $bar     = $this->output->createProgressBar($beneficiaries->count());
        $success = 0;
        $failed  = 0;

        $bar->start();

        foreach ($beneficiaries as $b) {
            try {
                $this->summaryService->calculateOverallCompliance($b->id);
                $success++;
            } catch (\Throwable $e) {
                $failed++;
                $this->newLine();
                $this->warn("  ✗ Beneficiary #{$b->id} ({$b->bin}): " . $e->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✓ Done. Success: {$success}  |  Failed: {$failed}");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function processSingle(int $id): void
    {
        $b = Beneficiary::find($id);
        if (!$b) {
            $this->error("Beneficiary #{$id} not found.");
            return;
        }
        $result = $this->summaryService->calculateOverallCompliance($id);
        $this->info("✓ Recalculated for #{$id} ({$b->family_head_name}): status = {$result['overall_compliance_status']}");
    }
}
