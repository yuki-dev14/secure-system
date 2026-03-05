<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the submitted_requirements table.
     * Tracks documentary requirements uploaded by beneficiaries or on their behalf by Field Officers.
     * Each file upload is a separate record with its own approval lifecycle.
     *
     * - requirement_type: category of the document submitted
     * - file_path: disk-relative path for server-side retrieval
     * - file_size: stored in bytes; used for storage quota enforcement if needed
     * - file_type: MIME type (e.g. 'application/pdf', 'image/jpeg')
     * - submitted_by_user_id: Field Officer who performed the upload
     * - approved_by_user_id: Administrator/Compliance Verifier who approved; nullable until decision made
     * - approval_status: pending → approved or rejected lifecycle
     */
    public function up(): void
    {
        Schema::create('submitted_requirements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('beneficiary_id')
                  ->constrained('beneficiaries')
                  ->cascadeOnDelete()
                  ->comment('Beneficiary these documents belong to; deleted when beneficiary is removed');

            // Document classification
            $table->enum('requirement_type', [
                'birth_certificate',
                'school_record',
                'health_record',
                'proof_of_income',
                'valid_id',
                'other',
            ])->comment('Category of the uploaded document');

            // File storage details
            $table->string('file_path', 255)->comment('Relative disk path used for server-side file retrieval');
            $table->string('file_name', 255)->comment('Original file name as uploaded by the user');
            $table->integer('file_size')->comment('File size in bytes');
            $table->string('file_type', 100)->comment('MIME type (e.g. application/pdf, image/jpeg)');

            // Submission audit
            $table->timestamp('submitted_at');
            $table->foreignId('submitted_by_user_id')
                  ->constrained('users')
                  ->comment('Field Officer who uploaded this document');

            // Approval workflow
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamp('approval_date')->nullable();
            $table->foreignId('approved_by_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete()
                  ->comment('User who approved/rejected this document; null until decision is made');
            $table->text('rejection_reason')->nullable()->comment('Required when approval_status = rejected');

            // Document validity
            $table->date('expiration_date')->nullable()->comment('Optional expiry (e.g. for valid IDs)');

            // Performance indexes
            $table->index('beneficiary_id');
            $table->index('approval_status');
            $table->index('requirement_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submitted_requirements');
    }
};
