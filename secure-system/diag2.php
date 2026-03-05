<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$lines = [];

// 1. DB records
$rows = \Illuminate\Support\Facades\DB::select("SELECT id, beneficiary_id, is_valid, qr_image_path FROM qr_codes");
$lines[] = "=== QR DB RECORDS ===";
foreach ($rows as $r) {
    $lines[] = "  ID={$r->id} ben={$r->beneficiary_id} valid={$r->is_valid} path={$r->qr_image_path}";
}

// 2. Beneficiaries
$bs = \Illuminate\Support\Facades\DB::select("SELECT id, bin, is_active, verification_token FROM beneficiaries");
$lines[] = "\n=== BENEFICIARIES ===";
foreach ($bs as $b) {
    $tok = $b->verification_token ? substr($b->verification_token, 0, 20).'...' : 'MISSING';
    $lines[] = "  ID={$b->id} BIN={$b->bin} active={$b->is_active} token={$tok}";
}

// 3. QR generation test (SVG first to avoid imagick requirement)
$lines[] = "\n=== QR GENERATION TESTS ===";

// Test SVG (no extension needed)
try {
    $svg = SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
        ->size(200)
        ->generate('TEST-TOKEN-SVG');
    $lines[] = "  SVG: " . strlen($svg) . " bytes — OK";
} catch (\Throwable $e) {
    $lines[] = "  SVG ERROR: " . $e->getMessage();
}

// Test PNG (needs GD or Imagick)
try {
    $png = SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')
        ->size(200)
        ->generate('TEST-TOKEN-PNG');
    $lines[] = "  PNG: " . strlen($png) . " bytes — OK";
} catch (\Throwable $e) {
    $lines[] = "  PNG ERROR: " . get_class($e) . ": " . $e->getMessage();
}

// Test EPS
try {
    $eps = SimpleSoftwareIO\QrCode\Facades\QrCode::format('eps')
        ->size(200)
        ->generate('TEST-TOKEN-EPS');
    $lines[] = "  EPS: " . strlen($eps) . " bytes — OK";
} catch (\Throwable $e) {
    $lines[] = "  EPS ERROR: " . $e->getMessage();
}

// 4. PHP extension info
$lines[] = "\n=== PHP EXTENSIONS ===";
$lines[] = "  GD: " . (extension_loaded('gd') ? 'YES' : 'NO');
$lines[] = "  Imagick: " . (extension_loaded('imagick') ? 'YES' : 'NO');
if (extension_loaded('gd')) {
    $info = gd_info();
    $lines[] = "  GD version: " . ($info['GD Version'] ?? 'unknown');
    $lines[] = "  PNG support: " . ($info['PNG Support'] ? 'YES' : 'NO');
}

// 5. Storage disk details
$lines[] = "\n=== STORAGE ===";
$qrFiles = \Illuminate\Support\Facades\Storage::disk('qr_codes')->allFiles();
$lines[] = "  qr_codes disk files: " . count($qrFiles);
foreach ($qrFiles as $f) {
    if ($f !== '.gitkeep') $lines[] = "    $f";
}

$storagePath = storage_path('app/public/qr_codes');
$lines[] = "  qr_codes root: $storagePath";
$lines[] = "  qr_codes dir exists: " . (is_dir($storagePath) ? 'YES' : 'NO');

$output = implode("\n", $lines) . "\n";
file_put_contents(__DIR__ . '/diag2.txt', $output);
echo $output;
