<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the beneficiaries table — the primary entity of the SECURE system.
     * Each beneficiary record represents a household head enrolled in the 4Ps/CCT program.
     *
     * - bin: Beneficiary ID Number (unique identifier used in QR scan verification)
     * - verification_token: Used to generate the secure QR code; must be unique and cryptographically random
     * - registered_by_user_id: Field Officer who registered this beneficiary
     */
    public function up(): void
    {
        Schema::create('beneficiaries', function (Blueprint $table) {
            $table->id();

            // Unique identifiers
            $table->string('bin', 20)->unique()->comment('Beneficiary ID Number — unique per household');
            $table->string('verification_token', 64)->unique()->comment('Cryptographic token used to generate QR code');

            // Household head personal information
            $table->string('family_head_name', 255);
            $table->date('family_head_birthdate');
            $table->enum('gender', ['Male', 'Female']);
            $table->enum('civil_status', ['Single', 'Married', 'Widowed', 'Separated']);

            // Contact information
            $table->string('contact_number', 20);
            $table->string('email', 255)->nullable();

            // Address information
            $table->string('barangay', 255);
            $table->string('municipality', 255);
            $table->string('province', 255);
            $table->string('zip_code', 10);

            // Household economic profile
            $table->decimal('annual_income', 10, 2);
            $table->integer('household_size');

            // Token and account status
            $table->enum('token_status', ['active', 'expired', 'revoked'])->default('active');
            $table->boolean('is_active')->default(true);

            // Audit: who registered this beneficiary
            $table->foreignId('registered_by_user_id')
                  ->constrained('users')
                  ->comment('Field Officer who registered this beneficiary');

            $table->timestamps();

            // Performance indexes
            $table->index('bin');
            $table->index('verification_token');
            $table->index('barangay');
            $table->index('municipality');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beneficiaries');
    }
};
