<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the verification_activity_logs table.
     * Immutable audit trail for all user actions in the system.
     * No CASCADE — logs must survive even if the referenced user or beneficiary is removed.
     *
     * - activity_type: enumerated action category for fast filtering
     * - ip_address: supports both IPv4 (max 15 chars) and IPv6 (max 39 chars); 45 chars total with safety margin
     * - timestamp: application-level timestamp (separate from created_at for explicit control)
     * - status: outcome of the activity (success/failed) for security monitoring
     *
     * NOTE: Both user_id and beneficiary_id are nullable to allow system-generated logs
     * and logs for actions on entities that have since been removed.
     */
    public function up(): void
    {
        Schema::create('verification_activity_logs', function (Blueprint $table) {
            $table->id();

            // Actor (nullable: system-generated actions have no user)
            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete()
                  ->comment('User who performed this action; null for system-generated events');

            // Subject (nullable: subject may have been removed, but log is retained)
            $table->foreignId('beneficiary_id')
                  ->nullable()
                  ->constrained('beneficiaries')
                  ->nullOnDelete()
                  ->comment('Beneficiary that was the subject of this activity; null if deleted or N/A');

            // Activity details
            $table->enum('activity_type', ['scan', 'verify', 'approve', 'reject', 'view', 'edit', 'delete'])
                  ->comment('Categorized action type for filtering and reporting');
            $table->text('activity_description')->comment('Human-readable description of what was done');

            // Request context (for security monitoring)
            $table->string('ip_address', 45)->comment('IPv4 or IPv6 address of the requester (max 45 chars for IPv6)');
            $table->text('user_agent')->nullable()->comment('Browser/client user-agent string');

            // Timing
            $table->timestamp('timestamp')->useCurrent()
                  ->comment('Application-level timestamp; defaults to now()');

            // Outcome
            $table->enum('status', ['success', 'failed'])
                  ->comment('Whether the action completed successfully or triggered an error/rejection');
            $table->text('remarks')->nullable();

            // created_at only — logs are immutable, no updated_at
            $table->timestamp('created_at')->nullable();

            // Performance indexes
            $table->index('user_id');
            $table->index('beneficiary_id');
            $table->index('activity_type');
            $table->index('timestamp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verification_activity_logs');
    }
};
