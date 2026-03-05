<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// 1. Raw DB check
echo "=== DB Check ===\n";
try {
    $rows = DB::select("SELECT id, beneficiary_id, is_valid, qr_image_path, LEFT(qr_image_url,50) as url FROM qr_codes LIMIT 10");
    echo "qr_codes rows: " . count($rows) . "\n";
    foreach ($rows as $r) {
        echo "  ID={$r->id} ben={$r->beneficiary_id} valid={$r->is_valid} path={$r->qr_image_path}\n";
    }
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// 2. QrCode model check
echo "\n=== Model Check ===\n";
try {
    $qrs = App\Models\QrCode::all(['id','beneficiary_id','is_valid','qr_image_path']);
    echo "Model loaded " . count($qrs) . " records\n";
    foreach ($qrs as $qr) {
        echo "  ID={$qr->id} ben={$qr->beneficiary_id} valid={$qr->is_valid} path={$qr->qr_image_path}\n";
    }
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// 3. Beneficiary check
echo "\n=== Beneficiary Check ===\n";
try {
    $bs = App\Models\Beneficiary::all(['id','bin','family_head_name','is_active','verification_token']);
    echo "Beneficiaries: " . count($bs) . "\n";
    foreach ($bs as $b) {
        echo "  ID={$b->id} BIN={$b->bin} active={$b->is_active} token=" . (empty($b->verification_token) ? 'MISSING' : substr($b->verification_token,0,15).'...') . "\n";
    }
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// 4. Storage check
echo "\n=== Storage Check ===\n";
$disks = ['qr_codes', 'id_cards', 'public'];
foreach ($disks as $disk) {
    try {
        $files = Storage::disk($disk)->files();
        echo "$disk: " . count($files) . " files\n";
        foreach ($files as $f) {
            if ($f !== '.gitkeep') echo "  $f\n";
        }
    } catch (\Throwable $e) {
        echo "$disk ERROR: " . $e->getMessage() . "\n";
    }
}

// 5. SimpleSoftwareIO QR test
echo "\n=== QR Generation Test ===\n";
try {
    $qrBinary = SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')->size(200)->generate('TEST-TOKEN-123');
    echo "QR binary generated: " . strlen($qrBinary) . " bytes\n";
    
    // Try saving
    $filename = 'test_qr_' . time() . '.png';
    $ok = Storage::disk('qr_codes')->put($filename, $qrBinary);
    echo "Saved to qr_codes disk: " . ($ok ? 'YES' : 'NO') . "\n";
    echo "URL: " . Storage::disk('qr_codes')->url($filename) . "\n";
    
    // Cleanup test file
    Storage::disk('qr_codes')->delete($filename);
    echo "Test file cleaned up\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo get_class($e) . "\n";
}

echo "\nDone.\n";
