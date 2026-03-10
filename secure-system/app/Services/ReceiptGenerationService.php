<?php

namespace App\Services;

use App\Models\CashGrantDistribution;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ReceiptGenerationService
{
    private const DISK      = 'local';
    private const BASE_PATH = 'private/receipts';

    // ─────────────────────────────────────────────────────────────────────────
    // generateReceipt
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Generate a PDF receipt for a cash grant distribution and persist it.
     *
     * @return array{path: string, url: string}
     */
    public function generateReceipt(int $distributionId): array
    {
        $distribution = CashGrantDistribution::with([
            'beneficiary',
            'distributedBy:id,name,role,office_location',
            'approvedBy:id,name,role',
        ])->findOrFail($distributionId);

        // Receipt number — zero-padded distribution ID
        $receiptNumber = 'RCPT-' . str_pad($distributionId, 8, '0', STR_PAD_LEFT);

        // Generate QR code for verification (base64 PNG embedded in PDF)
        $verificationUrl = route('distribution.show', $distributionId, absolute: true);
        $qrBase64 = null;
        try {
            $qrImage  = QrCode::format('png')->size(120)->generate($verificationUrl);
            $qrBase64 = 'data:image/png;base64,' . base64_encode($qrImage);
        } catch (\Throwable) {
            // QR is non-critical — PDF renders without it
        }

        // Signature (if any)
        $signatureBase64 = null;
        if ($distribution->received_signature_path) {
            try {
                $disk = Storage::disk(self::DISK);
                if ($disk->exists($distribution->received_signature_path)) {
                    $sigData         = $disk->get($distribution->received_signature_path);
                    $signatureBase64 = 'data:image/png;base64,' . base64_encode($sigData);
                }
            } catch (\Throwable) {
                // Non-critical
            }
        }

        $pdf = Pdf::loadView('receipts.cash_grant', [
            'distribution'    => $distribution,
            'receiptNumber'   => $receiptNumber,
            'qrBase64'        => $qrBase64,
            'signatureBase64' => $signatureBase64,
            'verificationUrl' => $verificationUrl,
        ])->setPaper('a5', 'portrait');

        $filename = "receipt_{$receiptNumber}.pdf";
        $path     = self::BASE_PATH . '/' . $filename;

        Storage::disk(self::DISK)->put($path, $pdf->output());

        return [
            'path'           => $path,
            'url'            => route('distribution.receipt.download', $distributionId, absolute: true),
            'receipt_number' => $receiptNumber,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // streamReceipt — stream PDF to browser
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Stream the PDF receipt to the browser for inline viewing / download.
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function streamReceipt(int $distributionId): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $receiptNumber = 'RCPT-' . str_pad($distributionId, 8, '0', STR_PAD_LEFT);
        $path          = self::BASE_PATH . '/receipt_' . $receiptNumber . '.pdf';

        if (! Storage::disk(self::DISK)->exists($path)) {
            // Regenerate on the fly
            $result = $this->generateReceipt($distributionId);
            $path   = $result['path'];
        }

        return Storage::disk(self::DISK)->response($path, "receipt_{$receiptNumber}.pdf", [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
