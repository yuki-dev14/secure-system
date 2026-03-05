<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed the system's user accounts.
     *
     * Creates the four core accounts needed to test all role-based access controls:
     *   - 1 Administrator  (full system access)
     *   - 2 Field Officers  (register beneficiaries, upload docs, distribute grants)
     *   - 1 Compliance Verifier (verify & approve compliance records)
     *
     * Passwords are individually hashed so each can be different.
     * All accounts are seeded with email_verified_at set to avoid
     * Breeze's email verification gate during local development.
     */
    public function run(): void
    {
        // ── Administrator ─────────────────────────────────────────────────────
        User::updateOrCreate(
            ['email' => 'admin@secure.gov.ph'],
            [
                'name'              => 'Admin User',
                'password'          => Hash::make('SecureAdmin2024!'),
                'role'              => 'Administrator',
                'office_location'   => 'DSWD Main Office',
                'status'            => 'active',
                'email_verified_at' => now(),
                'last_login_at'     => null,
            ]
        );

        // ── Field Officers ────────────────────────────────────────────────────
        User::updateOrCreate(
            ['email' => 'field1@secure.gov.ph'],
            [
                'name'              => 'Maria Santos',
                'password'          => Hash::make('FieldOfficer2024!'),
                'role'              => 'Field Officer',
                'office_location'   => 'Quezon City Field Office',
                'status'            => 'active',
                'email_verified_at' => now(),
                'last_login_at'     => now()->subDays(2),
            ]
        );

        User::updateOrCreate(
            ['email' => 'field2@secure.gov.ph'],
            [
                'name'              => 'Jose Reyes',
                'password'          => Hash::make('FieldOfficer2024!'),
                'role'              => 'Field Officer',
                'office_location'   => 'Manila Field Office',
                'status'            => 'active',
                'email_verified_at' => now(),
                'last_login_at'     => now()->subDays(5),
            ]
        );

        // ── Compliance Verifier ───────────────────────────────────────────────
        User::updateOrCreate(
            ['email' => 'verifier@secure.gov.ph'],
            [
                'name'              => 'Ana Cruz',
                'password'          => Hash::make('Verifier2024!'),
                'role'              => 'Compliance Verifier',
                'office_location'   => 'DSWD Main Office',
                'status'            => 'active',
                'email_verified_at' => now(),
                'last_login_at'     => now()->subHours(3),
            ]
        );

        $this->command->info('✓ UserSeeder: 4 user accounts created/updated.');
    }
}
