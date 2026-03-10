<?php

namespace App\Services;

use App\Models\Beneficiary;
use App\Models\FamilyMember;
use Illuminate\Support\Facades\DB;

class PayoutCalculationService
{
    // ─────────────────────────────────────────────────────────────────────────
    // getSetting — helper to read a system_settings value
    // ─────────────────────────────────────────────────────────────────────────

    private function getSetting(string $key, mixed $default = null): mixed
    {
        $row = DB::table('system_settings')->where('setting_key', $key)->first();
        if (! $row) {
            return $default;
        }

        return match ($row->setting_type) {
            'integer' => (int) $row->setting_value,
            'boolean' => filter_var($row->setting_value, FILTER_VALIDATE_BOOLEAN),
            'json'    => json_decode($row->setting_value, true),
            default   => $row->setting_value,
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // calculatePayoutAmount
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Calculate the total payout amount for a beneficiary.
     *
     * @return array{
     *   base_amount: float,
     *   education_subsidy: float,
     *   health_subsidy: float,
     *   total_amount: float,
     *   breakdown: array
     * }
     */
    public function calculatePayoutAmount(int $beneficiaryId): array
    {
        $beneficiary = Beneficiary::with('familyMembers')->findOrFail($beneficiaryId);

        // System settings (with defaults)
        $baseAmount           = (float) $this->getSetting('default_payout_amount', 3000);
        $perChildEducation    = (float) $this->getSetting('per_child_education_amount', 300);
        $perChildHealth       = (float) $this->getSetting('per_child_health_amount', 400);

        // Count school-age children (5–21)
        $schoolAgeChildren = $beneficiary->familyMembers->filter(fn ($m) => $m->is_school_age);

        // Count children under 5 (health subsidy)
        $childrenUnder5 = $beneficiary->familyMembers->filter(fn ($m) => $m->age < 5);

        $educationSubsidy = $schoolAgeChildren->count() * $perChildEducation;
        $healthSubsidy    = $childrenUnder5->count()    * $perChildHealth;
        $totalAmount      = $baseAmount + $educationSubsidy + $healthSubsidy;

        return [
            'base_amount'              => $baseAmount,
            'education_subsidy'        => $educationSubsidy,
            'health_subsidy'           => $healthSubsidy,
            'total_amount'             => $totalAmount,
            'school_age_children_count'=> $schoolAgeChildren->count(),
            'under_5_children_count'   => $childrenUnder5->count(),
            'per_child_education'      => $perChildEducation,
            'per_child_health'         => $perChildHealth,
            'household_size'           => $beneficiary->household_size,
            'breakdown'                => [
                [
                    'label'  => 'Base Cash Grant',
                    'amount' => $baseAmount,
                    'detail' => 'Standard household payout',
                ],
                [
                    'label'  => 'Education Subsidy',
                    'amount' => $educationSubsidy,
                    'detail' => $schoolAgeChildren->count() . ' school-age child(ren) × ₱' . number_format($perChildEducation, 2),
                ],
                [
                    'label'  => 'Health Subsidy',
                    'amount' => $healthSubsidy,
                    'detail' => $childrenUnder5->count() . ' child(ren) under 5 × ₱' . number_format($perChildHealth, 2),
                ],
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // validateAmount
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Validate a proposed payout amount against the calculated expected amount.
     * Allows +/- 1% variance.
     *
     * @return array{is_valid: bool, expected_amount: float, proposed_amount: float, variance: float, variance_pct: float}
     */
    public function validateAmount(int $beneficiaryId, float $proposedAmount): array
    {
        $calculation    = $this->calculatePayoutAmount($beneficiaryId);
        $expectedAmount = $calculation['total_amount'];
        $variance       = $proposedAmount - $expectedAmount;
        $variancePct    = $expectedAmount > 0 ? abs($variance / $expectedAmount) * 100 : 0;

        return [
            'is_valid'        => $variancePct <= 1.0,
            'expected_amount' => $expectedAmount,
            'proposed_amount' => $proposedAmount,
            'variance'        => $variance,
            'variance_pct'    => round($variancePct, 4),
        ];
    }
}
