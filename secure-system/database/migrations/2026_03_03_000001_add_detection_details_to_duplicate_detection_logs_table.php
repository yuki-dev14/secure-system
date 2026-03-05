<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds confidence_score, detection_details (JSON), and override tracking columns
 * to the duplicate_detection_logs table for enhanced real-time scan detection.
 *
 * PostgreSQL note: ALTER COLUMN for enum requires a TYPE CAST in PG,
 * so we use a text column for the extended duplicate_type values added
 * via a new `detection_source` column rather than altering the existing enum.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('duplicate_detection_logs', function (Blueprint $table) {
            // Confidence score 0–100
            $table->unsignedTinyInteger('confidence_score')
                  ->default(0)
                  ->after('duplicate_type')
                  ->comment('Confidence score 0-100 for this duplicate match');

            // JSON blob for rich detection details
            $table->jsonb('detection_details')
                  ->nullable()
                  ->after('confidence_score')
                  ->comment('Structured details about what triggered detection');

            // Override tracking (when operator overrides a block)
            $table->string('override_reason', 1000)
                  ->nullable()
                  ->after('detection_details')
                  ->comment('Reason provided when operator overrides a high-confidence block');

            $table->foreignId('override_by_user_id')
                  ->nullable()
                  ->after('override_reason')
                  ->constrained('users')
                  ->nullOnDelete()
                  ->comment('User who approved the override');

            $table->timestamp('override_at')
                  ->nullable()
                  ->after('override_by_user_id')
                  ->comment('When the override was approved');

            // Recommendation recorded at detection time
            $table->enum('recommendation', ['block', 'flag', 'allow'])
                  ->default('flag')
                  ->after('override_at')
                  ->comment('System recommendation at time of detection');

            // status column (active / resolved / dismissed / overridden)
            $table->enum('status', ['active', 'resolved', 'dismissed', 'overridden'])
                  ->default('active')
                  ->after('recommendation')
                  ->comment('Current resolution status of this duplicate flag');

            // Index for fast active-flag lookups during scan
            $table->index(['primary_beneficiary_id', 'status'], 'idx_dup_primary_status');
            $table->index(['duplicate_beneficiary_id', 'status'], 'idx_dup_duplicate_status');
            $table->index('confidence_score');
        });
    }

    public function down(): void
    {
        Schema::table('duplicate_detection_logs', function (Blueprint $table) {
            $table->dropColumn([
                'confidence_score',
                'detection_details',
                'override_reason',
                'override_by_user_id',
                'override_at',
                'recommendation',
                'status',
            ]);
        });
    }
};
