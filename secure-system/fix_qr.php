<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Beneficiary;
use App\Models\QrCode;
use App\Services\QRCodeStorageService;
use SimpleSoftwareIO\QrCode\Facades\QrCode as QrCodeFacade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

$out = [];
$out[] = "=== QR Code Status Detail ===";
$out[] = "";

// Check existing QR records
$qrCodes = QrCode::with('beneficiary')->get();
foreach ($qrCodes as $qr) {
    $bin = $qr->beneficiary?->bin ?? '(no beneficiary)';
    $path = $qr->qr_image_path ?? 'NULL';
    $fileExists = false;
    if ($path && $path !== 'NULL') {
        $fileExists = Storage::disk('qr_codes')->exists(basename($path));
    }
    $out[] = sprintf(
        "QR #%d | BIN: %s | valid: %s | file: %s | exists: %s",
        $qr->id,
        $bin,
        $qr->is_valid ? 'Y' : 'N',
        basename($path),
        $fileExists ? 'YES' : 'NO'
    );
}

$out[] = "";
$out[] = "=== Fixing Missing QR Files ===";
$out[] = "";

$storageService = app(QRCodeStorageService::class);
$beneficiaries = Beneficiary::where('is_active', true)->get();

foreach ($beneficiaries as $b) {
    // Ensure token
    if (!$b->verification_token) {
        $b->verification_token = Str::uuid()->toString();
        $b->save();
        $out[] = "  → Token set for {$b->bin}";
    }

    $activeQr = QrCode::where('beneficiary_id', $b->id)
        ->where('is_valid', true)
        ->latest()
        ->first();

    $fileOk = false;
    if ($activeQr && $activeQr->qr_image_path) {
        $fileOk = Storage::disk('qr_codes')->exists(basename($activeQr->qr_image_path));
    }

    if (!$fileOk) {
        $out[] = "  FIXING {$b->bin}...";
        
        // Invalidate stale DB records
        QrCode::where('beneficiary_id', $b->id)
            ->where('is_valid', true)
            ->update(['is_valid' => false]);
        
        try {
            $qrBinary = QrCodeFacade::format('png')
                ->size(300)
                ->errorCorrection('M')
                ->generate($b->verification_token);
            
            $saved = $storageService->saveQRImage($qrBinary, $b->bin);
            
            QrCode::create([
                'beneficiary_id'     => $b->id,
                'verification_token' => $b->verification_token,
                'qr_image_path'      => $saved['path'],
                'qr_image_url'       => $saved['url'],
                'generated_at'       => now(),
                'expires_at'         => now()->addYear(),
                'is_valid'           => true,
                'metadata'           => json_encode([
                    'generation_method' => 'fix_script',
                    'generated_at'      => now()->toIso8601String(),
                    'download_count'    => 0,
                    'access_log'        => [],
                ]),
            ]);
            
            $out[] = "  ✓ Generated: {$saved['filename']}";
            $out[] = "  ✓ URL: {$saved['url']}";
            
        } catch (\Throwable $e) {
            $out[] = "  ✗ Failed: " . $e->getMessage();
        }
    } else {
        $out[] = "  OK {$b->bin}: " . basename($activeQr->qr_image_path);
    }
}

$out[] = "";
$out[] = "=== QR Files in Storage ===";
$files = Storage::disk('qr_codes')->files();
if (empty($files)) {
    $out[] = "  (no files found)";
} else {
    foreach ($files as $f) {
        $size = Storage::disk('qr_codes')->size($f);
        $out[] = "  $f  ($size bytes)";
    }
}

$out[] = "";
$out[] = "Total beneficiaries: " . Beneficiary::count();
$out[] = "Active QR codes: " . QrCode::where('is_valid', true)->count();
$out[] = "";

// Test artisan commands
$out[] = "=== Artisan Command Check ===";
$cmds = ['qr:check-expired', 'requirements:process-expirations'];
foreach ($cmds as $cmd) {
    try {
        $code = \Illuminate\Support\Facades\Artisan::call($cmd, ['--help' => true]);
        $out[] = "  ✓ $cmd — registered (exit $code)";
    } catch (\Throwable $e) {
        $out[] = "  ✗ $cmd — " . $e->getMessage();
    }
}

$out[] = "";
$out[] = "✓ Done!";

$result = implode("\n", $out);
echo $result;
file_put_contents(__DIR__ . '/test_output.txt', $result);
