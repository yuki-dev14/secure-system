<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SystemSettingsSeeder extends Seeder
{
    /**
     * Seed the system_settings table with default application configuration.
     *
     * Uses upsert() keyed on setting_key so this seeder is safe to re-run.
     * An admin user (id=1) is assumed to exist before this seeder runs;
     * updated_by_user_id is left null for bootstrapped defaults (no specific actor).
     *
     * Setting types:
     *   - integer  → cast to int via (int) in application code
     *   - string   → used as-is
     *   - boolean  → cast to bool ('true'/'false') in application code
     *   - json     → decoded via json_decode() in application code
     */
    public function run(): void
    {
        $now = now();

        $settings = [
            // ── Compliance thresholds ─────────────────────────────────────────
            [
                'setting_key'   => 'minimum_attendance_percentage',
                'setting_value' => '85',
                'setting_type'  => 'integer',
                'description'   => 'Minimum class attendance percentage (0–100) required for education compliance.',
                'updated_at'    => $now,
            ],
            [
                'setting_key'   => 'education_compliance_threshold',
                'setting_value' => '85',
                'setting_type'  => 'integer',
                'description'   => 'Minimum overall education compliance percentage to be considered compliant.',
                'updated_at'    => $now,
            ],
            [
                'setting_key'   => 'health_compliance_threshold',
                'setting_value' => '80',
                'setting_type'  => 'integer',
                'description'   => 'Minimum health check-up compliance percentage to be considered compliant.',
                'updated_at'    => $now,
            ],

            // ── QR Code settings ──────────────────────────────────────────────
            [
                'setting_key'   => 'qr_code_expiration_days',
                'setting_value' => '365',
                'setting_type'  => 'integer',
                'description'   => 'Number of days before a generated QR code expires. Set to 0 for no expiration.',
                'updated_at'    => $now,
            ],

            // ── Financial settings ────────────────────────────────────────────
            [
                'setting_key'   => 'default_payout_amount',
                'setting_value' => '3000',
                'setting_type'  => 'integer',
                'description'   => 'Default cash grant payout amount in Philippine Peso (₱).',
                'updated_at'    => $now,
            ],

            // ── Security & session settings ───────────────────────────────────
            [
                'setting_key'   => 'session_timeout_minutes',
                'setting_value' => '30',
                'setting_type'  => 'integer',
                'description'   => 'Idle session timeout duration in minutes. Users will be logged out after this period of inactivity.',
                'updated_at'    => $now,
            ],
            [
                'setting_key'   => 'max_login_attempts',
                'setting_value' => '5',
                'setting_type'  => 'integer',
                'description'   => 'Maximum failed login attempts before the account is temporarily locked.',
                'updated_at'    => $now,
            ],

            // ── System identity ───────────────────────────────────────────────
            [
                'setting_key'   => 'system_name',
                'setting_value' => 'SECURE System',
                'setting_type'  => 'string',
                'description'   => 'Full display name of the system shown in the UI header and reports.',
                'updated_at'    => $now,
            ],
            [
                'setting_key'   => 'system_version',
                'setting_value' => '1.0.0',
                'setting_type'  => 'string',
                'description'   => 'Current semantic version of the SECURE System application.',
                'updated_at'    => $now,
            ],
        ];

        // Upsert: insert or update on duplicate setting_key
        DB::table('system_settings')->upsert(
            $settings,
            uniqueBy: ['setting_key'],
            update:   ['setting_value', 'setting_type', 'description', 'updated_at']
        );

        $this->command->info('✓ SystemSettingsSeeder: ' . count($settings) . ' settings configured.');
    }
}
