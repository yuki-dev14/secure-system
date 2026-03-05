<?php

namespace App\Console\Commands;

use App\Models\QrCode;
use App\Models\VerificationActivityLog;
use Illuminate\Console\Command;

class CheckExpiredQRCodes extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'qr:check-expired {--dry-run : List expired QR codes without marking them invalid}';

    /**
     * The console command description.
     */
    protected $description = 'Mark expired QR codes as invalid and log expiration events';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('[SECURE] QR Code Expiration Check — ' . now()->toDateTimeString());

        // Fetch QR codes that are past expiry and still marked valid
        $expiredQrs = QrCode::where('is_valid', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->with('beneficiary')
            ->get();

        if ($expiredQrs->isEmpty()) {
            $this->info('✓ No expired QR codes found.');
            return Command::SUCCESS;
        }

        $this->warn("Found {$expiredQrs->count()} expired QR code(s).");

        if ($this->option('dry-run')) {
            $this->table(
                ['ID', 'Beneficiary BIN', 'Expired At'],
                $expiredQrs->map(fn ($qr) => [
                    $qr->id,
                    $qr->beneficiary?->bin ?? '—',
                    $qr->expires_at->toDateTimeString(),
                ])->toArray()
            );
            $this->info('Dry-run mode — no changes made.');
            return Command::SUCCESS;
        }

        $count = 0;
        foreach ($expiredQrs as $qr) {
            $qr->update(['is_valid' => false]);

            // Log expiration event (system-initiated, no user ID)
            if ($qr->beneficiary_id) {
                VerificationActivityLog::create([
                    'user_id'              => null,
                    'beneficiary_id'       => $qr->beneficiary_id,
                    'activity_type'        => 'qr_expired',
                    'activity_description' => 'QR code #' . $qr->id . ' expired and marked invalid automatically.',
                    'ip_address'           => '127.0.0.1',
                    'user_agent'           => 'Artisan/Scheduler',
                    'status'               => 'success',
                ]);
            }

            $count++;
        }

        $this->info("✓ Marked {$count} QR code(s) as invalid.");

        // Print report
        $this->table(
            ['QR ID', 'Beneficiary BIN', 'Expired At'],
            $expiredQrs->map(fn ($qr) => [
                $qr->id,
                $qr->beneficiary?->bin ?? '—',
                $qr->expires_at->toDateTimeString(),
            ])->toArray()
        );

        return Command::SUCCESS;
    }
}
