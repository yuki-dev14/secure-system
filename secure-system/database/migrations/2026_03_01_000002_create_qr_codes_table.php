<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the qr_codes table.
     * Each beneficiary gets one active QR code at a time. Old QR codes are retained
     * for audit purposes (is_valid = false) with the regeneration reason recorded.
     *
     * - beneficiary_id: CASCADE on delete — if beneficiary is removed, orphaned QRs are removed too
     * - verification_token: copied from beneficiary at generation time (snapshot)
     * - is_valid: only one QR per beneficiary should be valid at any given time (enforced at app layer)
     */
    public function up(): void
    {
        Schema::create('qr_codes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('beneficiary_id')
                  ->constrained('beneficiaries')
                  ->cascadeOnDelete()
                  ->comment('Parent beneficiary — QR is deleted if beneficiary is deleted');

            // Token snapshot at generation time
            $table->string('verification_token', 64)->comment('Token value embedded in the QR code at generation time');

            // File storage details
            $table->string('qr_image_path', 255)->comment('Relative storage path (disk path)');
            $table->string('qr_image_url', 255)->comment('Public URL for displaying the QR image');

            // Lifecycle timestamps
            $table->timestamp('generated_at');
            $table->timestamp('expires_at')->nullable()->comment('Optional expiration; null means indefinite');
            $table->boolean('is_valid')->default(true)->comment('Only one QR per beneficiary should be valid at once');

            // Regeneration audit
            $table->timestamp('regenerated_at')->nullable();
            $table->text('regenerated_reason')->nullable()->comment('Reason provided when QR was regenerated (e.g., lost, compromised)');

            // Performance indexes
            $table->index('beneficiary_id');
            $table->index('verification_token');
            $table->index('is_valid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qr_codes');
    }
};
