<?php

namespace App\Services;

use App\Models\QrCode;
use App\Models\VerificationActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * QRCodeStorageService
 *
 * Centralises all file-system concerns for QR code images and ID card PDFs:
 *   - Saving, deleting, and URL resolution for QR images
 *   - Existence checks
 *   - Expired-file cleanup
 *   - JSON metadata tracking per QR record
 *   - Security helpers (path traversal prevention, file validation)
 *
 * All write operations use the named 'qr_codes' disk (config/filesystems.php)
 * which roots at storage/app/public/qr_codes and serves via the storage symlink.
 */
class QRCodeStorageService
{
    // Name of the Laravel storage disk used for QR images
    public const QR_DISK   = 'qr_codes';
    public const CARD_DISK = 'id_cards';

    // Allowed MIME types for QR images
    private const ALLOWED_MIME = ['image/svg+xml', 'image/png', 'image/jpeg'];

    // ─────────────────────────────────────────────────────────────────────────
    // saveQRImage — persist raw binary QR image to disk
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Save a generated QR image binary to the qr_codes disk.
     *
     * @param  string  $qrData        Raw binary PNG data from SimpleSoftwareIO
     * @param  string  $beneficiaryBin  e.g. "BIN-2026-00001"
     * @param  array   $meta          Optional extra metadata to store
     * @return array{path: string, url: string, filename: string}
     *
     * @throws \RuntimeException if the file cannot be written
     */
    public function saveQRImage(string $qrData, string $beneficiaryBin, array $meta = []): array
    {
        // Sanitise BIN for use in a filename (strip anything not alphanumeric/dash)
        $safeBin  = preg_replace('/[^A-Za-z0-9\-]/', '_', $beneficiaryBin);
        $filename = 'qr_' . $safeBin . '_' . now()->timestamp . '.svg';

        $written = Storage::disk(self::QR_DISK)->put($filename, $qrData);

        if (! $written) {
            throw new \RuntimeException("Failed to write QR image file: {$filename}");
        }

        $url = Storage::disk(self::QR_DISK)->url($filename);

        Log::info('[QRCodeStorage] QR image saved', [
            'filename' => $filename,
            'disk'     => self::QR_DISK,
            'size'     => strlen($qrData),
            'url'      => $url,
        ]);

        return [
            'filename' => $filename,
            'path'     => $filename,   // Relative to disk root — store this in DB
            'url'      => $url,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // deleteQRImage — remove an image file from disk
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Delete a QR code image from the storage disk.
     *
     * @param  string  $filePath  Relative path stored in qr_codes.qr_image_path
     * @return bool    true if deleted, false if file did not exist
     */
    public function deleteQRImage(string $filePath): bool
    {
        $safePath = $this->sanitisePath($filePath);

        if (! Storage::disk(self::QR_DISK)->exists($safePath)) {
            Log::warning('[QRCodeStorage] Delete skipped — file not found', ['path' => $safePath]);
            return false;
        }

        $deleted = Storage::disk(self::QR_DISK)->delete($safePath);

        if ($deleted) {
            Log::info('[QRCodeStorage] QR image deleted', [
                'path'       => $safePath,
                'deleted_by' => Auth::id() ?? 'system',
            ]);
        } else {
            Log::error('[QRCodeStorage] Failed to delete QR image', ['path' => $safePath]);
        }

        return $deleted;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // getQRImageUrl — resolve a stored path to a public URL
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Return the public URL for a stored QR image, or null if it doesn't exist.
     *
     * @param  string  $filePath  Relative path stored in qr_codes.qr_image_path
     */
    public function getQRImageUrl(string $filePath): ?string
    {
        $safePath = $this->sanitisePath($filePath);

        if (! Storage::disk(self::QR_DISK)->exists($safePath)) {
            Log::warning('[QRCodeStorage] URL requested for missing file', ['path' => $safePath]);
            return null;
        }

        return Storage::disk(self::QR_DISK)->url($safePath);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // checkQRExists — does a valid QR record (+ file) exist for a beneficiary?
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Check whether an active QR code (both DB record AND file on disk) exists.
     *
     * @param  int  $beneficiaryId
     */
    public function checkQRExists(int $beneficiaryId): bool
    {
        $qr = QrCode::where('beneficiary_id', $beneficiaryId)
            ->where('is_valid', true)
            ->latest('generated_at')
            ->first();

        if (! $qr) {
            return false;
        }

        return Storage::disk(self::QR_DISK)->exists($this->sanitisePath($qr->qr_image_path));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // cleanupExpiredQR — delete files for expired QR codes
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Find all QR DB records where expires_at < now() and is_valid = true,
     * delete their physical files, mark them invalid, and log each action.
     * Database records are KEPT for audit history.
     *
     * @return array{expired_count: int, files_deleted: int, errors: string[]}
     */
    public function cleanupExpiredQR(): array
    {
        $expired = QrCode::where('is_valid', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->with('beneficiary:id,bin')
            ->get();

        $filesDeleted = 0;
        $errors       = [];

        foreach ($expired as $qr) {
            try {
                // 1. Delete physical file if it exists
                $safePath = $this->sanitisePath($qr->qr_image_path);
                if (Storage::disk(self::QR_DISK)->exists($safePath)) {
                    Storage::disk(self::QR_DISK)->delete($safePath);
                    $filesDeleted++;
                }

                // 2. Mark the DB record invalid (keep for history)
                $qr->update(['is_valid' => false]);

                // 3. Log expiration event
                VerificationActivityLog::create([
                    'user_id'              => null,
                    'beneficiary_id'       => $qr->beneficiary_id,
                    'activity_type'        => 'qr_expired',
                    'activity_description' => 'QR code #' . $qr->id . ' expired; image file deleted during cleanup. BIN: ' . ($qr->beneficiary?->bin ?? 'N/A'),
                    'ip_address'           => '127.0.0.1',
                    'user_agent'           => 'Artisan/Scheduler',
                    'status'               => 'success',
                ]);
            } catch (\Throwable $e) {
                $errors[] = "QR #{$qr->id}: " . $e->getMessage();
                Log::error('[QRCodeStorage] Cleanup error', [
                    'qr_id' => $qr->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('[QRCodeStorage] Expired QR cleanup complete', [
            'total_expired' => $expired->count(),
            'files_deleted' => $filesDeleted,
            'errors'        => count($errors),
        ]);

        return [
            'expired_count' => $expired->count(),
            'files_deleted' => $filesDeleted,
            'errors'        => $errors,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // validateQRImageFile — security check before serving a file
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Verify that a requested file path is safe to serve.
     * Prevents directory traversal attacks.
     *
     * @param  string  $filePath  Path coming from request input
     * @return bool    true = safe to serve | false = reject
     */
    public function validateQRImageFile(string $filePath): bool
    {
        // Must not contain path traversal sequences
        if (str_contains($filePath, '..') || str_contains($filePath, '/') || str_contains($filePath, '\\')) {
            Log::warning('[QRCodeStorage] Path traversal attempt blocked', ['path' => $filePath]);
            return false;
        }

        // Must match expected filename pattern: qr_{BIN}_{timestamp}.svg or .png
        if (! preg_match('/^qr_[A-Za-z0-9_\-]+_\d+\.(svg|png)$/', $filePath)) {
            Log::warning('[QRCodeStorage] Invalid QR filename pattern', ['path' => $filePath]);
            return false;
        }

        // Must exist on disk
        if (! Storage::disk(self::QR_DISK)->exists($filePath)) {
            return false;
        }

        return true;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // serveQRImage — stream a QR image through the controller (not direct URL)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build an HTTP response that streams the QR image.
     * Call this from QRCodeController to serve files securely.
     *
     * @param  string  $filename      Just the filename (no parent dirs)
     * @param  int     $beneficiaryId Used for access logging
     * @return \Illuminate\Http\Response
     */
    public function serveQRImage(string $filename, int $beneficiaryId): \Illuminate\Http\Response
    {
        if (! $this->validateQRImageFile($filename)) {
            abort(404, 'QR image not found or invalid path.');
        }

        // Log the access attempt
        VerificationActivityLog::create([
            'user_id'              => Auth::id(),
            'beneficiary_id'       => $beneficiaryId,
            'activity_type'        => 'qr_file_access',
            'activity_description' => "QR image file '{$filename}' accessed by " . (Auth::user()?->name ?? 'guest'),
            'ip_address'           => request()->ip(),
            'user_agent'           => request()->userAgent(),
            'status'               => 'success',
        ]);

        $contents = Storage::disk(self::QR_DISK)->get($filename);

        // Determine content type based on file extension
        $ext         = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $contentType = $ext === 'svg' ? 'image/svg+xml' : 'image/png';

        return response($contents, 200, [
            'Content-Type'           => $contentType,
            'Content-Disposition'    => 'inline; filename="' . $filename . '"',
            'Cache-Control'          => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // buildMetadata — construct the JSON metadata blob for a QR record
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build a structured metadata array to be stored alongside a QR record.
     * Call this during generate/regenerate to capture generation context.
     *
     * @param  string  $method   'generate' | 'regenerate'
     * @param  array   $extra    Any additional fields to merge in
     * @return array
     */
    public function buildMetadata(string $method = 'generate', array $extra = []): array
    {
        $user = Auth::user();

        return array_merge([
            'generated_by'      => $user?->id,
            'operator_name'     => $user?->name,
            'operator_role'     => $user?->role,
            'generation_method' => $method,          // 'generate' | 'regenerate'
            'generation_time'   => now()->toIso8601String(),
            'timezone'          => 'Asia/Manila',
            'app_version'       => config('app.version', '1.0.0'),
            'ip_address'        => request()->ip(),
            'user_agent'        => request()->userAgent(),
            'download_count'    => 0,
            'access_log'        => [],
        ], $extra);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // incrementDownloadCount — track how many times a QR file was downloaded
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Read the JSON metadata on a QR record and increment the download counter.
     *
     * @param  QrCode  $qr
     */
    public function incrementDownloadCount(QrCode $qr): void
    {
        $meta = $this->decodeMeta($qr);

        $meta['download_count'] = ($meta['download_count'] ?? 0) + 1;

        // Append to access log (keep only last 20 entries to avoid bloat)
        $meta['access_log'][] = [
            'at'         => now()->toIso8601String(),
            'user_id'    => Auth::id(),
            'ip_address' => request()->ip(),
            'action'     => 'download',
        ];
        $meta['access_log'] = array_slice($meta['access_log'], -20);

        $qr->update(['metadata' => json_encode($meta)]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // getDiskInfo — return disk usage stats for the admin dashboard
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Return file count and total size (bytes) for the qr_codes disk.
     *
     * @return array{file_count: int, total_bytes: int, total_human: string}
     */
    public function getDiskInfo(): array
    {
        $files      = Storage::disk(self::QR_DISK)->allFiles();
        $totalBytes = 0;

        foreach ($files as $file) {
            $totalBytes += Storage::disk(self::QR_DISK)->size($file);
        }

        return [
            'file_count'  => count($files),
            'total_bytes' => $totalBytes,
            'total_human' => $this->formatBytes($totalBytes),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internals
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Strip directory components from a path — prevents traversal.
     * Only a plain filename (no slashes, no dots) is ever used.
     */
    private function sanitisePath(string $path): string
    {
        return basename($path);
    }

    /**
     * Return the metadata array from a QrCode model.
     * The 'metadata' attribute is already cast to array by the model,
     * so we just guard against null/empty.
     */
    private function decodeMeta(QrCode $qr): array
    {
        $meta = $qr->metadata;

        if (empty($meta)) {
            return [];
        }

        // Already an array due to model cast — return directly
        if (is_array($meta)) {
            return $meta;
        }

        // Fallback: if stored as a raw JSON string for some reason
        $decoded = json_decode((string) $meta, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Human-readable byte size.
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1_048_576) return round($bytes / 1_048_576, 2) . ' MB';
        if ($bytes >= 1_024)    return round($bytes / 1_024, 2) . ' KB';
        return $bytes . ' B';
    }
}
