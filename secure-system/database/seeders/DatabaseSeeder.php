<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Execution order matters — seeders that depend on foreign keys
     * must run AFTER the tables they reference are populated:
     *
     *   1. UserSeeder            → no dependencies; must run first
     *   2. SystemSettingsSeeder  → no dependencies; runs after users are available
     *   3. BeneficiarySeeder     → depends on users (registered_by_user_id)
     *                              also seeds: qr_codes, family_members, compliance_summary_cache
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            SystemSettingsSeeder::class,
            BeneficiarySeeder::class,
        ]);

        $this->command->info('');
        $this->command->info('╔══════════════════════════════════════════════════╗');
        $this->command->info('║         SECURE System — Database Seeded          ║');
        $this->command->info('╠══════════════════════════════════════════════════╣');
        $this->command->info('║  Admin:     admin@secure.gov.ph                  ║');
        $this->command->info('║  Password:  SecureAdmin2024!                     ║');
        $this->command->info('╠══════════════════════════════════════════════════╣');
        $this->command->info('║  Officer 1: field1@secure.gov.ph                 ║');
        $this->command->info('║  Officer 2: field2@secure.gov.ph                 ║');
        $this->command->info('║  Password:  FieldOfficer2024!                    ║');
        $this->command->info('╠══════════════════════════════════════════════════╣');
        $this->command->info('║  Verifier:  verifier@secure.gov.ph               ║');
        $this->command->info('║  Password:  Verifier2024!                        ║');
        $this->command->info('╚══════════════════════════════════════════════════╝');
        $this->command->info('');
    }
}
