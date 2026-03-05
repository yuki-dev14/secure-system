<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add composite index on (beneficiary_id, submitted_at) for paginated
     * DESC queries in RequirementsController::index(), and ensure submitted_at
     * is indexed for expiration cron queries.
     */
    public function up(): void
    {
        Schema::table('submitted_requirements', function (Blueprint $table) {
            // Check and add composite index for common query pattern
            $table->index(['beneficiary_id', 'submitted_at'], 'req_beneficiary_date_idx');

            // Index for expiration queries
            $table->index('expiration_date', 'req_expiration_idx');
        });
    }

    public function down(): void
    {
        Schema::table('submitted_requirements', function (Blueprint $table) {
            $table->dropIndex('req_beneficiary_date_idx');
            $table->dropIndex('req_expiration_idx');
        });
    }
};
