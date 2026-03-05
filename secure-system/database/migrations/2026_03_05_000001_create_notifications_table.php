<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * In-app notification records for compliance alerts, pending verifications,
     * expiring periods, and non-compliant beneficiary alerts.
     *
     * Each notification targets a single user (user_id).
     * read_at is NULL when unread; set to now() on read.
     * data (JSON) carries context-specific payload (beneficiary_id, link, etc.).
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            // Recipient
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete()
                  ->comment('User who should receive this notification');

            // Classification
            $table->enum('notification_type', [
                'compliance_alert',
                'pending_verification',
                'expiring_period',
                'non_compliant_beneficiary',
            ])->comment('Category used for icon/routing in the frontend');

            // Content
            $table->string('title', 255)->comment('Short headline shown in the notification list');
            $table->text('message')->comment('Full human-readable alert body');

            // Extra payload (e.g. beneficiary_id, compliance_period, redirect_url)
            $table->json('data')->nullable()->comment('Arbitrary JSON context data');

            // Read state
            $table->timestamp('read_at')
                  ->nullable()
                  ->comment('NULL = unread; set to current timestamp when the user reads it');

            $table->timestamp('created_at')
                  ->useCurrent()
                  ->comment('Creation time — no updated_at needed for immutable notifications');

            // Indexes
            $table->index('user_id');
            $table->index('notification_type');
            $table->index(['user_id', 'read_at']); // fast unread-count queries
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
