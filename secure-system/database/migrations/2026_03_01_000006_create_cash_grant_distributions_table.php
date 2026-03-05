<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the cash_grant_distributions table.
     * Records every payout event for a beneficiary household.
     * Each row = one disbursement transaction.
     *
     * - payout_period: descriptive label (e.g. "Q1 2026", "January 2026")
     * - payout_month / payout_year: numeric values enabling period-range queries
     * - distributed_by_user_id: Field Officer who physically dispersed the grant
     * - approved_by_user_id: Administrator who authorized this payout
     * - transaction_reference_number: unique per payout for traceability/auditing
     * - received_signature_path: optional scanned signature or e-signature path
     *
     * NOTE: No CASCADE on beneficiary_id — distribution history must be preserved
     * even if the beneficiary record is soft-deleted or deactivated.
     */
    public function up(): void
    {
        Schema::create('cash_grant_distributions', function (Blueprint $table) {
            $table->id();

            // Link to beneficiary (no CASCADE — preserve financial history)
            $table->foreignId('beneficiary_id')
                  ->constrained('beneficiaries')
                  ->comment('Beneficiary receiving this payout — history retained even if beneficiary deactivated');

            // Payout details
            $table->decimal('payout_amount', 10, 2)->comment('Amount disbursed in Philippine Peso');
            $table->string('payout_period', 255)->comment('Human-readable label e.g. "Q1 2026", "January 2026"');
            $table->unsignedTinyInteger('payout_month')->comment('Numeric month 1–12 for period queries');
            $table->unsignedSmallInteger('payout_year')->comment('4-digit year');

            // Disbursement audit
            $table->timestamp('distributed_at')->comment('When the physical payout was made');
            $table->foreignId('distributed_by_user_id')
                  ->constrained('users')
                  ->comment('Field Officer who distributed the cash/transferred the grant');
            $table->foreignId('approved_by_user_id')
                  ->constrained('users')
                  ->comment('Administrator who authorized this distribution');

            // Payment method
            $table->enum('payment_method', ['cash', 'e-wallet', 'bank_transfer']);

            // Traceability
            $table->string('transaction_reference_number', 100)->unique()
                  ->comment('Unique reference number for this payout transaction (for reconciliation)');
            $table->string('received_signature_path', 255)->nullable()
                  ->comment('Storage path of the beneficiary signature proving receipt');
            $table->text('remarks')->nullable();

            $table->timestamps();

            // Performance indexes
            $table->index('beneficiary_id');
            $table->index('payout_year');
            $table->index('payout_month');
            $table->index('transaction_reference_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_grant_distributions');
    }
};
