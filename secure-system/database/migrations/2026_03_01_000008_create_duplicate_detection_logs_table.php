<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the duplicate_detection_logs table.
     * Records suspected or confirmed duplicate beneficiary registrations.
     * Two beneficiary FKs are used: the primary (original) and the potential duplicate.
     *
     * - primary_beneficiary_id: the original/master record
     * - duplicate_beneficiary_id: the suspected duplicate (nullable if the duplicate was already removed)
     * - duplicate_type: how the duplicate was detected (name, address, contact, or token collision)
     * - detected_by_system_or_user: whether the system flagged it automatically or a user reported it
     * - action_taken + merged_or_flagged: store both the initial action and the final resolution state
     * - resolver_user_id: Administrator who resolved the flag
     *
     * NO CASCADE on either beneficiary FK — logs must survive even if beneficiary records are removed.
     */
    public function up(): void
    {
        Schema::create('duplicate_detection_logs', function (Blueprint $table) {
            $table->id();

            // Primary (authoritative) beneficiary record
            $table->foreignId('primary_beneficiary_id')
                  ->constrained('beneficiaries')
                  ->comment('The original/master beneficiary record in the duplicate pair');

            // Potential duplicate (nullable: may already have been removed)
            $table->foreignId('duplicate_beneficiary_id')
                  ->nullable()
                  ->constrained('beneficiaries')
                  ->nullOnDelete()
                  ->comment('The suspected duplicate beneficiary; null if already deleted');

            // Detection metadata
            $table->enum('duplicate_type', ['name_match', 'address_match', 'contact_match', 'token_collision'])
                  ->comment('Which field similarity triggered the duplicate flag');
            $table->timestamp('detection_date')->useCurrent()
                  ->comment('When this duplicate was detected');
            $table->enum('detected_by_system_or_user', ['system', 'user'])
                  ->comment('Whether the detection was automated or manually reported');

            // Resolution tracking
            $table->enum('action_taken', ['flagged', 'merged', 'dismissed'])->default('flagged')
                  ->comment('Initial action taken upon detection');
            $table->enum('merged_or_flagged', ['merged', 'flagged', 'dismissed'])
                  ->comment('Final resolution state of this duplicate log');
            $table->timestamp('resolved_at')->nullable()->comment('Timestamp when the duplicate was resolved');
            $table->foreignId('resolver_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete()
                  ->comment('Administrator who resolved this duplicate flag');

            // Performance indexes
            $table->index('primary_beneficiary_id');
            $table->index('duplicate_beneficiary_id');
            $table->index('duplicate_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('duplicate_detection_logs');
    }
};
