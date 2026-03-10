<?php

namespace App\Services;

use App\Models\Beneficiary;
use App\Models\CashGrantDistribution;
use App\Models\ComplianceSummaryCache;
use App\Models\DuplicateDetectionLog;
use App\Models\User;
use App\Models\VerificationActivityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ReportScheduler
{
    // ─────────────────────────────────────────────────────────────
    // generateDailyReports
    // ─────────────────────────────────────────────────────────────

    public function generateDailyReports(): array
    {
        $from = now()->subDay()->startOfDay();
        $to   = now()->subDay()->endOfDay();

        $newRegistrations = Beneficiary::whereBetween('created_at', [$from, $to])->count();

        $distributions = CashGrantDistribution::whereBetween('distributed_at', [$from, $to]);
        $distCount  = (clone $distributions)->count();
        $distAmount = (clone $distributions)->sum('payout_amount');

        $compliantTotal = ComplianceSummaryCache::where('overall_status', 'compliant')->count();
        $totalBenef     = ComplianceSummaryCache::count();
        $complianceRate = $totalBenef > 0 ? round($compliantTotal / $totalBenef * 100, 2) : 0;

        $securityAlerts = VerificationActivityLog::whereBetween('timestamp', [$from, $to])
            ->whereIn('severity', ['high', 'critical'])
            ->where('activity_category', 'security')
            ->count();

        $fraudAttempts = DuplicateDetectionLog::whereBetween('detection_date', [$from, $to])->count();

        $report = [
            'period'            => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'new_registrations' => $newRegistrations,
            'distribution_count'  => $distCount,
            'distribution_amount' => round($distAmount, 2),
            'compliance_rate'     => $complianceRate,
            'security_alerts'     => $securityAlerts,
            'fraud_attempts'      => $fraudAttempts,
            'generated_at'        => now()->toDateTimeString(),
        ];

        $this->emailAdmins('Daily Summary Report', $report);

        Log::info('ReportScheduler: Daily report generated.', $report);

        return $report;
    }

    // ─────────────────────────────────────────────────────────────
    // generateWeeklyReports
    // ─────────────────────────────────────────────────────────────

    public function generateWeeklyReports(): array
    {
        $from = now()->subWeek()->startOfDay();
        $to   = now()->endOfDay();

        // Registrations trend
        $regByDay = Beneficiary::select(
                DB::raw("DATE(created_at) AS day"),
                DB::raw('count(*) as count')
            )
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        // Distributions
        $distTotal  = CashGrantDistribution::whereBetween('distributed_at', [$from, $to])->sum('payout_amount');
        $distCount  = CashGrantDistribution::whereBetween('distributed_at', [$from, $to])->count();

        // Compliance summary
        $compliant    = ComplianceSummaryCache::where('overall_status', 'compliant')->count();
        $nonCompliant = ComplianceSummaryCache::where('overall_status', 'non_compliant')->count();
        $total        = ComplianceSummaryCache::count();

        // User activity
        $topUsers = VerificationActivityLog::whereBetween('timestamp', [$from, $to])
            ->whereNotNull('user_id')
            ->select('user_id', DB::raw('count(*) as count'))
            ->groupBy('user_id')
            ->orderByDesc('count')
            ->limit(5)
            ->with('user:id,name,role')
            ->get()
            ->map(fn ($r) => ['name' => $r->user?->name, 'count' => $r->count]);

        // Security
        $securityEvents = VerificationActivityLog::whereBetween('timestamp', [$from, $to])
            ->where('activity_category', 'security')
            ->count();

        // Fraud
        $fraud = DuplicateDetectionLog::whereBetween('detection_date', [$from, $to])->count();

        // Compare with previous week
        $prevFrom = $from->copy()->subWeek();
        $prevTo   = $to->copy()->subWeek();
        $prevDist = CashGrantDistribution::whereBetween('distributed_at', [$prevFrom, $prevTo])->sum('payout_amount');
        $prevReg  = Beneficiary::whereBetween('created_at', [$prevFrom, $prevTo])->count();
        $currReg  = Beneficiary::whereBetween('created_at', [$from, $to])->count();

        $report = [
            'period'             => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'registrations'      => $currReg,
            'prev_registrations' => $prevReg,
            'reg_trend'          => $prevReg > 0 ? round(($currReg - $prevReg) / $prevReg * 100, 1) : 0,
            'dist_amount'        => round($distTotal, 2),
            'dist_count'         => $distCount,
            'prev_dist'          => round($prevDist, 2),
            'dist_trend'         => $prevDist > 0 ? round(($distTotal - $prevDist) / $prevDist * 100, 1) : 0,
            'compliance_rate'    => $total > 0 ? round($compliant / $total * 100, 2) : 0,
            'non_compliant'      => $nonCompliant,
            'top_users'          => $topUsers,
            'security_events'    => $securityEvents,
            'fraud_detections'   => $fraud,
            'reg_by_day'         => $regByDay,
            'generated_at'       => now()->toDateTimeString(),
        ];

        $this->emailAdmins('Weekly Summary Report', $report);

        Log::info('ReportScheduler: Weekly report generated.', ['period' => $report['period']]);

        return $report;
    }

    // ─────────────────────────────────────────────────────────────
    // generateMonthlyReports
    // ─────────────────────────────────────────────────────────────

    public function generateMonthlyReports(): array
    {
        $from    = now()->subMonth()->startOfMonth();
        $to      = now()->subMonth()->endOfMonth();
        $prevFrom = $from->copy()->subMonth();
        $prevTo   = $prevFrom->copy()->endOfMonth();

        // Beneficiary stats
        $totalBenef  = Beneficiary::count();
        $newBenef    = Beneficiary::whereBetween('created_at', [$from, $to])->count();
        $prevBenef   = Beneficiary::whereBetween('created_at', [$prevFrom, $prevTo])->count();
        $activeBenef = Beneficiary::where('is_active', true)->count();

        // Distributions
        $distAmount  = CashGrantDistribution::whereBetween('distributed_at', [$from, $to])->sum('payout_amount');
        $distCount   = CashGrantDistribution::whereBetween('distributed_at', [$from, $to])->count();
        $prevDist    = CashGrantDistribution::whereBetween('distributed_at', [$prevFrom, $prevTo])->sum('payout_amount');

        // Compliance
        $compliant    = ComplianceSummaryCache::where('overall_status', 'compliant')->count();
        $nonCompliant = ComplianceSummaryCache::where('overall_status', 'non_compliant')->count();
        $totalCache   = ComplianceSummaryCache::count();
        $compRate     = $totalCache > 0 ? round($compliant / $totalCache * 100, 2) : 0;

        // Fraud
        $fraud = DuplicateDetectionLog::whereBetween('detection_date', [$from, $to])->count();

        // Security events
        $security = VerificationActivityLog::whereBetween('timestamp', [$from, $to])
            ->where('activity_category', 'security')
            ->whereIn('severity', ['high', 'critical'])
            ->count();

        // Monthly trend (last 12 months)
        $monthlyTrend = DB::table('beneficiaries')
            ->select(DB::raw("TO_CHAR(created_at, 'YYYY-MM') AS month"), DB::raw('count(*) as count'))
            ->where('created_at', '>=', now()->subYear())
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $report = [
            'period'               => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'total_beneficiaries'  => $totalBenef,
            'active_beneficiaries' => $activeBenef,
            'new_beneficiaries'    => $newBenef,
            'prev_new_benef'       => $prevBenef,
            'benef_growth'         => $prevBenef > 0 ? round(($newBenef - $prevBenef) / $prevBenef * 100, 1) : 0,
            'dist_amount'          => round($distAmount, 2),
            'dist_count'           => $distCount,
            'prev_dist_amount'     => round($prevDist, 2),
            'dist_growth'          => $prevDist > 0 ? round(($distAmount - $prevDist) / $prevDist * 100, 1) : 0,
            'compliance_rate'      => $compRate,
            'non_compliant'        => $nonCompliant,
            'fraud_detections'     => $fraud,
            'security_events'      => $security,
            'monthly_trend'        => $monthlyTrend,
            'generated_at'         => now()->toDateTimeString(),
        ];

        $this->emailAdmins('Monthly Comprehensive Report', $report);

        Log::info('ReportScheduler: Monthly report generated.', ['period' => $report['period']]);

        return $report;
    }

    // ─────────────────────────────────────────────────────────────
    // Private Helpers
    // ─────────────────────────────────────────────────────────────

    private function emailAdmins(string $subject, array $report): void
    {
        $admins = User::where('role', 'Administrator')->whereNotNull('email')->get();

        foreach ($admins as $admin) {
            try {
                // Placeholder — real implementation uses a Mailable
                Log::info("ReportScheduler: Would email '{$subject}' to {$admin->email}.");
            } catch (\Throwable $e) {
                Log::error("ReportScheduler::emailAdmins failed for {$admin->email}: " . $e->getMessage());
            }
        }
    }
}
