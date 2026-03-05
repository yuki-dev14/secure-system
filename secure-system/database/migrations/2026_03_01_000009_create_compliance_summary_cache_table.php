<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the compliance_summary_cache table.
     * Stores pre-aggregated compliance percentages per beneficiary to avoid expensive
     * real-time calculations on every dashboard/report request.
     *
     * - One row per beneficiary (enforced by UNIQUE constraint on beneficiary_id)
     * - CASCADE on delete: cache row is meaningless without its beneficiary
     * - Percentages stored as decimals (0.00–100.00) for each compliance dimension
     * - overall_compliance_status: derived aggregate status for quick list filtering
     * - missing_requirements: JSON array of specific missing items (used for recommendations UI)
     * - last_updated_at: when the cache was last recomputed
     * - cache_validity: optional TTL timestamp; null means "always valid until explicitly invalidated"
     */
    public function up(): void
    {
        Schema::create('compliance_summary_cache', function (Blueprint $table) {
            $table->id();

            // One-to-one with beneficiaries (unique constraint enforces single cache row per beneficiary)
            $table->foreignId('beneficiary_id')
                  ->unique()
                  ->constrained('beneficiaries')
                  ->cascadeOnDelete()
                  ->comment('One-to-one: each beneficiary has exactly one cache row');

            // Aggregated compliance percentages per dimension
            $table->decimal('education_compliance_percentage', 5, 2)->default(0.00)
                  ->comment('Percentage of education compliance conditions met (0.00–100.00)');
            $table->decimal('health_compliance_percentage', 5, 2)->default(0.00)
                  ->comment('Percentage of health compliance conditions met (0.00–100.00)');
            $table->decimal('fds_compliance_percentage', 5, 2)->default(0.00)
                  ->comment('Percentage of FDS attendance conditions met (0.00–100.00)');

            // Overall calculated status
            $table->enum('overall_compliance_status', ['compliant', 'non_compliant', 'partial'])
                  ->default('non_compliant')
                  ->comment('Derived aggregate status: compliant (≥100%), partial (>0%), non_compliant (0%)');

            // Detailed missing items for UI recommendations
            $table->json('missing_requirements')->nullable()
                  ->comment('JSON array of missing compliance items (e.g. ["health_checkup_2026-01", "fds_2026-02"])');

            // Cache management timestamps
            $table->timestamp('last_updated_at')
                  ->comment('When this cache row was last recomputed from compliance_records');
            $table->timestamp('cache_validity')->nullable()
                  ->comment('Optional expiry timestamp; null = cache remains valid until explicitly invalidated');

            // Performance indexes
            $table->index('beneficiary_id');
            $table->index('overall_compliance_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_summary_cache');
    }
};
