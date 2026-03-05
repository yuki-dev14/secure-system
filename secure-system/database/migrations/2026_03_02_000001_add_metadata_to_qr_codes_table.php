<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add metadata (JSON) column to the qr_codes table.
     *
     * Stores generation context, device info, operator info,
     * download count, and access log entries per QR record.
     */
    public function up(): void
    {
        Schema::table('qr_codes', function (Blueprint $table) {
            // JSON blob — generation_method, operator_name, download_count, access_log, etc.
            $table->json('metadata')->nullable()->after('regenerated_reason')
                  ->comment('JSON metadata: generation method, operator info, download counts, access log');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('qr_codes', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};
