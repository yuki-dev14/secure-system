<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add enhanced audit columns to verification_activity_logs.
     *
     * Note: PostgreSQL renders Laravel enum() columns as VARCHAR + CHECK
     * constraints, NOT as native pg ENUM types. We therefore:
     *   1. Drop the CHECK constraint that limits activity_type values.
     *   2. Leave the column as VARCHAR so any string can be stored.
     *   3. Add all new columns needed for the enhanced audit trail.
     */
    public function up(): void
    {
        // ── Step 1: expand activity_type from enum to unrestricted varchar ──
        // Only possible on PostgreSQL. The original migration created it as
        // an enum which in PG is a VARCHAR+CHECK.
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            // Drop the CHECK constraint that enforces the old enum values.
            // The constraint name follows Laravel's convention: table_column_check
            DB::statement('ALTER TABLE verification_activity_logs DROP CONSTRAINT IF EXISTS verification_activity_logs_activity_type_check');
            // Widen the column to plain VARCHAR so new activity types can be stored.
            DB::statement('ALTER TABLE verification_activity_logs ALTER COLUMN activity_type TYPE VARCHAR(80)');
        }

        // ── Step 2: add the new audit columns ───────────────────────────────
        Schema::table('verification_activity_logs', function (Blueprint $table) {
            // Change tracking
            if (! Schema::hasColumn('verification_activity_logs', 'old_values')) {
                $table->json('old_values')->nullable()
                      ->after('remarks')
                      ->comment('Serialized model attributes before change (edit operations)');
            }
            if (! Schema::hasColumn('verification_activity_logs', 'new_values')) {
                $table->json('new_values')->nullable()
                      ->after('old_values')
                      ->comment('Serialized model attributes after change (edit operations)');
            }
            if (! Schema::hasColumn('verification_activity_logs', 'request_data')) {
                $table->json('request_data')->nullable()
                      ->after('new_values')
                      ->comment('Sanitized request payload stored for audit trails');
            }

            // Response metadata
            if (! Schema::hasColumn('verification_activity_logs', 'response_status')) {
                $table->smallInteger('response_status')->nullable()->unsigned()
                      ->after('request_data')
                      ->comment('HTTP response status code returned for this action');
            }
            if (! Schema::hasColumn('verification_activity_logs', 'execution_time')) {
                $table->float('execution_time')->nullable()
                      ->after('response_status')
                      ->comment('Wall-clock time (seconds) taken to process the request');
            }

            // Activity categorisation
            if (! Schema::hasColumn('verification_activity_logs', 'activity_category')) {
                $table->string('activity_category', 50)->nullable()
                      ->after('activity_type')
                      ->comment('Higher-level category: data_access, data_change, security, system, compliance');
            }

            // Data-access columns
            if (! Schema::hasColumn('verification_activity_logs', 'resource_type')) {
                $table->string('resource_type', 100)->nullable()
                      ->after('activity_category')
                      ->comment('Model class or resource type that was accessed (e.g. Beneficiary)');
            }
            if (! Schema::hasColumn('verification_activity_logs', 'resource_id')) {
                $table->unsignedBigInteger('resource_id')->nullable()
                      ->after('resource_type')
                      ->comment('Primary key of the resource that was accessed');
            }

            // Security / privacy
            if (! Schema::hasColumn('verification_activity_logs', 'severity')) {
                $table->string('severity', 20)->nullable()
                      ->after('resource_id')
                      ->comment('Severity level for security events: low, medium, high, critical');
            }
            if (! Schema::hasColumn('verification_activity_logs', 'is_acknowledged')) {
                $table->boolean('is_acknowledged')->default(false)
                      ->after('severity')
                      ->comment('Security alerts: true once an admin has acknowledged the alert');
            }
            if (! Schema::hasColumn('verification_activity_logs', 'acknowledged_at')) {
                $table->timestamp('acknowledged_at')->nullable()->after('is_acknowledged');
            }
            if (! Schema::hasColumn('verification_activity_logs', 'acknowledged_by')) {
                $table->unsignedBigInteger('acknowledged_by')->nullable()->after('acknowledged_at');
            }
        });

        // ── Step 3: add performance indexes ─────────────────────────────────
        // Use a separate statement block so partial failures don't abort the column adds.
        $existingIndexes = collect(DB::select("
            SELECT indexname FROM pg_indexes
            WHERE tablename = 'verification_activity_logs'
        "))->pluck('indexname')->toArray();

        $indexes = [
            'verification_activity_logs_activity_category_index' =>
                'CREATE INDEX IF NOT EXISTS verification_activity_logs_activity_category_index ON verification_activity_logs (activity_category)',
            'verification_activity_logs_resource_type_index' =>
                'CREATE INDEX IF NOT EXISTS verification_activity_logs_resource_type_index ON verification_activity_logs (resource_type)',
            'verification_activity_logs_resource_type_resource_id_index' =>
                'CREATE INDEX IF NOT EXISTS verification_activity_logs_resource_type_resource_id_index ON verification_activity_logs (resource_type, resource_id)',
            'verification_activity_logs_severity_index' =>
                'CREATE INDEX IF NOT EXISTS verification_activity_logs_severity_index ON verification_activity_logs (severity)',
            'verification_activity_logs_is_acknowledged_index' =>
                'CREATE INDEX IF NOT EXISTS verification_activity_logs_is_acknowledged_index ON verification_activity_logs (is_acknowledged)',
            'verification_activity_logs_response_status_index' =>
                'CREATE INDEX IF NOT EXISTS verification_activity_logs_response_status_index ON verification_activity_logs (response_status)',
        ];

        foreach ($indexes as $name => $sql) {
            if (! in_array($name, $existingIndexes)) {
                DB::statement($sql);
            }
        }
    }

    public function down(): void
    {
        Schema::table('verification_activity_logs', function (Blueprint $table) {
            $cols = [
                'old_values', 'new_values', 'request_data',
                'response_status', 'execution_time',
                'activity_category', 'resource_type', 'resource_id',
                'severity', 'is_acknowledged', 'acknowledged_at', 'acknowledged_by',
            ];
            // Only drop columns that actually exist
            $existing = collect(DB::select("
                SELECT column_name FROM information_schema.columns
                WHERE table_name = 'verification_activity_logs'
            "))->pluck('column_name')->toArray();

            $toDrop = array_filter($cols, fn ($c) => in_array($c, $existing));
            if ($toDrop) {
                $table->dropColumn(array_values($toDrop));
            }
        });

        // Reinstate the original CHECK constraint
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE verification_activity_logs
                ADD CONSTRAINT verification_activity_logs_activity_type_check
                CHECK (activity_type IN ('scan','verify','approve','reject','view','edit','delete'))
            ");
        }
    }
};
