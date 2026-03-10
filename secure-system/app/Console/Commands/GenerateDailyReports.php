<?php

namespace App\Console\Commands;

use App\Services\ReportScheduler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateDailyReports extends Command
{
    protected $signature   = 'reports:daily {--email : Email report to administrators}';
    protected $description = 'Generate daily summary report with key metrics.';

    public function __construct(private ReportScheduler $scheduler)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Generating daily reports…');

        try {
            $report = $this->scheduler->generateDailyReports();

            $this->newLine();
            $this->line('══════════════════════════════════════');
            $this->info('   DAILY SUMMARY REPORT');
            $this->line("   Date: {$report['period']['from']}");
            $this->line('══════════════════════════════════════');
            $this->table(
                ['Metric', 'Value'],
                [
                    ['New Registrations',    $report['new_registrations']],
                    ['Distributions Count',  $report['distribution_count']],
                    ['Amount Distributed',   'PHP ' . number_format($report['distribution_amount'], 2)],
                    ['Compliance Rate',      $report['compliance_rate'] . '%'],
                    ['Security Alerts',      $report['security_alerts']],
                    ['Fraud Attempts',       $report['fraud_attempts']],
                ]
            );

            if ($report['security_alerts'] > 0) {
                $this->warn("⚠️  {$report['security_alerts']} security alert(s) require attention.");
            }

            $this->info('✅ Daily report generated successfully.');
            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $this->error('Daily report generation failed: ' . $e->getMessage());
            Log::error('GenerateDailyReports command failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return Command::FAILURE;
        }
    }
}
