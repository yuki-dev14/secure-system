<?php

namespace App\Services;

use App\Models\Beneficiary;
use App\Models\ComplianceRecord;
use App\Models\FamilyMember;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ComplianceCheckingService
{
    /** Minimum education attendance percentage */
    private const MIN_EDUCATION_ATTENDANCE = 85.0;

    /** Minimum FDS sessions per period (configurable via DB / .env) */
    private function minFdsSessions(): int
    {
        return (int) config('secure.min_fds_sessions', 2);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // checkOverallCompliance
    // ─────────────────────────────────────────────────────────────────────────

    public function checkOverallCompliance(int $beneficiaryId): array
    {
        $education = $this->checkEducationCompliance($beneficiaryId);
        $health    = $this->checkHealthCompliance($beneficiaryId);
        $fds       = $this->checkFDSCompliance($beneficiaryId);

        $allCompliant = $education['compliant'] && $health['compliant'] && $fds['compliant'];

        return [
            'compliant' => $allCompliant,
            'summary'   => $allCompliant ? 'All compliance requirements met.' : 'One or more compliance requirements not met.',
            'details'   => [
                [
                    'type'      => 'education',
                    'label'     => 'Education',
                    'compliant' => $education['compliant'],
                    'reason'    => $education['reason'] ?? null,
                    'data'      => $education,
                ],
                [
                    'type'      => 'health',
                    'label'     => 'Health',
                    'compliant' => $health['compliant'],
                    'reason'    => $health['reason'] ?? null,
                    'data'      => $health,
                ],
                [
                    'type'      => 'fds',
                    'label'     => 'FDS (Family Development Session)',
                    'compliant' => $fds['compliant'],
                    'reason'    => $fds['reason'] ?? null,
                    'data'      => $fds,
                ],
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // checkEducationCompliance
    // ─────────────────────────────────────────────────────────────────────────

    public function checkEducationCompliance(int $beneficiaryId): array
    {
        $beneficiary   = Beneficiary::with('familyMembers')->find($beneficiaryId);
        $schoolAgeKids = $beneficiary->familyMembers->filter(fn($m) => $m->is_school_age);

        if ($schoolAgeKids->isEmpty()) {
            return [
                'compliant'        => true,
                'reason'           => 'No school-age children in household.',
                'percentage'       => null,
                'missing_members'  => [],
                'members_checked'  => 0,
            ];
        }

        $period         = Carbon::now()->format('Y-m');
        $nonCompliant   = [];
        $percentages    = [];

        foreach ($schoolAgeKids as $member) {
            $record = ComplianceRecord::where('family_member_id', $member->id)
                ->where('compliance_type', 'education')
                ->where('compliance_period', $period)
                ->latest()
                ->first();

            $pct = $record ? (float) $record->attendance_percentage : 0.0;
            $percentages[] = $pct;

            if (! $record || $pct < self::MIN_EDUCATION_ATTENDANCE) {
                $nonCompliant[] = [
                    'member_id'          => $member->id,
                    'full_name'          => $member->full_name,
                    'age'                => $member->age,
                    'attendance_pct'     => $pct,
                    'required_pct'       => self::MIN_EDUCATION_ATTENDANCE,
                    'has_record'         => (bool) $record,
                ];
            }
        }

        $avgPct    = count($percentages) ? array_sum($percentages) / count($percentages) : 0.0;
        $compliant = empty($nonCompliant);

        return [
            'compliant'       => $compliant,
            'reason'          => $compliant
                ? null
                : count($nonCompliant) . ' school-age member(s) below 85% attendance.',
            'average_percentage' => round($avgPct, 1),
            'required_percentage'=> self::MIN_EDUCATION_ATTENDANCE,
            'members_checked' => $schoolAgeKids->count(),
            'missing_members' => $nonCompliant,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // checkHealthCompliance
    // ─────────────────────────────────────────────────────────────────────────

    public function checkHealthCompliance(int $beneficiaryId): array
    {
        $beneficiary    = Beneficiary::with('familyMembers')->find($beneficiaryId);
        $youngChildren  = $beneficiary->familyMembers->filter(fn($m) => $m->needs_health_monitoring);

        $period         = Carbon::now()->format('Y-m');
        $issues         = [];
        $checkedCount   = 0;

        // Head-of-family basic check
        $headRecord = ComplianceRecord::whereHas('familyMember', fn($q) => $q->where('beneficiary_id', $beneficiaryId))
            ->where('compliance_type', 'health')
            ->where('compliance_period', $period)
            ->latest()
            ->first();

        $checkedCount++;

        if ($youngChildren->isEmpty() && ! $headRecord) {
            return [
                'compliant'      => true,
                'reason'         => 'No health compliance records required for current period.',
                'details'        => [],
                'members_checked'=> 0,
            ];
        }

        foreach ($youngChildren as $member) {
            $record = ComplianceRecord::where('family_member_id', $member->id)
                ->where('compliance_type', 'health')
                ->where('compliance_period', $period)
                ->latest()
                ->first();

            $checkedCount++;

            if (! $record) {
                $issues[] = [
                    'member_id'  => $member->id,
                    'full_name'  => $member->full_name,
                    'age'        => $member->age,
                    'issue'      => 'No health compliance record for current period.',
                    'has_record' => false,
                ];
                continue;
            }

            if (! $record->is_compliant) {
                $issues[] = [
                    'member_id'         => $member->id,
                    'full_name'         => $member->full_name,
                    'age'               => $member->age,
                    'issue'             => 'Health compliance not met.',
                    'has_record'        => true,
                    'vaccination_status'=> $record->vaccination_status,
                    'health_checkup_date'=> $record->health_checkup_date?->format('M d, Y'),
                ];
            }
        }

        $compliant = empty($issues);

        return [
            'compliant'       => $compliant,
            'reason'          => $compliant ? null : count($issues) . ' member(s) have pending health compliance.',
            'members_checked' => $checkedCount,
            'issues'          => $issues,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // checkFDSCompliance
    // ─────────────────────────────────────────────────────────────────────────

    public function checkFDSCompliance(int $beneficiaryId): array
    {
        $beneficiary = Beneficiary::with('familyMembers')->find($beneficiaryId);
        $period      = Carbon::now()->format('Y-m');
        $minSessions = $this->minFdsSessions();

        // FDS is usually tracked on the head/household level via any member
        $sessionsAttended = ComplianceRecord::whereHas('familyMember', function ($q) use ($beneficiaryId) {
                $q->where('beneficiary_id', $beneficiaryId);
            })
            ->where('compliance_type', 'fds')
            ->where('compliance_period', $period)
            ->where('fds_attendance', true)
            ->count();

        $compliant = $sessionsAttended >= $minSessions;

        return [
            'compliant'        => $compliant,
            'reason'           => $compliant
                ? null
                : "Only {$sessionsAttended} of {$minSessions} required FDS sessions attended.",
            'sessions_attended'=> $sessionsAttended,
            'sessions_required'=> $minSessions,
            'period'           => Carbon::now()->format('F Y'),
        ];
    }
}
