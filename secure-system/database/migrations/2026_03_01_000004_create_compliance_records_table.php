<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the compliance_records table.
     * Tracks per-member compliance for each CCT conditionality (education, health, FDS).
     * One record per member per compliance_type per compliance_period (YYYY-MM).
     *
     * - compliance_type: which conditionality this record tracks ('education', 'health', 'fds')
     * - compliance_period: month-year string (e.g. '2026-02') — allows monthly granularity
     * - education fields: school_name, enrollment_status, attendance_percentage
     * - health fields: health_checkup_date, vaccination_status
     * - fds_attendance: boolean for Family Development Session attendance
     * - verified_by_user_id: Compliance Verifier who confirmed this record; nullable (unverified)
     * - is_compliant: computed/set result — true if all applicable conditions are met
     */
    public function up(): void
    {
        Schema::create('compliance_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('family_member_id')
                  ->constrained('family_members')
                  ->cascadeOnDelete()
                  ->comment('The specific household member this compliance record belongs to');

            // Compliance classification
            $table->enum('compliance_type', ['education', 'health', 'fds'])
                  ->comment('Which CCT conditionality: education, health check-up, or family development session');
            $table->string('compliance_period', 7)
                  ->comment('Period in YYYY-MM format (e.g. 2026-02)');

            // Education-specific fields
            $table->string('school_name', 255)->nullable();
            $table->enum('enrollment_status', ['Enrolled', 'Not Enrolled', 'Not Applicable'])->nullable();
            $table->decimal('attendance_percentage', 5, 2)->nullable()
                  ->comment('Class attendance rate 0.00–100.00');

            // Health-specific fields
            $table->date('health_checkup_date')->nullable();
            $table->enum('vaccination_status', ['Complete', 'Incomplete', 'Not Applicable'])->nullable();

            // FDS-specific field
            $table->boolean('fds_attendance')->default(false)
                  ->comment('Whether this member attended the Family Development Session for this period');

            // Verification audit
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete()
                  ->comment('Compliance Verifier who signed off this record; null if not yet verified');

            // Computed compliance result
            $table->boolean('is_compliant')->default(false)
                  ->comment('True if member meets all applicable compliance conditions for this period');

            $table->timestamps();

            // Performance indexes
            $table->index('family_member_id');
            $table->index('compliance_type');
            $table->index('compliance_period');
            $table->index('is_compliant');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_records');
    }
};
