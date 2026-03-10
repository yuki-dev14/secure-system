<?php

namespace App\Console\Commands;

use App\Models\VerificationActivityLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ArchiveOldAuditLogs extends Command
{
    protected $signature   = 'audit:archive {--dry-run : Show what would be archived without actually moving data}';
    protected $description = 'Archive audit logs older than 2 years into audit_logs_archive table.';

    public function handle(): int
    {
        $cutoffDate  = now()->subYears(2);
        $this->info("Archiving logs older than {$cutoffDate->toDateString()}...");

        $count = VerificationActivityLog::where('timestamp', '<', $cutoffDate)->count();
        $this->info("Found {$count} log(s) to archive.");

        if ($count === 0) {
            $this->info('Nothing to archive.');
            return Command::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn("[DRY RUN] Would archive {$count} records. No changes made.");
            return Command::SUCCESS;
        }

        // Ensure archive table exists (create on-the-fly if not)
        if (! Schema::hasTable('audit_logs_archive')) {
            Schema::create('audit_logs_archive', function ($table) {
                $table->id();
                $table->unsignedBigInteger('original_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('beneficiary_id')->nullable();
                $table->string('activity_type');
                $table->string('activity_category', 50)->nullable();
                $table->text('activity_description');
                $table->string('ip_address', 45);
                $table->text('user_agent')->nullable();
                $table->timestamp('timestamp')->nullable();
                $table->string('status', 20);
                $table->text('remarks')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->json('request_data')->nullable();
                $table->smallInteger('response_status')->nullable();
                $table->float('execution_time')->nullable();
                $table->string('resource_type', 100)->nullable();
                $table->unsignedBigInteger('resource_id')->nullable();
                $table->string('severity', 20)->nullable();
                $table->timestamp('archived_at')->useCurrent();
                $table->index('original_id');
                $table->index('user_id');
                $table->index('timestamp');
            });
            $this->info('Created audit_logs_archive table.');
        }

        // Batch process to avoid memory exhaustion
        $archived = 0;
        VerificationActivityLog::where('timestamp', '<', $cutoffDate)
            ->chunkById(500, function ($logs) use (&$archived) {
                $rows = $logs->map(fn ($l) => array_merge(
                    $l->attributesToArray(),
                    ['original_id' => $l->id, 'archived_at' => now()->toDateTimeString()]
                ))->toArray();

                DB::table('audit_logs_archive')->insertOrIgnore($rows);
                $ids = $logs->pluck('id')->toArray();
                VerificationActivityLog::whereIn('id', $ids)->delete();
                $archived += count($ids);

                $this->line("  Archived {$archived} records so far...");
            });


        $this->info("✅ Archived {$archived} audit log(s).");
        Log::info("audit:archive — Archived {$archived} log(s) older than {$cutoffDate->toDateString()}.");

        return Command::SUCCESS;
    }
}
