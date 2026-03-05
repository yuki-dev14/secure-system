<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the family_members table.
     * Stores individual household members associated with a beneficiary (household head).
     * These members are the subjects of compliance monitoring (education, health, FDS).
     *
     * - beneficiary_id: CASCADE on delete — family members are removed with their beneficiary
     * - relationship_to_head: defines member's role within the household
     * - school_enrollment_status: used for quick compliance pre-filtering
     * - health_center_registered: flag indicating registration at a government health center
     */
    public function up(): void
    {
        Schema::create('family_members', function (Blueprint $table) {
            $table->id();

            $table->foreignId('beneficiary_id')
                  ->constrained('beneficiaries')
                  ->cascadeOnDelete()
                  ->comment('Household this member belongs to; deleted with beneficiary');

            // Personal details
            $table->string('full_name', 255);
            $table->date('birthdate');
            $table->enum('gender', ['Male', 'Female']);

            // Household role
            $table->enum('relationship_to_head', ['Spouse', 'Child', 'Parent', 'Sibling', 'Other']);

            // Document reference
            $table->string('birth_certificate_no', 50)->nullable()->comment('PSA birth certificate number if available');

            // Pre-compliance flags (for quick filtering before full compliance check)
            $table->enum('school_enrollment_status', ['Enrolled', 'Not Enrolled', 'Not Applicable'])
                  ->default('Not Applicable')
                  ->comment('Current school enrollment status; Not Applicable for non-school-age members');
            $table->boolean('health_center_registered')
                  ->default(false)
                  ->comment('Whether this member is registered at a government health center');

            // Performance indexes
            $table->index('beneficiary_id');
            $table->index('school_enrollment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('family_members');
    }
};
