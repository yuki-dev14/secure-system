<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BeneficiarySeeder extends Seeder
{
    /**
     * Seed 5 realistic beneficiary records for testing.
     *
     * Each beneficiary gets:
     *   - A unique BIN in the format BIN-YYYY-NNNNN
     *   - A unique 64-character verification token (used to generate the QR code)
     *   - A QR code record (is_valid = true) linked to that token
     *   - 2–6 family members covering various relationships
     *
     * registered_by_user_id is randomly assigned to one of the two seeded field officers.
     *
     * IMPORTANT: This seeder assumes UserSeeder has already run.
     */
    public function run(): void
    {
        // Fetch the two seeded field officers for assigning registrations
        $fieldOfficers = User::where('role', 'Field Officer')->pluck('id');

        if ($fieldOfficers->isEmpty()) {
            $this->command->warn('BeneficiarySeeder: no Field Officers found — run UserSeeder first.');
            return;
        }

        // ── Beneficiary dataset ───────────────────────────────────────────────
        $beneficiaries = [
            [
                'bin'                 => 'BIN-2024-00001',
                'family_head_name'    => 'Rosa Dela Cruz',
                'family_head_birthdate' => '1985-04-12',
                'gender'              => 'Female',
                'civil_status'        => 'Married',
                'contact_number'      => '09171234567',
                'email'               => 'rosa.delacruz@email.com',
                'barangay'            => 'Bagong Silang',
                'municipality'        => 'Caloocan City',
                'province'            => 'Metro Manila',
                'zip_code'            => '1400',
                'annual_income'       => 84000.00,  // ₱7,000/month
                'household_size'      => 5,
                'members' => [
                    ['full_name' => 'Juan Dela Cruz',    'birthdate' => '1983-07-20', 'gender' => 'Male',   'relationship' => 'Spouse'],
                    ['full_name' => 'Miguel Dela Cruz',  'birthdate' => '2010-02-15', 'gender' => 'Male',   'relationship' => 'Child'],
                    ['full_name' => 'Lucia Dela Cruz',   'birthdate' => '2012-11-03', 'gender' => 'Female', 'relationship' => 'Child'],
                    ['full_name' => 'Pedro Dela Cruz',   'birthdate' => '2016-08-25', 'gender' => 'Male',   'relationship' => 'Child'],
                ],
            ],
            [
                'bin'                 => 'BIN-2024-00002',
                'family_head_name'    => 'Eduardo Bautista',
                'family_head_birthdate' => '1978-09-30',
                'gender'              => 'Male',
                'civil_status'        => 'Married',
                'contact_number'      => '09281234567',
                'email'               => null,
                'barangay'            => 'Poblacion',
                'municipality'        => 'Marikina City',
                'province'            => 'Metro Manila',
                'zip_code'            => '1800',
                'annual_income'       => 60000.00,  // ₱5,000/month
                'household_size'      => 4,
                'members' => [
                    ['full_name' => 'Celia Bautista',    'birthdate' => '1980-03-14', 'gender' => 'Female', 'relationship' => 'Spouse'],
                    ['full_name' => 'Carlo Bautista',    'birthdate' => '2009-06-22', 'gender' => 'Male',   'relationship' => 'Child'],
                    ['full_name' => 'Lara Bautista',     'birthdate' => '2014-12-10', 'gender' => 'Female', 'relationship' => 'Child'],
                ],
            ],
            [
                'bin'                 => 'BIN-2024-00003',
                'family_head_name'    => 'Nena Ramos',
                'family_head_birthdate' => '1970-01-05',
                'gender'              => 'Female',
                'civil_status'        => 'Widowed',
                'contact_number'      => '09391234567',
                'email'               => null,
                'barangay'            => 'San Antonio',
                'municipality'        => 'Pasig City',
                'province'            => 'Metro Manila',
                'zip_code'            => '1600',
                'annual_income'       => 48000.00,  // ₱4,000/month
                'household_size'      => 3,
                'members' => [
                    ['full_name' => 'Rico Ramos',        'birthdate' => '1998-05-18', 'gender' => 'Male',   'relationship' => 'Child'],
                    ['full_name' => 'Ana Ramos',         'birthdate' => '2005-09-09', 'gender' => 'Female', 'relationship' => 'Child'],
                ],
            ],
            [
                'bin'                 => 'BIN-2024-00004',
                'family_head_name'    => 'Roberto Garcia',
                'family_head_birthdate' => '1990-11-22',
                'gender'              => 'Male',
                'civil_status'        => 'Single',
                'contact_number'      => '09451234567',
                'email'               => 'rgarcia@email.com',
                'barangay'            => 'Tandang Sora',
                'municipality'        => 'Quezon City',
                'province'            => 'Metro Manila',
                'zip_code'            => '1116',
                'annual_income'       => 72000.00,  // ₱6,000/month
                'household_size'      => 2,
                'members' => [
                    ['full_name' => 'Sofia Garcia',      'birthdate' => '2018-03-30', 'gender' => 'Female', 'relationship' => 'Child'],
                ],
            ],
            [
                'bin'                 => 'BIN-2024-00005',
                'family_head_name'    => 'Ligaya Torres',
                'family_head_birthdate' => '1975-06-17',
                'gender'              => 'Female',
                'civil_status'        => 'Separated',
                'contact_number'      => '09561234567',
                'email'               => null,
                'barangay'            => 'Batasan Hills',
                'municipality'        => 'Quezon City',
                'province'            => 'Metro Manila',
                'zip_code'            => '1126',
                'annual_income'       => 54000.00,  // ₱4,500/month
                'household_size'      => 6,
                'members' => [
                    ['full_name' => 'Alex Torres',       'birthdate' => '2000-07-04', 'gender' => 'Male',   'relationship' => 'Child'],
                    ['full_name' => 'Rina Torres',       'birthdate' => '2003-02-11', 'gender' => 'Female', 'relationship' => 'Child'],
                    ['full_name' => 'Mark Torres',       'birthdate' => '2007-10-29', 'gender' => 'Male',   'relationship' => 'Child'],
                    ['full_name' => 'Luz Torres',        'birthdate' => '2013-05-17', 'gender' => 'Female', 'relationship' => 'Child'],
                    ['full_name' => 'Ben Torres',        'birthdate' => '2019-08-01', 'gender' => 'Male',   'relationship' => 'Child'],
                ],
            ],
        ];

        // ── Relationship-specific enums ───────────────────────────────────────
        $enrollmentByAge = function (string $birthdate): string {
            $age = now()->diffInYears($birthdate);
            if ($age >= 5 && $age <= 25) return 'Enrolled';
            if ($age > 25)               return 'Not Applicable';
            return 'Not Applicable'; // too young
        };

        foreach ($beneficiaries as $data) {
            $token          = Str::random(64);
            $officerId      = $fieldOfficers->random();
            $generatedAt    = now()->subDays(rand(30, 180));

            // ── Insert beneficiary ─────────────────────────────────────────────
            $beneficiaryId = DB::table('beneficiaries')->insertGetId([
                'bin'                     => $data['bin'],
                'verification_token'      => $token,
                'family_head_name'        => $data['family_head_name'],
                'family_head_birthdate'   => $data['family_head_birthdate'],
                'gender'                  => $data['gender'],
                'civil_status'            => $data['civil_status'],
                'contact_number'          => $data['contact_number'],
                'email'                   => $data['email'],
                'barangay'                => $data['barangay'],
                'municipality'            => $data['municipality'],
                'province'                => $data['province'],
                'zip_code'                => $data['zip_code'],
                'annual_income'           => $data['annual_income'],
                'household_size'          => $data['household_size'],
                'token_status'            => 'active',
                'is_active'               => true,
                'registered_by_user_id'   => $officerId,
                'created_at'              => $generatedAt,
                'updated_at'              => $generatedAt,
            ]);

            // ── Insert QR code ─────────────────────────────────────────────────
            DB::table('qr_codes')->insert([
                'beneficiary_id'     => $beneficiaryId,
                'verification_token' => $token,
                // Placeholder paths; actual generation happens via QrCodeService
                'qr_image_path'      => "qr_codes/{$data['bin']}.png",
                'qr_image_url'       => "/storage/qr_codes/{$data['bin']}.png",
                'generated_at'       => $generatedAt,
                'expires_at'         => $generatedAt->copy()->addDays(365),
                'is_valid'           => true,
                'regenerated_at'     => null,
                'regenerated_reason' => null,
            ]);

            // ── Insert family members ──────────────────────────────────────────
            foreach ($data['members'] as $member) {
                DB::table('family_members')->insert([
                    'beneficiary_id'           => $beneficiaryId,
                    'full_name'                => $member['full_name'],
                    'birthdate'                => $member['birthdate'],
                    'gender'                   => $member['gender'],
                    'relationship_to_head'     => $member['relationship'],
                    'birth_certificate_no'     => null,
                    'school_enrollment_status' => $enrollmentByAge($member['birthdate']),
                    'health_center_registered' => (bool) rand(0, 1),
                ]);
            }

            // ── Insert compliance summary cache (initial zeroed state) ─────────
            DB::table('compliance_summary_cache')->insertOrIgnore([
                'beneficiary_id'                  => $beneficiaryId,
                'education_compliance_percentage'  => 0.00,
                'health_compliance_percentage'     => 0.00,
                'fds_compliance_percentage'        => 0.00,
                'overall_compliance_status'        => 'non_compliant',
                'missing_requirements'             => null,
                'last_updated_at'                  => now(),
                'cache_validity'                   => now()->addHours(24),
            ]);
        }

        $this->command->info('✓ BeneficiarySeeder: 5 beneficiaries seeded with QR codes, family members, and compliance cache.');
    }
}
