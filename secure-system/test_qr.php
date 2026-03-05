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

echo "=== SECURE System Test Suite ===\n\n";

// 1. Check DB connection
echo "1. DATABASE CHECK\n";
try {
    $count = Beneficiary::count();
    echo "   ✓ DB Connected. Beneficiary count: $count\n";
} catch (\Throwable $e) {
    echo "   ✗ DB Error: " . $e->getMessage() . "\n";
    exit(1);
}

// 2. Show first beneficiary
echo "\n2. BENEFICIARY CHECK\n";
$b = Beneficiary::first();
if (!$b) {
    echo "   ✗ No beneficiaries found. Creating a test one...\n";
    $b = Beneficiary::create([
        'bin'              => 'BIN-TEST-' . now()->timestamp,
        'family_head_name' => 'Test Beneficiary',
        'municipality'     => 'Test Municipality',
        'province'         => 'Test Province',
        'is_active'        => true,
        'verification_token' => \Illuminate\Support\Str::uuid()->toString(),
    ]);
    echo "   ✓ Test beneficiary created with ID: {$b->id}, BIN: {$b->bin}\n";
} else {
    echo "   ✓ Found: {$b->family_head_name} (BIN: {$b->bin})\n";
    echo "   ✓ Active: " . ($b->is_active ? 'YES' : 'NO') . "\n";
    echo "   ✓ Token: " . ($b->verification_token ? substr($b->verification_token, 0, 20) . '...' : 'NOT SET') . "\n";
    
    if (!$b->verification_token) {
        echo "   → Setting verification_token...\n";
        $b->verification_token = \Illuminate\Support\Str::uuid()->toString();
        $b->save();
        echo "   ✓ Token set: " . substr($b->verification_token, 0, 20) . "...\n";
    }
}

// 3. Check storage disks
echo "\n3. STORAGE DISK CHECK\n";
$qrDiskPath = storage_path('app/public/qr_codes');
$idCardPath = storage_path('app/public/id_cards');
$docPath    = storage_path('app/private/documents');

$dirs = [
    'qr_codes disk root' => $qrDiskPath,
    'id_cards disk root' => $idCardPath,
    'private/documents'  => $docPath,
];

foreach ($dirs as $label => $path) {
    if (is_dir($path)) {
        echo "   ✓ {$label}: EXISTS\n";
    } else {
        mkdir($path, 0775, true);
        echo "   ✓ {$label}: CREATED\n";
    }
}

// Check symlink
$symlinkPath = public_path('storage');
echo "   " . (file_exists($symlinkPath) ? '✓' : '✗') . " storage symlink: " . ($symlinkPath) . "\n";

// 4. Test QR Generation (SimpleSoftwareIO)
echo "\n4. QR CODE GENERATION TEST\n";
try {
    $token = $b->verification_token;
    $qrBinary = QrCodeFacade::format('png')->size(300)->errorCorrection('M')->generate($token);
    echo "   ✓ QR binary generated: " . strlen($qrBinary) . " bytes\n";
    
    // Save via Storage disk
    $storageService = app(QRCodeStorageService::class);
    $saved = $storageService->saveQRImage($qrBinary, $b->bin);
    echo "   ✓ Saved to disk: {$saved['filename']}\n";
    echo "   ✓ URL: {$saved['url']}\n";
    echo "   ✓ File exists on disk: " . (Storage::disk('qr_codes')->exists($saved['filename']) ? 'YES' : 'NO') . "\n";
    
    // Check if QR record already exists
    $existingQr = QrCode::where('beneficiary_id', $b->id)->where('is_valid', true)->first();
    if ($existingQr) {
        echo "   ℹ Active QR record already exists (ID: {$existingQr->id}) — skipping DB insert\n";
    } else {
        $qrRecord = QrCode::create([
            'beneficiary_id'     => $b->id,
            'verification_token' => $token,
            'qr_image_path'      => $saved['path'],
            'qr_image_url'       => $saved['url'],
            'generated_at'       => now(),
            'expires_at'         => now()->addYear(),
            'is_valid'           => true,
            'metadata'           => $storageService->buildMetadata('generate'),
        ]);
        echo "   ✓ QR record saved to DB (ID: {$qrRecord->id})\n";
    }
} catch (\Throwable $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    echo "   Trace: " . $e->getTraceAsString() . "\n";
}

// 5. Test artisan commands exist
echo "\n5. ARTISAN COMMAND CHECK\n";
$commands = [
    'qr:check-expired',
    'requirements:process-expirations',
];
foreach ($commands as $cmd) {
    try {
        \Illuminate\Support\Facades\Artisan::call($cmd, ['--help' => true]);
        echo "   ✓ {$cmd}: registered\n";
    } catch (\Throwable $e) {
        echo "   ✗ {$cmd}: " . $e->getMessage() . "\n";
    }
}

// 6. Summary
echo "\n=== SUMMARY ===\n";
$qrCount = QrCode::where('is_valid', true)->count();
$beneficiaryCount = Beneficiary::count();
echo "Total beneficiaries: $beneficiaryCount\n";
echo "Active QR codes:     $qrCount\n";
echo "\nCheck the QR image at: " . storage_path('app/public/qr_codes') . "\n";
echo "\n✓ All tests complete!\n";
