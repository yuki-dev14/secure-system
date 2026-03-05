<?php

namespace App\Console\Commands;

use App\Services\ComplianceAlertService;
use Illuminate\Console\Command;

class GenerateComplianceAlerts extends Command
{
    protected $signature = 'compliance:generate-alerts {--dry-run : Preview alerts without inserting}';
    protected $description = 'Generate in-app compliance alerts for Field Officers and Verifiers';

    public function __construct(
        private ComplianceAlertService $alertService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('[SECURE] Generating compliance alerts — ' . now()->toDateTimeString());

        if ($this->option('dry-run')) {
            $this->warn('Dry-run mode: no notifications will be inserted.');
            return Command::SUCCESS;
        }

        try {
            $results = $this->alertService->generateAlerts();

            $this->table(
                ['Alert Type', 'Notifications Created'],
                [
                    ['Non-Compliant Beneficiaries',  $results['non_compliant']],
                    ['Pending Verifications',         $results['pending_verification']],
                    ['Expiring Compliance Periods',   $results['expiring_period']],
                ]
            );

            $total = array_sum($results);
            $this->info("✓ {$total} alert notification(s) created.");
        } catch (\Throwable $e) {
            $this->error('Alert generation failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
