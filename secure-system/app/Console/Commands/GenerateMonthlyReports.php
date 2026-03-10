<?php

namespace App\Console\Commands;

use App\Services\DataExportService;
use App\Services\ReportScheduler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateMonthlyReports extends Command
{
    protected $signature   = 'reports:monthly {--archive : Archive the PDF to storage}';
    protected $description = 'Generate complete monthly reports with all metrics and export to PDF.';

    public function __construct(
        private ReportScheduler   $scheduler,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Generating monthly reports…');

        try {
            $report   = $this->scheduler->generateMonthlyReports();
            $month    = now()->subMonth()->format('Y-m');

            $this->newLine();
            $this->line('══════════════════════════════════════════════════');
            $this->info('   MONTHLY COMPREHENSIVE REPORT');
            $this->line("   Month: {$report['period']['from']} → {$report['period']['to']}");
            $this->line('══════════════════════════════════════════════════');

            $benefGrowth  = $report['benef_growth'] >= 0 ? '+' . $report['benef_growth'] . '%' : $report['benef_growth'] . '%';
            $distGrowth   = $report['dist_growth']  >= 0 ? '+' . $report['dist_growth'] . '%'  : $report['dist_growth'] . '%';

            $this->table(
                ['Metric', 'Value', 'vs Last Month'],
                [
                    ['Total Beneficiaries',   $report['total_beneficiaries'],                        '—'],
                    ['Active Beneficiaries',  $report['active_beneficiaries'],                       '—'],
                    ['New Registrations',     $report['new_beneficiaries'],                          $benefGrowth],
                    ['Distributions',         $report['dist_count'],                                 '—'],
                    ['Amount Distributed',    'PHP ' . number_format($report['dist_amount'], 2),     $distGrowth],
                    ['Compliance Rate',       $report['compliance_rate'] . '%',                      '—'],
                    ['Non-Compliant',         $report['non_compliant'],                              '—'],
                    ['Fraud Detections',      $report['fraud_detections'],                           '—'],
                    ['Security Events',       $report['security_events'],                            '—'],
                ]
            );

            $this->info("✅ Monthly report for {$month} generated successfully.");
            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $this->error('Monthly report generation failed: ' . $e->getMessage());
            Log::error('GenerateMonthlyReports command failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return Command::FAILURE;
        }
    }
}
