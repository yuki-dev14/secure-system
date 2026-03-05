<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the system_settings table.
     * Key-value store for application-wide configuration managed by Administrators.
     * Supports typed values: integer, string, boolean, json.
     *
     * - setting_key: unique slug identifier (e.g. 'max_household_size', 'compliance_threshold')
     * - setting_type: used by the application layer to deserialize setting_value correctly
     * - updated_by_user_id: nullable — some settings may be seeded without a specific user actor
     * - updated_at: explicit timestamp column (no created_at needed; settings are seeded then updated)
     *
     * NOTE: No CASCADE — settings should not be deleted if the updating admin is removed.
     */
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();

            // Setting identifier (slug format, e.g. 'compliance_threshold_percentage')
            $table->string('setting_key', 100)->unique()
                  ->comment('Unique slug key used by the application to retrieve this setting');

            // The value is always stored as text; setting_type governs its interpretation
            $table->text('setting_value')
                  ->comment('Serialized value; interpret according to setting_type');
            $table->enum('setting_type', ['integer', 'string', 'boolean', 'json'])
                  ->comment('Data type hint for correct deserialization in application code');

            // Optional documentation
            $table->text('description')->nullable()
                  ->comment('Human-readable explanation of what this setting controls');

            // Audit: who last changed this setting
            $table->foreignId('updated_by_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete()
                  ->comment('Administrator who last modified this setting; null for seeded defaults');

            // Only updated_at is needed — settings don't have a meaningful "created" event
            $table->timestamp('updated_at')->nullable()
                  ->comment('When this setting was last changed');

            // Performance index
            $table->index('setting_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
