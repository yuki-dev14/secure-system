<?php

namespace App\Console\Commands;

use App\Services\ReportScheduler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateWeeklyReports extends Command
{
    protected $signature   = 'reports:weekly {--email : Email report to administrators}';
    protected $description = 'Generate comprehensive weekly summary report.';

    public function __construct(private ReportScheduler $scheduler)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Generating weekly reports…');

        try {
            $report = $this->scheduler->generateWeeklyReports();

            $this->newLine();
            $this->line('══════════════════════════════════════');
            $this->info('   WEEKLY SUMMARY REPORT');
            $this->line("   Period: {$report['period']['from']} → {$report['period']['to']}");
            $this->line('══════════════════════════════════════');

            $regTrend = $report['reg_trend'] >= 0
                ? '+' . $report['reg_trend'] . '% ↑'
                : $report['reg_trend'] . '% ↓';

            $distTrend = $report['dist_trend'] >= 0
                ? '+' . $report['dist_trend'] . '% ↑'
                : $report['dist_trend'] . '% ↓';

            $this->table(
                ['Metric', 'Value', 'vs Last Week'],
                [
                    ['New Registrations',   $report['registrations'],                   $regTrend],
                    ['Distributions',       $report['dist_count'],                      '—'],
                    ['Amount Distributed',  'PHP ' . number_format($report['dist_amount'], 2), $distTrend],
                    ['Compliance Rate',     $report['compliance_rate'] . '%',           '—'],
                    ['Non-Compliant',       $report['non_compliant'],                   '—'],
                    ['Security Events',     $report['security_events'],                 '—'],
                    ['Fraud Detections',    $report['fraud_detections'],               '—'],
                ]
            );

            if (! empty($report['top_users'])) {
                $this->newLine();
                $this->info('Top Performers:');
                foreach ($report['top_users'] as $i => $u) {
                    $this->line("  " . ($i + 1) . ". {$u['name']} ({$u['count']} activities)");
                }
            }

            if ($report['fraud_detections'] > 0) {
                $this->warn("⚠️  {$report['fraud_detections']} fraud detection(s) this week.");
            }

            $this->info('✅ Weekly report generated successfully.');
            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $this->error('Weekly report generation failed: ' . $e->getMessage());
            Log::error('GenerateWeeklyReports command failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return Command::FAILURE;
        }
    }
}
