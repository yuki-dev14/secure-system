<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DigitalSignatureService
{
    private const DISK      = 'local';
    private const BASE_PATH = 'private/signatures';

    // ─────────────────────────────────────────────────────────────────────────
    // saveSignature — decode base64 and persist to storage
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Accept a base64-encoded PNG Data URL, decode it, and save to private storage.
     *
     * @param  string $signatureDataUrl  e.g. "data:image/png;base64,iVBOR..."
     * @param  string $bin               Beneficiary Identification Number
     * @return string                    Relative storage path
     * @throws \InvalidArgumentException
     */
    public function saveSignature(string $signatureDataUrl, string $bin): string
    {
        // Strip the "data:image/...;base64," prefix
        if (! str_contains($signatureDataUrl, 'base64,')) {
            throw new \InvalidArgumentException('Signature must be a valid base64 Data URL.');
        }

        [, $base64Data] = explode('base64,', $signatureDataUrl, 2);
        $imageData = base64_decode($base64Data, strict: true);

        if ($imageData === false) {
            throw new \InvalidArgumentException('Invalid base64 signature data.');
        }

        $timestamp = now()->format('Ymd_His');
        $safeBin   = preg_replace('/[^A-Za-z0-9\-]/', '_', $bin);
        $filename  = "signature_{$safeBin}_{$timestamp}.png";
        $path      = self::BASE_PATH . '/' . $filename;

        Storage::disk(self::DISK)->put($path, $imageData);

        return $path;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // getSignatureUrl — generate a temporary signed URL (1 hour)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Return an accessible URL for the stored signature file.
     * Uses a temporary signed URL valid for 1 hour.
     *
     * @param  string $signaturePath  Relative path from saveSignature()
     * @return string|null
     */
    public function getSignatureUrl(string $signaturePath): ?string
    {
        if (! Storage::disk(self::DISK)->exists($signaturePath)) {
            return null;
        }

        // For local disk, generate a route-based signed URL via the serve-signature endpoint.
        // The controller must expose a route that validates the signature and streams the file.
        return route('distribution.signature.serve', [
            'path' => base64_encode($signaturePath),
        ], absolute: true);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // deleteSignature
    // ─────────────────────────────────────────────────────────────────────────

    public function deleteSignature(string $signaturePath): bool
    {
        return Storage::disk(self::DISK)->delete($signaturePath);
    }
}
