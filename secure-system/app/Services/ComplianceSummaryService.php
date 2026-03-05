<?php

namespace App\Services;

use App\Models\Beneficiary;
use App\Models\ComplianceRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ComplianceSummaryService
{
    private const MIN_EDUCATION_ATTENDANCE = 85.0;
    private const MIN_FDS_SESSIONS        = 2;

    // ─────────────────────────────────────────────────────────────────────────
    // calculateOverallCompliance
    // ─────────────────────────────────────────────────────────────────────────

    public function calculateOverallCompliance(int $beneficiaryId): array
    {
        $period      = Carbon::now()->format('Y-m');
        $beneficiary = Beneficiary::with('familyMembers')->find($beneficiaryId);

        if (! $beneficiary) {
            return ['error' => 'Beneficiary not found.'];
        }

        // ── Education ────────────────────────────────────────────────────────
        $schoolAgeMembers = $beneficiary->familyMembers->filter(fn($m) => $m->is_school_age);
        $eduTotal         = $schoolAgeMembers->count();
        $eduCompliant     = 0;

        if ($eduTotal > 0) {
            foreach ($schoolAgeMembers as $member) {
                $record = ComplianceRecord::where('family_member_id', $member->id)
                    ->where('compliance_type', 'education')
                    ->where('compliance_period', $period)
                    ->latest()
                    ->first();

                if ($record && (float) $record->attendance_percentage >= self::MIN_EDUCATION_ATTENDANCE) {
                    $eduCompliant++;
                }
            }
        }

        $educationPct = $eduTotal > 0 ? round(($eduCompliant / $eduTotal) * 100, 2) : 100.00;

        // ── Health ───────────────────────────────────────────────────────────
        $healthMembers  = $beneficiary->familyMembers->filter(fn($m) => $m->needs_health_monitoring);
        $healthTotal    = $healthMembers->count();
        $healthCompliant = 0;

        if ($healthTotal > 0) {
            foreach ($healthMembers as $member) {
                $record = ComplianceRecord::where('family_member_id', $member->id)
                    ->where('compliance_type', 'health')
                    ->where('compliance_period', $period)
                    ->latest()
                    ->first();

                if ($record && $record->is_compliant) {
                    $healthCompliant++;
                }
            }
        }

        $healthPct = $healthTotal > 0 ? round(($healthCompliant / $healthTotal) * 100, 2) : 100.00;

        // ── FDS ──────────────────────────────────────────────────────────────
        $fdsAttended = ComplianceRecord::whereHas('familyMember', function ($q) use ($beneficiaryId) {
                $q->where('beneficiary_id', $beneficiaryId);
            })
            ->where('compliance_type', 'fds')
            ->where('compliance_period', $period)
            ->where('fds_attendance', true)
            ->count('id');

        // Deduplicate: FDS sessions are per-member, so use distinct family_member_id as session proxy
        $fdsDistinct = ComplianceRecord::whereHas('familyMember', function ($q) use ($beneficiaryId) {
                $q->where('beneficiary_id', $beneficiaryId);
            })
            ->where('compliance_type', 'fds')
            ->where('compliance_period', $period)
            ->where('fds_attendance', true)
            ->distinct('family_member_id')
            ->count('family_member_id');

        $fdsPct = min(round(($fdsDistinct / self::MIN_FDS_SESSIONS) * 100, 2), 100.00);

        // ── Overall Status ───────────────────────────────────────────────────
        $allAbove85 = $educationPct >= 85 && $healthPct >= 85 && $fdsPct >= 85;
        $anyAbove85 = $educationPct >= 85 || $healthPct >= 85 || $fdsPct >= 85;

        $overallStatus = match (true) {
            $allAbove85  => 'compliant',
            $anyAbove85  => 'partial',
            default      => 'non_compliant',
        };

        // ── Missing Requirements ─────────────────────────────────────────────
        $missing = $this->identifyMissingRequirements($beneficiaryId, $period, $beneficiary);

        // ── Upsert Cache ─────────────────────────────────────────────────────
        $summary = [
            'education_compliance_percentage' => $educationPct,
            'health_compliance_percentage'    => $healthPct,
            'fds_compliance_percentage'       => $fdsPct,
            'overall_compliance_status'       => $overallStatus,
            'missing_requirements'            => json_encode($missing),
            'last_updated_at'                 => now(),
            'cache_validity'                  => now()->addHours(24),
        ];

        try {
            DB::table('compliance_summary_cache')
                ->updateOrInsert(
                    ['beneficiary_id' => $beneficiaryId],
                    $summary
                );
        } catch (\Throwable $e) {
            Log::warning("ComplianceSummaryService: cache upsert failed for #{$beneficiaryId}: " . $e->getMessage());
        }

        return [
            'beneficiary_id'                  => $beneficiaryId,
            'compliance_period'               => $period,
            'education_compliance_percentage' => $educationPct,
            'health_compliance_percentage'    => $healthPct,
            'fds_compliance_percentage'       => $fdsPct,
            'fds_sessions_attended'           => $fdsDistinct,
            'fds_sessions_required'           => self::MIN_FDS_SESSIONS,
            'overall_compliance_status'       => $overallStatus,
            'missing_requirements'            => $missing,
            'last_updated_at'                 => now()->toIso8601String(),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // identifyMissingRequirements
    // ─────────────────────────────────────────────────────────────────────────

    public function identifyMissingRequirements(
        int    $beneficiaryId,
        string $period = null,
        ?Beneficiary $beneficiary = null
    ): array {
        $period      ??= Carbon::now()->format('Y-m');
        $beneficiary ??= Beneficiary::with('familyMembers')->find($beneficiaryId);

        if (! $beneficiary) {
            return [];
        }

        $missing = [];

        // Education
        $schoolAgeMembers = $beneficiary->familyMembers->filter(fn($m) => $m->is_school_age);
        foreach ($schoolAgeMembers as $member) {
            $record = ComplianceRecord::where('family_member_id', $member->id)
                ->where('compliance_type', 'education')
                ->where('compliance_period', $period)
                ->latest()
                ->first();

            if (! $record) {
                $missing[] = "Education record missing for {$member->full_name} (Period: {$period})";
            } elseif ((float) $record->attendance_percentage < self::MIN_EDUCATION_ATTENDANCE) {
                $missing[] = sprintf(
                    'School attendance below 85%% for %s (%.1f%%, Period: %s)',
                    $member->full_name,
                    (float) $record->attendance_percentage,
                    $period
                );
            }
        }

        // Health
        $healthMembers = $beneficiary->familyMembers->filter(fn($m) => $m->needs_health_monitoring);
        foreach ($healthMembers as $member) {
            $record = ComplianceRecord::where('family_member_id', $member->id)
                ->where('compliance_type', 'health')
                ->where('compliance_period', $period)
                ->latest()
                ->first();

            if (! $record) {
                $missing[] = "Health checkup missing for {$member->full_name} (Period: {$period})";
            } elseif (! $record->is_compliant) {
                $missing[] = "Health compliance not met for {$member->full_name} (Period: {$period})";
            }
        }

        // FDS
        $fdsDistinct = ComplianceRecord::whereHas('familyMember', function ($q) use ($beneficiaryId) {
                $q->where('beneficiary_id', $beneficiaryId);
            })
            ->where('compliance_type', 'fds')
            ->where('compliance_period', $period)
            ->where('fds_attendance', true)
            ->distinct('family_member_id')
            ->count('family_member_id');

        if ($fdsDistinct < self::MIN_FDS_SESSIONS) {
            $missing[] = sprintf(
                'FDS attendance below minimum (%d of %d sessions, Period: %s)',
                $fdsDistinct,
                self::MIN_FDS_SESSIONS,
                $period
            );
        }

        return $missing;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // getComplianceTrend
    // ─────────────────────────────────────────────────────────────────────────

    public function getComplianceTrend(int $beneficiaryId, int $months = 6): array
    {
        $beneficiary = Beneficiary::with('familyMembers')->find($beneficiaryId);
        if (! $beneficiary) {
            return [];
        }

        $trend = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $period = Carbon::now()->subMonths($i)->format('Y-m');
            $label  = Carbon::now()->subMonths($i)->format('M Y');

            // Education
            $schoolAgeMembers = $beneficiary->familyMembers->filter(fn($m) => $m->is_school_age);
            $eduTotal     = $schoolAgeMembers->count();
            $eduCompliant = 0;

            foreach ($schoolAgeMembers as $member) {
                $record = ComplianceRecord::where('family_member_id', $member->id)
                    ->where('compliance_type', 'education')
                    ->where('compliance_period', $period)
                    ->latest()->first();
                if ($record && (float) $record->attendance_percentage >= 85) {
                    $eduCompliant++;
                }
            }

            // Health
            $healthMembers   = $beneficiary->familyMembers->filter(fn($m) => $m->needs_health_monitoring);
            $healthTotal     = $healthMembers->count();
            $healthCompliant = 0;

            foreach ($healthMembers as $member) {
                $record = ComplianceRecord::where('family_member_id', $member->id)
                    ->where('compliance_type', 'health')
                    ->where('compliance_period', $period)
                    ->latest()->first();
                if ($record && $record->is_compliant) {
                    $healthCompliant++;
                }
            }

            // FDS
            $fds = ComplianceRecord::whereHas('familyMember', fn($q) => $q->where('beneficiary_id', $beneficiaryId))
                ->where('compliance_type', 'fds')
                ->where('compliance_period', $period)
                ->where('fds_attendance', true)
                ->distinct('family_member_id')
                ->count('family_member_id');

            $eduPct    = $eduTotal > 0 ? round(($eduCompliant / $eduTotal) * 100, 1) : 100.0;
            $healthPct = $healthTotal > 0 ? round(($healthCompliant / $healthTotal) * 100, 1) : 100.0;
            $fdsPct    = min(round(($fds / self::MIN_FDS_SESSIONS) * 100, 1), 100.0);
            $overall   = round(($eduPct + $healthPct + $fdsPct) / 3, 1);

            $trend[$period] = [
                'period'    => $period,
                'label'     => $label,
                'education' => $eduPct,
                'health'    => $healthPct,
                'fds'       => $fdsPct,
                'overall'   => $overall,
            ];
        }

        return $trend;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // getBeneficiariesAtRisk
    // ─────────────────────────────────────────────────────────────────────────

    public function getBeneficiariesAtRisk(): array
    {
        $currentPeriod  = Carbon::now()->format('Y-m');
        $previousPeriod = Carbon::now()->subMonth()->format('Y-m');

        // Get all non-compliant / partial beneficiaries from cache
        $atRiskCache = DB::table('compliance_summary_cache')
            ->whereIn('overall_compliance_status', ['non_compliant', 'partial'])
            ->get();

        $results = [];

        foreach ($atRiskCache as $cache) {
            $beneficiaryId = $cache->beneficiary_id;

            // Check if they were compliant last period
            $wasPreviouslyCompliant = DB::table('compliance_summary_cache')
                ->where('beneficiary_id', $beneficiaryId)
                ->where('overall_compliance_status', 'compliant')
                ->exists();

            // Decode missing requirements
            $missing = json_decode($cache->missing_requirements ?? '[]', true) ?? [];

            $beneficiary = Beneficiary::with('familyMembers')
                ->select('id', 'bin', 'family_head_name', 'municipality', 'barangay')
                ->find($beneficiaryId);

            if (! $beneficiary) continue;

            $results[] = [
                'beneficiary_id'           => $beneficiaryId,
                'bin'                      => $beneficiary->bin,
                'family_head_name'         => $beneficiary->family_head_name,
                'municipality'             => $beneficiary->municipality,
                'barangay'                 => $beneficiary->barangay,
                'overall_status'           => $cache->overall_compliance_status,
                'education_pct'            => (float) $cache->education_compliance_percentage,
                'health_pct'               => (float) $cache->health_compliance_percentage,
                'fds_pct'                  => (float) $cache->fds_compliance_percentage,
                'was_previously_compliant' => $wasPreviouslyCompliant,
                'missing_requirements'     => $missing,
                'last_updated_at'          => $cache->last_updated_at,
            ];
        }

        // Sort: non_compliant first, then partial
        usort($results, fn($a, $b) => strcmp($a['overall_status'], $b['overall_status']));

        return $results;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // getFromCache — Returns cached summary for a beneficiary
    // ─────────────────────────────────────────────────────────────────────────

    public function getFromCache(int $beneficiaryId): ?array
    {
        $cache = DB::table('compliance_summary_cache')
            ->where('beneficiary_id', $beneficiaryId)
            ->first();

        if (! $cache) {
            return null;
        }

        return [
            'beneficiary_id'                  => $cache->beneficiary_id,
            'education_compliance_percentage' => (float) $cache->education_compliance_percentage,
            'health_compliance_percentage'    => (float) $cache->health_compliance_percentage,
            'fds_compliance_percentage'       => (float) $cache->fds_compliance_percentage,
            'overall_compliance_status'       => $cache->overall_compliance_status,
            'missing_requirements'            => json_decode($cache->missing_requirements ?? '[]', true) ?? [],
            'last_updated_at'                 => $cache->last_updated_at,
            'cache_validity'                  => $cache->cache_validity,
        ];
    }
}
