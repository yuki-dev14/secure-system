<?php

namespace App\Http\Controllers;

use App\Models\Beneficiary;
use App\Models\ComplianceRecord;
use App\Services\ComplianceSummaryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ComplianceDashboardController extends Controller
{
    public function __construct(
        private ComplianceSummaryService $summaryService
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // index — GET /dashboard/compliance
    // ─────────────────────────────────────────────────────────────────────────

    public function index()
    {
        $period = Carbon::now()->format('Y-m');

        // ── Key metrics from compliance_summary_cache ────────────────────────
        $totalBeneficiaries = Beneficiary::count();
        $cacheStats = DB::table('compliance_summary_cache')
            ->selectRaw("
                COUNT(*) as cached_total,
                SUM(CASE WHEN overall_compliance_status = 'compliant'     THEN 1 ELSE 0 END) as compliant_count,
                SUM(CASE WHEN overall_compliance_status = 'partial'       THEN 1 ELSE 0 END) as partial_count,
                SUM(CASE WHEN overall_compliance_status = 'non_compliant' THEN 1 ELSE 0 END) as non_compliant_count,
                ROUND(AVG(education_compliance_percentage), 1) as avg_education,
                ROUND(AVG(health_compliance_percentage),    1) as avg_health,
                ROUND(AVG(fds_compliance_percentage),       1) as avg_fds
            ")
            ->first();

        // Pending verifications (records with verified_at = null, current period)
        $pendingVerifications = ComplianceRecord::whereNull('verified_at')
            ->where('compliance_period', $period)
            ->count();

        // Safe division
        $cached = max((int) ($cacheStats->cached_total ?? 0), 1);
        $compliantPct    = round(((int)($cacheStats->compliant_count    ?? 0) / $cached) * 100, 1);
        $partialPct      = round(((int)($cacheStats->partial_count      ?? 0) / $cached) * 100, 1);
        $nonCompliantPct = round(((int)($cacheStats->non_compliant_count ?? 0) / $cached) * 100, 1);

        // ── Compliance by type (averages from cache) ─────────────────────────
        $byType = [
            'education' => (float)($cacheStats->avg_education ?? 0),
            'health'    => (float)($cacheStats->avg_health    ?? 0),
            'fds'       => (float)($cacheStats->avg_fds       ?? 0),
        ];

        // ── Compliance trend (last 6 months) — aggregate from cache snapshots
        $trend = $this->buildAggregateTrend(6);

        // ── At-risk beneficiaries ─────────────────────────────────────────────
        $atRisk = $this->summaryService->getBeneficiariesAtRisk();

        // ── Compliance by location ────────────────────────────────────────────
        $byLocation = $this->buildLocationData();

        return Inertia::render('Compliance/Dashboard', [
            'metrics' => [
                'total_beneficiaries'  => $totalBeneficiaries,
                'compliant_count'      => (int)($cacheStats->compliant_count    ?? 0),
                'partial_count'        => (int)($cacheStats->partial_count      ?? 0),
                'non_compliant_count'  => (int)($cacheStats->non_compliant_count ?? 0),
                'compliant_pct'        => $compliantPct,
                'partial_pct'          => $partialPct,
                'non_compliant_pct'    => $nonCompliantPct,
                'pending_verifications'=> $pendingVerifications,
                'by_type'              => $byType,
            ],
            'trend'       => $trend,
            'atRisk'      => array_slice($atRisk, 0, 50), // cap for initial load
            'byLocation'  => $byLocation,
            'currentPeriod' => $period,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // complianceByLocation — GET /dashboard/compliance/location
    // ─────────────────────────────────────────────────────────────────────────

    public function complianceByLocation(Request $request)
    {
        $location = $request->query('location');
        $data = $this->buildLocationData($location);

        return response()->json([
            'success'  => true,
            'location' => $location,
            'data'     => $data,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // complianceTrends — GET /dashboard/compliance/trends
    // ─────────────────────────────────────────────────────────────────────────

    public function complianceTrends(Request $request)
    {
        $months = (int)($request->query('months', 6));
        $months = min(max($months, 1), 24); // clamp 1–24

        $trend = $this->buildAggregateTrend($months);

        return response()->json([
            'success' => true,
            'months'  => $months,
            'trend'   => array_values($trend),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // atRiskBeneficiaries — GET /dashboard/compliance/at-risk
    // ─────────────────────────────────────────────────────────────────────────

    public function atRiskBeneficiaries(Request $request)
    {
        $atRisk = $this->summaryService->getBeneficiariesAtRisk();

        // Calculate an urgency score (0–100, higher = worse)
        foreach ($atRisk as &$b) {
            $score = 0;
            if ($b['overall_status'] === 'non_compliant') $score += 50;
            if ($b['education_pct'] < 85) $score += (85 - $b['education_pct']) * 0.2;
            if ($b['health_pct']    < 85) $score += (85 - $b['health_pct'])    * 0.2;
            if ($b['fds_pct']       < 85) $score += (85 - $b['fds_pct'])       * 0.2;
            if ($b['was_previously_compliant']) $score += 10; // recently slipped
            $b['urgency_score'] = min(round($score, 1), 100);
        }
        unset($b);

        usort($atRisk, fn($a, $b) => $b['urgency_score'] <=> $a['urgency_score']);

        return response()->json([
            'success' => true,
            'total'   => count($atRisk),
            'data'    => $atRisk,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // complianceDetails — GET /dashboard/compliance/{beneficiaryId}
    // ─────────────────────────────────────────────────────────────────────────

    public function complianceDetails(Request $request, int $beneficiaryId)
    {
        $beneficiary = Beneficiary::with('familyMembers')->findOrFail($beneficiaryId);

        $memberIds = $beneficiary->familyMembers->pluck('id');

        // All compliance records
        $records = ComplianceRecord::with(['familyMember', 'verifiedBy:id,name,role'])
            ->whereIn('family_member_id', $memberIds)
            ->orderByDesc('compliance_period')
            ->get();

        // Current period summary
        $summary = $this->summaryService->calculateOverallCompliance($beneficiaryId);

        // Missing requirements
        $missing = $this->summaryService->identifyMissingRequirements($beneficiaryId);

        // Trend (6 months)
        $trend = $this->summaryService->getComplianceTrend($beneficiaryId, 6);

        // Verification stats
        $verifiedCount   = $records->whereNotNull('verified_at')->count();
        $unverifiedCount = $records->whereNull('verified_at')->count();

        return response()->json([
            'success'     => true,
            'beneficiary' => [
                'id'               => $beneficiary->id,
                'bin'              => $beneficiary->bin,
                'family_head_name' => $beneficiary->family_head_name,
                'municipality'     => $beneficiary->municipality,
                'barangay'         => $beneficiary->barangay ?? '',
            ],
            'summary'        => $summary,
            'missing'        => $missing,
            'trend'          => array_values($trend),
            'records_total'  => $records->count(),
            'verified_count' => $verifiedCount,
            'pending_count'  => $unverifiedCount,
            'records'        => $records->map(fn($r) => [
                'id'                    => $r->id,
                'family_member_name'    => $r->familyMember?->full_name,
                'compliance_type'       => $r->compliance_type,
                'compliance_period'     => $r->compliance_period,
                'is_compliant'          => $r->is_compliant,
                'verified_at'           => $r->verified_at?->toIso8601String(),
                'verified_by'           => $r->verifiedBy?->name,
                'attendance_percentage' => $r->attendance_percentage,
                'vaccination_status'    => $r->vaccination_status,
                'fds_attendance'        => $r->fds_attendance,
            ])->values(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build aggregate trend by iterating months and averaging cache values
     * for records that exist within that period's compliance_period.
     */
    private function buildAggregateTrend(int $months): array
    {
        $trend = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $period = Carbon::now()->subMonths($i)->format('Y-m');
            $label  = Carbon::now()->subMonths($i)->format('M Y');

            // Average compliance percentages from compliance_records for that period
            $row = DB::table('compliance_records')
                ->select(
                    DB::raw("ROUND(AVG(CASE WHEN compliance_type = 'education' AND is_compliant = true THEN 100.0 ELSE 0 END), 1) AS edu"),
                    DB::raw("ROUND(AVG(CASE WHEN compliance_type = 'health'    AND is_compliant = true THEN 100.0 ELSE 0 END), 1) AS hlth"),
                    DB::raw("ROUND(AVG(CASE WHEN compliance_type = 'fds'       AND fds_attendance = true THEN 100.0 ELSE 0 END), 1) AS fds"),
                    DB::raw("ROUND(AVG(CASE WHEN is_compliant = true THEN 100.0 ELSE 0 END), 1) AS overall")
                )
                ->where('compliance_period', $period)
                ->first();

            $trend[$period] = [
                'period'    => $period,
                'label'     => $label,
                'education' => (float)($row->edu     ?? 0),
                'health'    => (float)($row->hlth    ?? 0),
                'fds'       => (float)($row->fds     ?? 0),
                'overall'   => (float)($row->overall ?? 0),
            ];
        }
        return $trend;
    }

    /**
     * Group beneficiaries by municipality (and optionally barangay) and
     * calculate average compliance per location from cache.
     */
    private function buildLocationData(?string $filterLocation = null): array
    {
        $query = DB::table('compliance_summary_cache as c')
            ->join('beneficiaries as b', 'b.id', '=', 'c.beneficiary_id')
            ->select(
                'b.municipality',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN c.overall_compliance_status = 'compliant' THEN 1 ELSE 0 END) as compliant"),
                DB::raw("SUM(CASE WHEN c.overall_compliance_status = 'partial' THEN 1 ELSE 0 END) as partial"),
                DB::raw("SUM(CASE WHEN c.overall_compliance_status = 'non_compliant' THEN 1 ELSE 0 END) as non_compliant"),
                DB::raw('ROUND(AVG(c.education_compliance_percentage), 1) as avg_education'),
                DB::raw('ROUND(AVG(c.health_compliance_percentage),    1) as avg_health'),
                DB::raw('ROUND(AVG(c.fds_compliance_percentage),       1) as avg_fds'),
                DB::raw("ROUND(
                    AVG(
                        (c.education_compliance_percentage + c.health_compliance_percentage + c.fds_compliance_percentage) / 3.0
                    ), 1
                ) as avg_overall")
            )
            ->groupBy('b.municipality');

        if ($filterLocation) {
            $query->where('b.municipality', $filterLocation);
        }

        $rows = $query->orderBy('avg_overall')->get();

        return $rows->map(fn($r) => [
            'municipality'    => $r->municipality ?? '(Unknown)',
            'total'           => (int)$r->total,
            'compliant'       => (int)$r->compliant,
            'partial'         => (int)$r->partial,
            'non_compliant'   => (int)$r->non_compliant,
            'compliant_pct'   => $r->total > 0 ? round(($r->compliant / $r->total) * 100, 1) : 0,
            'avg_education'   => (float)($r->avg_education ?? 0),
            'avg_health'      => (float)($r->avg_health    ?? 0),
            'avg_fds'         => (float)($r->avg_fds       ?? 0),
            'avg_overall'     => (float)($r->avg_overall   ?? 0),
        ])->toArray();
    }
}
