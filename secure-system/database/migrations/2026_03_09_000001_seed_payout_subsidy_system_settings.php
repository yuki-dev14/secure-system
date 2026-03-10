<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add payout subsidy settings needed by PayoutCalculationService.
     *
     * - per_child_education_amount: subsidy per school-age child (age 5–21)
     * - per_child_health_amount: subsidy per child under 5
     */
    public function up(): void
    {
        $now = now();

        $settings = [
            [
                'setting_key'   => 'per_child_education_amount',
                'setting_value' => '300',
                'setting_type'  => 'integer',
                'description'   => 'Additional payout amount per school-age child (5–21 years) in Philippine Peso.',
                'updated_at'    => $now,
            ],
            [
                'setting_key'   => 'per_child_health_amount',
                'setting_value' => '400',
                'setting_type'  => 'integer',
                'description'   => 'Additional payout amount per child under 5 years for health subsidy, in Philippine Peso.',
                'updated_at'    => $now,
            ],
            [
                'setting_key'   => 'distribution_discrepancy_threshold_pct',
                'setting_value' => '0.01',
                'setting_type'  => 'string',
                'description'   => 'Maximum acceptable discrepancy percentage (0.01 = 0.01%) before flagging in reconciliation.',
                'updated_at'    => $now,
            ],
            [
                'setting_key'   => 'fds_minimum_sessions',
                'setting_value' => '2',
                'setting_type'  => 'integer',
                'description'   => 'Minimum number of FDS sessions required per compliance period.',
                'updated_at'    => $now,
            ],
        ];

        DB::table('system_settings')->upsert(
            $settings,
            uniqueBy: ['setting_key'],
            update:   ['setting_value', 'setting_type', 'description', 'updated_at']
        );
    }

    public function down(): void
    {
        DB::table('system_settings')->whereIn('setting_key', [
            'per_child_education_amount',
            'per_child_health_amount',
            'distribution_discrepancy_threshold_pct',
            'fds_minimum_sessions',
        ])->delete();
    }
};
