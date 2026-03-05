<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class FileSecurityService
{
    /**
     * Allowed MIME types mapped to their valid extensions.
     */
    private const ALLOWED_MIME_MAP = [
        'application/pdf'                                                      => ['pdf'],
        'image/jpeg'                                                           => ['jpg', 'jpeg'],
        'image/png'                                                            => ['png'],
        'application/msword'                                                   => ['doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
    ];

    /**
     * Dangerous file signatures (magic bytes) to block.
     * Format: hex string prefix.
     */
    private const DANGEROUS_SIGNATURES = [
        '4d5a',         // PE/EXE: MZ
        '504b0304',     // ZIP-based (used by macro-enabled Office formats check separately)
        '7f454c46',     // ELF Linux executable
        'd0cf11e0',     // Old Office compound doc — allowed only for .doc
        'cafebabe',     // Java class file
        '23212f',       // Shebang script
    ];

    /**
     * Max file size: 5 MB
     */
    private const MAX_SIZE_BYTES = 5 * 1024 * 1024;

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Full file validation pipeline.
     *
     * @return array{is_valid: bool, errors: string[]}
     */
    public function validateFile(UploadedFile $file): array
    {
        $errors = [];

        // 1. Size check
        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            $errors[] = 'File exceeds maximum allowed size of 5 MB.';
        }

        // 2. MIME type must be in allowed list
        $mime = $file->getMimeType();
        if (! array_key_exists($mime, self::ALLOWED_MIME_MAP)) {
            $errors[] = "File type '{$mime}' is not allowed.";
            // We cannot do extension check if MIME is already wrong
            return ['is_valid' => false, 'errors' => $errors];
        }

        // 3. Extension must match detected MIME
        $ext             = strtolower($file->getClientOriginalExtension());
        $allowedForMime  = self::ALLOWED_MIME_MAP[$mime];
        if (! in_array($ext, $allowedForMime, true)) {
            $errors[] = "File extension '.{$ext}' does not match the detected type '{$mime}'.";
        }

        // 4. Magic-byte scan
        $magicResult = $this->scanForMalware($file->getRealPath());
        if (! $magicResult['is_safe']) {
            $errors[] = $magicResult['reason'] ?? 'File failed security scan.';
        }

        return [
            'is_valid' => empty($errors),
            'errors'   => $errors,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Sanitize original filename to prevent directory traversal and injection.
     */
    public function sanitizeFilename(string $originalName): string
    {
        // Strip directory components
        $name = basename($originalName);

        // Remove special characters except alphanumeric, dashes, underscores, dots
        $name = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $name);

        // Prevent double extensions / null byte injection
        $name = str_replace(["\0", '..', '//', '\\\\'], '_', $name);

        // Limit length
        if (strlen($name) > 100) {
            $ext  = pathinfo($name, PATHINFO_EXTENSION);
            $base = substr(pathinfo($name, PATHINFO_FILENAME), 0, 90);
            $name = $base . '.' . $ext;
        }

        return $name ?: 'file';
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Basic file safety check using magic-byte inspection.
     *
     * @return array{is_safe: bool, reason: string|null}
     */
    public function scanForMalware(string $filePath): array
    {
        try {
            $handle = fopen($filePath, 'rb');
            if (! $handle) {
                return ['is_safe' => false, 'reason' => 'Cannot read file for security scan.'];
            }

            // Read first 8 bytes for magic number check
            $bytes  = fread($handle, 8);
            fclose($handle);

            $hex = bin2hex($bytes ?? '');

            foreach (self::DANGEROUS_SIGNATURES as $signature) {
                if (str_starts_with($hex, $signature)) {
                    // Special case: old Office compound doc (.doc) is legitimate
                    if ($signature === 'd0cf11e0') {
                        // Allow it — it's a valid .doc binary
                        continue;
                    }
                    Log::warning("FileSecurityService: Dangerous magic bytes detected [{$signature}] in file: {$filePath}");
                    return [
                        'is_safe' => false,
                        'reason'  => 'File contains potentially dangerous content and cannot be accepted.',
                    ];
                }
            }

            // Check for PHP/script tags embedded in images (basic polyglot check)
            $handle = fopen($filePath, 'rb');
            if ($handle) {
                $chunk = fread($handle, 4096);
                fclose($handle);
                if ($chunk && (
                    str_contains($chunk, '<?php') ||
                    str_contains($chunk, '<?=')   ||
                    str_contains($chunk, '<script')
                )) {
                    Log::warning("FileSecurityService: Script tags detected in file: {$filePath}");
                    return [
                        'is_safe' => false,
                        'reason'  => 'File contains embedded scripts and cannot be accepted.',
                    ];
                }
            }

            return ['is_safe' => true, 'reason' => null];

        } catch (\Throwable $e) {
            Log::error('FileSecurityService::scanForMalware failed: ' . $e->getMessage());
            // Fail safe — if we cannot scan, reject
            return ['is_safe' => false, 'reason' => 'Security scan could not be completed.'];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Generate a safe, unique storage filename.
     */
    public function generateSecureFilename(string $type, int|string $beneficiaryId, string $extension): string
    {
        $ext       = strtolower(ltrim($extension, '.'));
        $timestamp = now()->format('YmdHis');
        $random    = substr(bin2hex(random_bytes(4)), 0, 8);

        return "{$type}_{$beneficiaryId}_{$timestamp}_{$random}.{$ext}";
    }
}
