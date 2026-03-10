<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\VerificationActivityLog;
use App\Services\AuditLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class GenerateSecurityReport extends Command
{
    protected $signature   = 'audit:security-report {--email : Also email the report to administrators}';
    protected $description = 'Analyze audit logs for security issues and generate a weekly summary report.';

    public function __construct(private AuditLogService $auditSvc)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $from = now()->subWeek()->startOfDay();
        $to   = now()->endOfDay();

        $this->info("Generating security report: {$from->toDateString()} → {$to->toDateString()}");

        $report = $this->buildReport($from, $to);
        $this->printReport($report);

        // Persist a summary security event in the audit log
        $this->auditSvc->logSecurityEvent(
            'weekly_security_report',
            "Weekly security report generated. "
            . "Total logs: {$report['total_logs']}. "
            . "Failed attempts: {$report['failed_attempts']}. "
            . "High/Critical events: {$report['high_critical_events']}.",
            empty($report['flags']) ? 'low' : 'medium'
        );

        if ($this->option('email')) {
            $this->emailAdmins($report);
        }

        $this->info('✅ Security report complete.');
        return Command::SUCCESS;
    }

    private function buildReport(\Carbon\Carbon $from, \Carbon\Carbon $to): array
    {
        $base = VerificationActivityLog::whereBetween('timestamp', [$from, $to]);

        $totalLogs      = (clone $base)->count();
        $failedAttempts = (clone $base)->where('status', 'failed')->count();
        $highCritical   = (clone $base)->whereIn('severity', ['high', 'critical'])->count();
        $exportEvents   = (clone $base)->where('activity_type', 'export')->count();
        $deleteEvents   = (clone $base)->where('activity_type', 'delete')->count();

        // IPs with repeated failures
        $susFails = (clone $base)
            ->where('status', 'failed')
            ->select('ip_address', DB::raw('count(*) as count'))
            ->groupBy('ip_address')
            ->having('count', '>', 5)
            ->get();

        // Users active between 2-5 AM
        $unusualHours = (clone $base)
            ->whereRaw("EXTRACT(HOUR FROM timestamp) BETWEEN 2 AND 5")
            ->select('user_id', DB::raw('count(*) as cnt'))
            ->groupBy('user_id')
            ->having('cnt', '>', 3)
            ->with('user:id,name')
            ->get();

        // Flag unusual patterns
        $flags = [];
        if ($susFails->isNotEmpty()) {
            $flags[] = "⚠️ {$susFails->count()} IP(s) had > 5 failed attempts.";
        }
        if ($unusualHours->isNotEmpty()) {
            $flags[] = "⚠️ {$unusualHours->count()} user(s) active between 2–5 AM.";
        }
        if ($highCritical > 0) {
            $flags[] = "🚨 {$highCritical} high/critical severity event(s) recorded.";
        }
        if ($exportEvents > 50) {
            $flags[] = "⚠️ {$exportEvents} export events — potential bulk data extraction.";
        }
        if ($deleteEvents > 20) {
            $flags[] = "⚠️ {$deleteEvents} delete events — unusually high deletion activity.";
        }

        return [
            'period'            => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'total_logs'        => $totalLogs,
            'failed_attempts'   => $failedAttempts,
            'high_critical_events' => $highCritical,
            'export_events'     => $exportEvents,
            'delete_events'     => $deleteEvents,
            'suspicious_ips'    => $susFails->toArray(),
            'unusual_hours'     => $unusualHours->map(fn ($r) => [
                'user' => $r->user?->name ?? 'Unknown', 'count' => $r->cnt
            ])->toArray(),
            'flags'             => $flags,
            'generated_at'      => now()->toDateTimeString(),
        ];
    }

    private function printReport(array $r): void
    {
        $this->newLine();
        $this->line('═══════════════════════════════════════════');
        $this->info('   WEEKLY SECURITY REPORT');
        $this->line("   Period: {$r['period']['from']} → {$r['period']['to']}");
        $this->line('═══════════════════════════════════════════');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Audit Logs',       $r['total_logs']],
                ['Failed Attempts',        $r['failed_attempts']],
                ['High/Critical Events',   $r['high_critical_events']],
                ['Export Events',          $r['export_events']],
                ['Delete Events',          $r['delete_events']],
                ['Suspicious IPs',         count($r['suspicious_ips'])],
                ['Unusual Hour Users',     count($r['unusual_hours'])],
            ]
        );

        if (!empty($r['flags'])) {
            $this->newLine();
            $this->warn('  ⚑ Flags Raised:');
            foreach ($r['flags'] as $flag) {
                $this->warn("    {$flag}");
            }
        } else {
            $this->info('  ✓ No significant security flags this week.');
        }
        $this->newLine();
    }

    private function emailAdmins(array $report): void
    {
        $admins = User::where('role', 'Administrator')->whereNotNull('email')->get();
        foreach ($admins as $admin) {
            try {
                // Real implementation would use a Mailable class
                // Mail::to($admin->email)->send(new SecurityReportMail($report, $admin));
                Log::info("audit:security-report — Would email {$admin->email} (Mailable not yet created).");
            } catch (\Throwable $e) {
                Log::error('GenerateSecurityReport email failed: ' . $e->getMessage());
            }
        }
    }
}
