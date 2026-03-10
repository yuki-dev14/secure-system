<?php

namespace App\Http\Controllers;

use App\Models\Beneficiary;
use App\Models\CashGrantDistribution;
use App\Models\ComplianceSummaryCache;
use App\Models\DuplicateDetectionLog;
use App\Models\QrCode;
use App\Models\User;
use App\Models\VerificationActivityLog;
use App\Services\AuditLogService;
use App\Services\DataExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function __construct(
        private AuditLogService   $auditSvc,
        private DataExportService $exportSvc,
    ) {}

    // ─────────────────────────────────────────────────────────────
    // dashboard — GET /reports/dashboard
    // ─────────────────────────────────────────────────────────────

    public function dashboard()
    {
        $users   = User::orderBy('name')->get(['id', 'name', 'role', 'office_location']);
        $offices = User::whereNotNull('office_location')
                       ->distinct()
                       ->pluck('office_location')
                       ->sort()
                       ->values();

        return Inertia::render('Reports/Dashboard', [
            'users'   => $users,
            'offices' => $offices,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // beneficiaryStatistics — POST /reports/beneficiary-statistics
    // ─────────────────────────────────────────────────────────────

    public function beneficiaryStatistics(Request $request)
    {
        $request->validate([
            'date_from'    => ['nullable', 'date'],
            'date_to'      => ['nullable', 'date'],
            'office'       => ['nullable', 'string'],
            'municipality' => ['nullable', 'string'],
            'status'       => ['nullable', 'in:active,inactive'],
        ]);

        $cacheKey = 'report.beneficiary_stats.' . md5(json_encode($request->all()));

        $stats = Cache::remember($cacheKey, 3600, function () use ($request) {

            $from = $request->filled('date_from') ? Carbon::parse($request->date_from)->startOfDay() : null;
            $to   = $request->filled('date_to')   ? Carbon::parse($request->date_to)->endOfDay()     : null;

            $base = Beneficiary::query();

            if ($request->filled('office')) {
                $base->whereHas('registeredBy', fn ($q) => $q->where('office_location', $request->office));
            }
            if ($request->filled('municipality')) {
                $base->where('municipality', $request->municipality);
            }
            if ($request->filled('status')) {
                $base->where('is_active', $request->status === 'active');
            }

            $total    = (clone $base)->count();
            $active   = (clone $base)->where('is_active', true)->count();
            $inactive = $total - $active;

            // Newly registered in period
            $newlyRegistered = 0;
            if ($from || $to) {
                $nq = clone $base;
                if ($from) $nq->where('created_at', '>=', $from);
                if ($to)   $nq->where('created_at', '<=', $to);
                $newlyRegistered = $nq->count();
            }

            // Averages
            $avgHousehold = (clone $base)->avg('household_size') ?? 0;
            $avgIncome    = (clone $base)->avg('annual_income') ?? 0;

            // Civil status distribution
            $civilStatus = (clone $base)
                ->select('civil_status', DB::raw('count(*) as count'))
                ->groupBy('civil_status')
                ->get()
                ->pluck('count', 'civil_status');

            // Income distribution buckets
            $incomeDistribution = [
                'under_10k'   => (clone $base)->where('annual_income', '<', 10000)->count(),
                '10k_20k'     => (clone $base)->whereBetween('annual_income', [10000, 19999])->count(),
                '20k_30k'     => (clone $base)->whereBetween('annual_income', [20000, 29999])->count(),
                '30k_50k'     => (clone $base)->whereBetween('annual_income', [30000, 49999])->count(),
                '50k_100k'    => (clone $base)->whereBetween('annual_income', [50000, 99999])->count(),
                'over_100k'   => (clone $base)->where('annual_income', '>=', 100000)->count(),
            ];

            // By municipality
            $byMunicipality = (clone $base)
                ->select('municipality', DB::raw('count(*) as count'))
                ->groupBy('municipality')
                ->orderByDesc('count')
                ->get();

            // By barangay (top 20)
            $byBarangay = (clone $base)
                ->select('barangay', 'municipality', DB::raw('count(*) as count'))
                ->groupBy('barangay', 'municipality')
                ->orderByDesc('count')
                ->limit(20)
                ->get();

            // Registrations over time (monthly for last 12 months)
            $registrationsOverTime = Beneficiary::select(
                    DB::raw("TO_CHAR(created_at, 'YYYY-MM') AS month"),
                    DB::raw('count(*) as count')
                )
                ->where('created_at', '>=', now()->subYear())
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->map(fn ($r) => ['month' => $r->month, 'count' => $r->count]);

            // By office (via registered_by_user_id → users.office_location)
            $byOffice = DB::table('beneficiaries')
                ->join('users', 'beneficiaries.registered_by_user_id', '=', 'users.id')
                ->select('users.office_location as office', DB::raw('count(*) as count'))
                ->whereNotNull('users.office_location')
                ->groupBy('users.office_location')
                ->orderByDesc('count')
                ->get();

            return [
                'total'                  => $total,
                'active'                 => $active,
                'inactive'               => $inactive,
                'active_percentage'      => $total > 0 ? round($active / $total * 100, 2) : 0,
                'inactive_percentage'    => $total > 0 ? round($inactive / $total * 100, 2) : 0,
                'newly_registered'       => $newlyRegistered,
                'avg_household_size'     => round($avgHousehold, 2),
                'avg_annual_income'      => round($avgIncome, 2),
                'civil_status'           => $civilStatus,
                'income_distribution'    => $incomeDistribution,
                'by_municipality'        => $byMunicipality,
                'by_barangay'            => $byBarangay,
                'registrations_over_time'=> $registrationsOverTime,
                'by_office'              => $byOffice,
                'generated_at'           => now()->toDateTimeString(),
            ];
        });

        return response()->json(['success' => true, 'data' => $stats]);
    }

    // ─────────────────────────────────────────────────────────────
    // complianceReport — POST /reports/compliance
    // ─────────────────────────────────────────────────────────────

    public function complianceReport(Request $request)
    {
        $request->validate([
            'date_from'       => ['nullable', 'date'],
            'date_to'         => ['nullable', 'date'],
            'office'          => ['nullable', 'string'],
            'compliance_type' => ['nullable', 'in:education,health,fds,all'],
            'status'          => ['nullable', 'in:compliant,non_compliant,partial'],
        ]);

        $cacheKey = 'report.compliance.' . md5(json_encode($request->all()));

        $data = Cache::remember($cacheKey, 300, function () use ($request) {

            $from = $request->filled('date_from') ? Carbon::parse($request->date_from)->startOfDay() : now()->subMonths(3);
            $to   = $request->filled('date_to')   ? Carbon::parse($request->date_to)->endOfDay()     : now()->endOfDay();

            // Base compliance summary query
            $base = ComplianceSummaryCache::query()
                ->with('beneficiary:id,bin,family_head_name,municipality,barangay,is_active');

            if ($request->filled('office')) {
                $base->whereHas('beneficiary.registeredBy', fn ($q) => $q->where('office_location', $request->office));
            }
            if ($request->filled('status')) {
                $statusMap = ['compliant' => 'compliant', 'non_compliant' => 'non_compliant', 'partial' => 'partial'];
                $base->where('overall_status', $statusMap[$request->status] ?? $request->status);
            }

            $allSummaries = (clone $base)->get();
            $total = $allSummaries->count();

            $compliantCount    = $allSummaries->where('overall_status', 'compliant')->count();
            $nonCompliantCount = $allSummaries->where('overall_status', 'non_compliant')->count();
            $partialCount      = $allSummaries->where('overall_status', 'partial')->count();

            $overallRate    = $total > 0 ? round($compliantCount / $total * 100, 2) : 0;
            $educationRate  = $total > 0 ? round($allSummaries->avg('education_rate') ?? 0, 2) : 0;
            $healthRate     = $total > 0 ? round($allSummaries->avg('health_rate') ?? 0, 2) : 0;
            $fdsRate        = $total > 0 ? round($allSummaries->avg('fds_rate') ?? 0, 2) : 0;

            // Non-compliant beneficiaries (top 50)
            $nonCompliant = (clone $base)
                ->where('overall_status', 'non_compliant')
                ->limit(50)
                ->get()
                ->map(fn ($c) => [
                    'id'               => $c->beneficiary_id,
                    'bin'              => $c->beneficiary?->bin,
                    'name'             => $c->beneficiary?->family_head_name,
                    'municipality'     => $c->beneficiary?->municipality,
                    'education_rate'   => $c->education_rate,
                    'health_rate'      => $c->health_rate,
                    'fds_rate'         => $c->fds_rate,
                    'overall_status'   => $c->overall_status,
                    'last_updated'     => $c->updated_at?->toDateString(),
                ]);

            // At-risk: partial compliance, trending downward
            $atRisk = (clone $base)
                ->whereIn('overall_status', ['partial', 'non_compliant'])
                ->limit(30)
                ->get()
                ->map(fn ($c) => [
                    'id'             => $c->beneficiary_id,
                    'bin'            => $c->beneficiary?->bin,
                    'name'           => $c->beneficiary?->family_head_name,
                    'municipality'   => $c->beneficiary?->municipality,
                    'overall_status' => $c->overall_status,
                    'education_rate' => $c->education_rate,
                    'health_rate'    => $c->health_rate,
                    'fds_rate'       => $c->fds_rate,
                ]);

            // Compliance trend by month (last 12 months) from the cache updated_at
            $trend = ComplianceSummaryCache::select(
                    DB::raw("TO_CHAR(updated_at, 'YYYY-MM') AS month"),
                    DB::raw('AVG(education_rate) as education'),
                    DB::raw('AVG(health_rate) as health'),
                    DB::raw('AVG(fds_rate) as fds')
                )
                ->where('updated_at', '>=', now()->subYear())
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->map(fn ($r) => [
                    'month'     => $r->month,
                    'education' => round($r->education ?? 0, 2),
                    'health'    => round($r->health ?? 0, 2),
                    'fds'       => round($r->fds ?? 0, 2),
                ]);

            // By municipality
            $byLocation = DB::table('compliance_summary_caches as csc')
                ->join('beneficiaries as b', 'b.id', '=', 'csc.beneficiary_id')
                ->select(
                    'b.municipality',
                    DB::raw('count(*) as total'),
                    DB::raw("SUM(CASE WHEN csc.overall_status = 'compliant' THEN 1 ELSE 0 END) as compliant"),
                    DB::raw("SUM(CASE WHEN csc.overall_status = 'non_compliant' THEN 1 ELSE 0 END) as non_compliant"),
                    DB::raw("SUM(CASE WHEN csc.overall_status = 'partial' THEN 1 ELSE 0 END) as partial")
                )
                ->groupBy('b.municipality')
                ->orderByDesc('total')
                ->get();

            return [
                'total'                => $total,
                'compliant_count'      => $compliantCount,
                'non_compliant_count'  => $nonCompliantCount,
                'partial_count'        => $partialCount,
                'overall_rate'         => $overallRate,
                'education_rate'       => $educationRate,
                'health_rate'          => $healthRate,
                'fds_rate'             => $fdsRate,
                'non_compliant_list'   => $nonCompliant,
                'at_risk'              => $atRisk,
                'trend'                => $trend,
                'by_location'          => $byLocation,
                'period'               => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
                'generated_at'         => now()->toDateTimeString(),
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    // ─────────────────────────────────────────────────────────────
    // distributionReport — POST /reports/distribution
    // ─────────────────────────────────────────────────────────────

    public function distributionReport(Request $request)
    {
        abort_if(
            ! Auth::user()->hasRole(['Administrator', 'Compliance Verifier']),
            403
        );

        $request->validate([
            'date_from'      => ['nullable', 'date'],
            'date_to'        => ['nullable', 'date'],
            'office'         => ['nullable', 'string'],
            'payment_method' => ['nullable', 'string'],
            'distributed_by' => ['nullable', 'integer'],
        ]);

        $cacheKey = 'report.distribution.' . md5(json_encode($request->all()));

        $data = Cache::remember($cacheKey, 300, function () use ($request) {

            $from = $request->filled('date_from') ? Carbon::parse($request->date_from)->startOfDay() : now()->subMonth();
            $to   = $request->filled('date_to')   ? Carbon::parse($request->date_to)->endOfDay()     : now()->endOfDay();

            $base = CashGrantDistribution::whereBetween('distributed_at', [$from, $to]);

            if ($request->filled('payment_method')) {
                $base->where('payment_method', $request->payment_method);
            }
            if ($request->filled('distributed_by')) {
                $base->where('distributed_by_user_id', $request->distributed_by);
            }
            if ($request->filled('office')) {
                $base->whereHas('distributedBy', fn ($q) => $q->where('office_location', $request->office));
            }

            $totalAmount         = (clone $base)->sum('payout_amount');
            $totalCount          = (clone $base)->count();
            $avgPayout           = $totalCount > 0 ? $totalAmount / $totalCount : 0;
            $uniqueBeneficiaries = (clone $base)->distinct('beneficiary_id')->count('beneficiary_id');

            // By payment method
            $byPaymentMethod = (clone $base)
                ->select('payment_method', DB::raw('count(*) as count'), DB::raw('SUM(payout_amount) as total'))
                ->groupBy('payment_method')
                ->get();

            // By officer
            $byOfficer = (clone $base)
                ->select('distributed_by_user_id', DB::raw('count(*) as count'), DB::raw('SUM(payout_amount) as total'))
                ->groupBy('distributed_by_user_id')
                ->with('distributedBy:id,name,office_location')
                ->orderByDesc('total')
                ->limit(20)
                ->get()
                ->map(fn ($r) => [
                    'user_id' => $r->distributed_by_user_id,
                    'name'    => $r->distributedBy?->name ?? 'Unknown',
                    'office'  => $r->distributedBy?->office_location,
                    'count'   => $r->count,
                    'total'   => $r->total,
                ]);

            // By office
            $byOffice = DB::table('cash_grant_distributions as d')
                ->join('users as u', 'u.id', '=', 'd.distributed_by_user_id')
                ->select('u.office_location as office', DB::raw('count(*) as count'), DB::raw('SUM(d.payout_amount) as total'))
                ->whereBetween('d.distributed_at', [$from, $to])
                ->groupBy('u.office_location')
                ->orderByDesc('total')
                ->get();

            // Daily trend (last 30 days or range)
            $byDay = (clone $base)
                ->select(DB::raw("DATE(distributed_at) AS day"), DB::raw('count(*) as count'), DB::raw('SUM(payout_amount) as total'))
                ->groupBy('day')
                ->orderBy('day')
                ->get()
                ->map(fn ($r) => ['day' => $r->day, 'count' => $r->count, 'total' => $r->total]);

            // Reconciliation status
            $reconciled    = (clone $base)->whereNotNull('transaction_reference_number')->count();
            $unreconciled  = $totalCount - $reconciled;

            // Anomaly detection
            $anomalies = [];

            // High amount (>3 std dev)
            $avgA = $avgPayout;
            $stdQuery = (clone $base)->select(DB::raw('STDDEV(payout_amount) as std'))->first();
            $std = $stdQuery->std ?? 0;
            if ($std > 0) {
                $highAmount = (clone $base)
                    ->where('payout_amount', '>', $avgA + 3 * $std)
                    ->with('beneficiary:id,bin,family_head_name', 'distributedBy:id,name')
                    ->limit(10)
                    ->get()
                    ->map(fn ($d) => [
                        'type'        => 'high_amount',
                        'id'          => $d->id,
                        'beneficiary' => $d->beneficiary?->family_head_name,
                        'amount'      => $d->payout_amount,
                        'officer'     => $d->distributedBy?->name,
                        'date'        => $d->distributed_at?->toDateTimeString(),
                    ]);
                $anomalies = array_merge($anomalies, $highAmount->toArray());
            }

            // Multiple distributions same beneficiary in same period
            $multiDist = (clone $base)
                ->select('beneficiary_id', DB::raw('count(*) as cnt'))
                ->groupBy('beneficiary_id')
                ->having('cnt', '>', 1)
                ->with('beneficiary:id,bin,family_head_name')
                ->limit(10)
                ->get()
                ->map(fn ($d) => [
                    'type'        => 'multiple_distributions',
                    'beneficiary' => $d->beneficiary?->family_head_name,
                    'count'       => $d->cnt,
                ]);
            $anomalies = array_merge($anomalies, $multiDist->toArray());

            return [
                'total_amount'           => round($totalAmount, 2),
                'total_count'            => $totalCount,
                'avg_payout'             => round($avgPayout, 2),
                'unique_beneficiaries'   => $uniqueBeneficiaries,
                'by_payment_method'      => $byPaymentMethod,
                'by_officer'             => $byOfficer,
                'by_office'              => $byOffice,
                'by_day'                 => $byDay,
                'reconciled'             => $reconciled,
                'unreconciled'           => $unreconciled,
                'anomalies'              => $anomalies,
                'period'                 => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
                'generated_at'           => now()->toDateTimeString(),
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    // ─────────────────────────────────────────────────────────────
    // fraudDetectionReport — POST /reports/fraud-detection
    // ─────────────────────────────────────────────────────────────

    public function fraudDetectionReport(Request $request)
    {
        abort_if(! Auth::user()->isAdministrator(), 403);

        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date'],
            'office'    => ['nullable', 'string'],
        ]);

        $cacheKey = 'report.fraud.' . md5(json_encode($request->all()));

        $data = Cache::remember($cacheKey, 300, function () use ($request) {

            $from = $request->filled('date_from') ? Carbon::parse($request->date_from)->startOfDay() : now()->subMonth();
            $to   = $request->filled('date_to')   ? Carbon::parse($request->date_to)->endOfDay()     : now()->endOfDay();

            $base = DuplicateDetectionLog::whereBetween('detection_date', [$from, $to]);

            $total             = (clone $base)->count();
            $highConfidence    = (clone $base)->where('confidence_score', '>=', 80)->count();
            $mergedCount       = (clone $base)->where('action_taken', 'merged')->count();
            $flaggedCount      = (clone $base)->where('action_taken', 'flagged')->count();
            $dismissedCount    = (clone $base)->where('action_taken', 'dismissed')->count();
            $pendingCount      = (clone $base)->whereNull('action_taken')->count();

            // By detection type
            $byType = (clone $base)
                ->select('duplicate_type', DB::raw('count(*) as count'))
                ->groupBy('duplicate_type')
                ->get()
                ->pluck('count', 'duplicate_type');

            // By status
            $byStatus = (clone $base)
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->get()
                ->pluck('count', 'status');

            // Timeline (by day)
            $timeline = (clone $base)
                ->select(DB::raw("DATE(detection_date) AS day"), DB::raw('count(*) as count'))
                ->groupBy('day')
                ->orderBy('day')
                ->get()
                ->map(fn ($r) => ['day' => $r->day, 'count' => $r->count]);

            // Action breakdown
            $actionBreakdown = [
                'merged'    => $mergedCount,
                'flagged'   => $flaggedCount,
                'dismissed' => $dismissedCount,
                'pending'   => $pendingCount,
            ];

            // Investigation status
            $investigationStatus = (clone $base)
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->get()
                ->pluck('count', 'status');

            // Recent fraud events (last 50)
            $events = (clone $base)
                ->with([
                    'primaryBeneficiary:id,bin,family_head_name',
                    'duplicateBeneficiary:id,bin,family_head_name',
                    'resolver:id,name',
                ])
                ->orderByDesc('detection_date')
                ->limit(50)
                ->get()
                ->map(fn ($d) => [
                    'id'              => $d->id,
                    'type'            => $d->duplicate_type,
                    'confidence'      => $d->confidence_score,
                    'severity'        => $d->severity,
                    'primary'         => $d->primaryBeneficiary?->family_head_name,
                    'primary_bin'     => $d->primaryBeneficiary?->bin,
                    'duplicate'       => $d->duplicateBeneficiary?->family_head_name,
                    'duplicate_bin'   => $d->duplicateBeneficiary?->bin,
                    'action_taken'    => $d->action_taken,
                    'status'          => $d->status,
                    'detected_at'     => $d->detection_date?->toDateTimeString(),
                    'resolver'        => $d->resolver?->name,
                    'recommendation'  => $d->recommendation,
                ]);

            // Multiple QR scans in short time (< 5 min) from activity logs
            $multiScans = VerificationActivityLog::where('activity_type', 'qr_scan')
                ->whereBetween('timestamp', [$from, $to])
                ->where('status', 'success')
                ->select('beneficiary_id', DB::raw('count(*) as scan_count'))
                ->groupBy('beneficiary_id')
                ->havingRaw('count(*) > 3')
                ->with('beneficiary:id,bin,family_head_name')
                ->get()
                ->map(fn ($r) => [
                    'beneficiary' => $r->beneficiary?->family_head_name,
                    'bin'         => $r->beneficiary?->bin,
                    'scan_count'  => $r->scan_count,
                ]);

            // Failed verifications
            $failedVerifications = VerificationActivityLog::where('activity_type', 'verification')
                ->whereBetween('timestamp', [$from, $to])
                ->where('status', 'failed')
                ->count();

            return [
                'total'                  => $total,
                'high_confidence'        => $highConfidence,
                'failed_verifications'   => $failedVerifications,
                'by_type'                => $byType,
                'by_status'              => $byStatus,
                'action_breakdown'       => $actionBreakdown,
                'investigation_status'   => $investigationStatus,
                'timeline'               => $timeline,
                'events'                 => $events,
                'multiple_scans'         => $multiScans,
                'period'                 => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
                'generated_at'           => now()->toDateTimeString(),
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    // ─────────────────────────────────────────────────────────────
    // userActivityReport — POST /reports/user-activity
    // ─────────────────────────────────────────────────────────────

    public function userActivityReport(Request $request)
    {
        abort_if(! Auth::user()->isAdministrator(), 403);

        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date'],
            'user_id'   => ['nullable', 'integer'],
            'office'    => ['nullable', 'string'],
        ]);

        $cacheKey = 'report.user_activity.' . md5(json_encode($request->all()));

        $data = Cache::remember($cacheKey, 300, function () use ($request) {

            $from = $request->filled('date_from') ? Carbon::parse($request->date_from)->startOfDay() : now()->subMonth();
            $to   = $request->filled('date_to')   ? Carbon::parse($request->date_to)->endOfDay()     : now()->endOfDay();

            $base = VerificationActivityLog::whereBetween('timestamp', [$from, $to]);

            if ($request->filled('user_id')) {
                $base->where('user_id', $request->user_id);
            }
            if ($request->filled('office')) {
                $base->whereHas('user', fn ($q) => $q->where('office_location', $request->office));
            }

            // Activities per user
            $perUser = (clone $base)
                ->whereNotNull('user_id')
                ->select('user_id', 'activity_type', DB::raw('count(*) as count'))
                ->groupBy('user_id', 'activity_type')
                ->with('user:id,name,role,office_location')
                ->get()
                ->groupBy('user_id')
                ->map(function ($activities) {
                    $user = $activities->first()->user;
                    return [
                        'user_id'     => $user?->id,
                        'name'        => $user?->name,
                        'role'        => $user?->role,
                        'office'      => $user?->office_location,
                        'total'       => $activities->sum('count'),
                        'by_type'     => $activities->pluck('count', 'activity_type'),
                    ];
                })
                ->values()
                ->sortByDesc('total')
                ->values();

            // Activity by hour (heatmap data)
            $byHour = (clone $base)
                ->select(
                    DB::raw('EXTRACT(DOW FROM timestamp)::int AS dow'),
                    DB::raw('EXTRACT(HOUR FROM timestamp)::int AS hour'),
                    DB::raw('count(*) as count')
                )
                ->groupBy('dow', 'hour')
                ->get()
                ->map(fn ($r) => ['dow' => $r->dow, 'hour' => $r->hour, 'count' => $r->count]);

            // Activity trend by day
            $byDay = (clone $base)
                ->select(DB::raw("DATE(timestamp) AS day"), DB::raw('count(*) as count'))
                ->groupBy('day')
                ->orderBy('day')
                ->get()
                ->map(fn ($r) => ['day' => $r->day, 'count' => $r->count]);

            // Distribution amounts processed per user
            $distributionsByUser = CashGrantDistribution::whereBetween('distributed_at', [$from, $to])
                ->select('distributed_by_user_id', DB::raw('count(*) as count'), DB::raw('SUM(payout_amount) as total'))
                ->groupBy('distributed_by_user_id')
                ->with('distributedBy:id,name')
                ->get()
                ->map(fn ($r) => [
                    'user_id' => $r->distributed_by_user_id,
                    'name'    => $r->distributedBy?->name,
                    'count'   => $r->count,
                    'total'   => $r->total,
                ]);

            // Registrations per user
            $registrationsByUser = Beneficiary::whereBetween('created_at', [$from, $to])
                ->select('registered_by_user_id', DB::raw('count(*) as count'))
                ->groupBy('registered_by_user_id')
                ->with('registeredBy:id,name')
                ->get()
                ->map(fn ($r) => [
                    'user_id' => $r->registered_by_user_id,
                    'name'    => $r->registeredBy?->name,
                    'count'   => $r->count,
                ]);

            // Inactive users (all users minus those with activity)
            $activeUserIds = (clone $base)->distinct('user_id')->pluck('user_id');
            $inactiveUsers = User::whereNotIn('id', $activeUserIds)
                ->get(['id', 'name', 'role', 'office_location', 'last_login_at']);

            // Unusual patterns: too many or too few
            $allUsers  = $perUser->filter(fn ($u) => $u['total'] > 0);
            $avgTotal  = $allUsers->avg('total') ?? 0;
            $stdDev    = $allUsers->count() > 1
                ? sqrt($allUsers->map(fn ($u) => pow($u['total'] - $avgTotal, 2))->avg())
                : 0;

            $unusualPatterns = $allUsers->filter(fn ($u) =>
                abs($u['total'] - $avgTotal) > ($stdDev * 2)
            )->values();

            // Productivity metrics
            $daysInPeriod  = max(1, $from->diffInDays($to));
            $hoursInPeriod = max(1, $from->diffInHours($to));

            $productivity = $perUser->map(fn ($u) => [
                'user_id'              => $u['user_id'],
                'name'                 => $u['name'],
                'total_activities'     => $u['total'],
                'activities_per_day'   => round($u['total'] / $daysInPeriod, 2),
                'activities_per_hour'  => round($u['total'] / $hoursInPeriod, 2),
            ])->values();

            return [
                'per_user'               => $perUser,
                'by_hour_heatmap'        => $byHour,
                'by_day'                 => $byDay,
                'distributions_by_user'  => $distributionsByUser,
                'registrations_by_user'  => $registrationsByUser,
                'inactive_users'         => $inactiveUsers,
                'unusual_patterns'       => $unusualPatterns,
                'productivity'           => $productivity,
                'period'                 => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
                'generated_at'           => now()->toDateTimeString(),
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    // ─────────────────────────────────────────────────────────────
    // qrCodeReport — POST /reports/qr-code
    // ─────────────────────────────────────────────────────────────

    public function qrCodeReport(Request $request)
    {
        abort_if(
            ! Auth::user()->hasRole(['Administrator', 'Field Officer']),
            403
        );

        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date'],
            'office'    => ['nullable', 'string'],
            'status'    => ['nullable', 'in:active,expired,invalid'],
        ]);

        $cacheKey = 'report.qr.' . md5(json_encode($request->all()));

        $data = Cache::remember($cacheKey, 300, function () use ($request) {

            $from = $request->filled('date_from') ? Carbon::parse($request->date_from)->startOfDay() : now()->subMonth();
            $to   = $request->filled('date_to')   ? Carbon::parse($request->date_to)->endOfDay()     : now()->endOfDay();

            $base = QrCode::whereBetween('generated_at', [$from, $to]);

            if ($request->filled('office')) {
                $base->whereHas('beneficiary.registeredBy', fn ($q) => $q->where('office_location', $request->office));
            }

            $total       = (clone $base)->count();
            $active      = (clone $base)->where('is_valid', true)->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count();
            $expired     = (clone $base)->where('is_valid', true)->where('expires_at', '<=', now())->count();
            $invalid     = (clone $base)->where('is_valid', false)->count();
            $regenerated = (clone $base)->whereNotNull('regenerated_at')->count();

            // By generation date
            $byDate = (clone $base)
                ->select(DB::raw("DATE(generated_at) AS day"), DB::raw('count(*) as count'))
                ->groupBy('day')
                ->orderBy('day')
                ->get()
                ->map(fn ($r) => ['day' => $r->day, 'count' => $r->count]);

            // Regeneration reasons
            $byReason = (clone $base)
                ->whereNotNull('regenerated_reason')
                ->select('regenerated_reason', DB::raw('count(*) as count'))
                ->groupBy('regenerated_reason')
                ->get()
                ->pluck('count', 'regenerated_reason');

            // Average QR lifespan (days between generated_at and expires_at)
            $avgLifespanResult = (clone $base)
                ->whereNotNull('expires_at')
                ->select(DB::raw("AVG(EXTRACT(EPOCH FROM (expires_at - generated_at))/86400) as avg_days"))
                ->first();
            $avgLifespan = round($avgLifespanResult->avg_days ?? 0, 1);

            // Failed scan attempts  
            $failedScans = VerificationActivityLog::where('activity_type', 'qr_scan')
                ->where('status', 'failed')
                ->whereBetween('timestamp', [$from, $to])
                ->count();

            // Frequent scan failures by beneficiary
            $failedScansByBeneficiary = VerificationActivityLog::where('activity_type', 'qr_scan')
                ->where('status', 'failed')
                ->whereBetween('timestamp', [$from, $to])
                ->whereNotNull('beneficiary_id')
                ->select('beneficiary_id', DB::raw('count(*) as count'))
                ->groupBy('beneficiary_id')
                ->having('count', '>', 3)
                ->with('beneficiary:id,bin,family_head_name')
                ->limit(20)
                ->get()
                ->map(fn ($r) => [
                    'beneficiary' => $r->beneficiary?->family_head_name,
                    'bin'         => $r->beneficiary?->bin,
                    'failures'    => $r->count,
                ]);

            // High regeneration by office
            $highRegenByOffice = DB::table('qr_codes as q')
                ->join('beneficiaries as b', 'b.id', '=', 'q.beneficiary_id')
                ->join('users as u', 'u.id', '=', 'b.registered_by_user_id')
                ->select('u.office_location as office', DB::raw('count(*) as regen_count'))
                ->whereNotNull('q.regenerated_at')
                ->whereBetween('q.generated_at', [$from, $to])
                ->groupBy('u.office_location')
                ->orderByDesc('regen_count')
                ->get();

            // QR replacement rate (regenerated / total)
            $replacementRate = $total > 0 ? round($regenerated / $total * 100, 2) : 0;

            return [
                'total'                       => $total,
                'active'                      => $active,
                'expired'                     => $expired,
                'invalid'                     => $invalid,
                'regenerated'                 => $regenerated,
                'replacement_rate'            => $replacementRate,
                'avg_lifespan_days'           => $avgLifespan,
                'failed_scans'                => $failedScans,
                'by_date'                     => $byDate,
                'by_reason'                   => $byReason,
                'failed_scans_by_beneficiary' => $failedScansByBeneficiary,
                'high_regen_by_office'        => $highRegenByOffice,
                'status_breakdown'            => [
                    'active'   => $active,
                    'expired'  => $expired,
                    'invalid'  => $invalid,
                ],
                'period'                      => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
                'generated_at'                => now()->toDateTimeString(),
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    // ─────────────────────────────────────────────────────────────
    // exportReport — POST /reports/export/{type}
    // ─────────────────────────────────────────────────────────────

    public function exportReport(Request $request, string $reportType)
    {
        $request->validate([
            'format'    => ['required', 'in:pdf,excel,csv'],
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date'],
        ]);

        $allowedTypes = ['beneficiary', 'compliance', 'distribution', 'fraud', 'user', 'qr'];
        abort_if(! in_array($reportType, $allowedTypes), 404);

        // Build report data by calling appropriate method
        $reportData = match ($reportType) {
            'beneficiary'  => $this->beneficiaryStatistics($request)->getData(true)['data'],
            'compliance'   => $this->complianceReport($request)->getData(true)['data'],
            'distribution' => $this->distributionReport($request)->getData(true)['data'],
            'fraud'        => $this->fraudDetectionReport($request)->getData(true)['data'],
            'user'         => $this->userActivityReport($request)->getData(true)['data'],
            'qr'           => $this->qrCodeReport($request)->getData(true)['data'],
        };

        $filename = strtolower($reportType) . '_report_' . now()->format('Ymd_His');

        $this->auditSvc->logActivity('export', "Exported {$reportType} report as {$request->format}.", [
            'activity_category' => 'data_access',
            'severity'          => 'medium',
        ]);

        return match ($request->format) {
            'pdf'   => $this->exportSvc->exportToPDF($reportData, $reportType, $request->all(), $filename),
            'excel' => $this->exportSvc->exportToExcel($reportData, $reportType, $request->all(), $filename),
            'csv'   => $this->exportSvc->exportToCSV($reportData, $reportType, $filename),
        };
    }
}
