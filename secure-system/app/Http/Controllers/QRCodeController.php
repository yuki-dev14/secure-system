<?php

namespace App\Http\Controllers;

use App\Models\Beneficiary;
use App\Models\QrCode;
use App\Models\VerificationActivityLog;
use App\Services\QRCodeStorageService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use SimpleSoftwareIO\QrCode\Facades\QrCode as QrCodeFacade;

class QRCodeController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // Constructor — inject QRCodeStorageService
    // ─────────────────────────────────────────────────────────────────────────

    public function __construct(private QRCodeStorageService $storage) {}

    // ─────────────────────────────────────────────────────────────────────────
    // showPage — render the Inertia QR management page
    // ─────────────────────────────────────────────────────────────────────────

    public function showPage($beneficiaryId)
    {
        $beneficiary = Beneficiary::findOrFail($beneficiaryId);
        abort_if(! Auth::user()->hasPermission('beneficiary.view'), 403);

        $activeQrCode = QrCode::where('beneficiary_id', $beneficiary->id)
            ->where('is_valid', true)
            ->latest('generated_at')
            ->first();

        return Inertia::render('QRCode/Show', [
            'beneficiary'  => $beneficiary,
            'activeQrCode' => $activeQrCode ? $this->formatQrCode($activeQrCode) : null,
            'canGenerate'  => Auth::user()->hasRole(['Field Officer', 'Administrator']),
            'canRegenerate'=> Auth::user()->isAdministrator(),
            'diskInfo'     => $this->storage->getDiskInfo(),
        ]);
    }


    public function generate($beneficiaryId)
    {
        $beneficiary = Beneficiary::findOrFail($beneficiaryId);

        // Validation gates
        if (! $beneficiary->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Beneficiary is inactive and cannot have a QR code generated.',
            ], 422);
        }

        if (! $beneficiary->verification_token) {
            return response()->json([
                'success' => false,
                'message' => 'Beneficiary does not have a verification token.',
            ], 422);
        }

        // Check if an active valid QR already exists
        $existing = QrCode::where('beneficiary_id', $beneficiary->id)
            ->where('is_valid', true)
            ->latest()
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'An active QR code already exists for this beneficiary. Use regenerate to create a new one.',
                'qr_code' => $this->formatQrCode($existing),
            ], 409);
        }

        DB::beginTransaction();
        try {
            // Generate QR SVG via SimpleSoftwareIO (SVG works without Imagick/GD PNG renderer)
            $qrBinary = QrCodeFacade::format('svg')
                ->size(300)
                ->errorCorrection('M')
                ->generate($beneficiary->verification_token);

            // Save via storage service (handles disk, filename, error)
            $saved = $this->storage->saveQRImage($qrBinary, $beneficiary->bin);

            // Build metadata blob
            $meta = $this->storage->buildMetadata('generate');

            // Persist QR record
            $qrRecord = QrCode::create([
                'beneficiary_id'     => $beneficiary->id,
                'verification_token' => $beneficiary->verification_token,
                'qr_image_path'      => $saved['path'],
                'qr_image_url'       => $saved['url'],
                'generated_at'       => now(),
                'expires_at'         => Carbon::now()->addYear(),
                'is_valid'           => true,
                'metadata'           => $meta,
            ]);

            // Activity log
            VerificationActivityLog::create([
                'user_id'              => Auth::id(),
                'beneficiary_id'       => $beneficiary->id,
                'activity_type'        => 'qr_generate',
                'activity_description' => 'QR code generated for beneficiary ' . $beneficiary->bin . ' by ' . Auth::user()->name,
                'ip_address'           => request()->ip(),
                'user_agent'           => request()->userAgent(),
                'status'               => 'success',
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'QR code generation failed: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'QR code generated successfully.',
            'qr_code' => $this->formatQrCode($qrRecord),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // regenerate — invalidate old QR and create a fresh one (Admin only)
    // ─────────────────────────────────────────────────────────────────────────

    public function regenerate(Request $request, $beneficiaryId)
    {
        // Authorization gate
        abort_if(! Auth::user()->isAdministrator(), 403, 'Only Administrators can regenerate QR codes.');

        $validated = $request->validate([
            'regeneration_reason' => 'required|string|max:500',
        ]);

        $beneficiary = Beneficiary::findOrFail($beneficiaryId);

        if (! $beneficiary->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot regenerate QR code for an inactive beneficiary.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Invalidate all currently valid QR codes
            QrCode::where('beneficiary_id', $beneficiary->id)
                ->where('is_valid', true)
                ->update(['is_valid' => false]);

            // Generate new QR SVG — keep same verification_token
            $qrBinary = QrCodeFacade::format('svg')
                ->size(300)
                ->errorCorrection('M')
                ->generate($beneficiary->verification_token);

            // Save via storage service
            $saved = $this->storage->saveQRImage($qrBinary, $beneficiary->bin);

            // Build metadata blob with regeneration context
            $meta = $this->storage->buildMetadata('regenerate', [
                'regeneration_reason' => $validated['regeneration_reason'],
            ]);

            $qrRecord = QrCode::create([
                'beneficiary_id'     => $beneficiary->id,
                'verification_token' => $beneficiary->verification_token,
                'qr_image_path'      => $saved['path'],
                'qr_image_url'       => $saved['url'],
                'generated_at'       => now(),
                'expires_at'         => Carbon::now()->addYear(),
                'is_valid'           => true,
                'regenerated_at'     => now(),
                'regenerated_reason' => $validated['regeneration_reason'],
                'metadata'           => $meta,
            ]);

            // Activity log
            VerificationActivityLog::create([
                'user_id'              => Auth::id(),
                'beneficiary_id'       => $beneficiary->id,
                'activity_type'        => 'qr_regenerate',
                'activity_description' => 'QR code regenerated for ' . $beneficiary->bin . ' by ' . Auth::user()->name . '. Reason: ' . $validated['regeneration_reason'],
                'ip_address'           => $request->ip(),
                'user_agent'           => $request->userAgent(),
                'status'               => 'success',
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'QR code regeneration failed: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'QR code regenerated successfully.',
            'qr_code' => $this->formatQrCode($qrRecord),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // show — get active QR code for beneficiary
    // ─────────────────────────────────────────────────────────────────────────

    public function show($beneficiaryId)
    {
        $beneficiary = Beneficiary::findOrFail($beneficiaryId);

        $qrCode = QrCode::where('beneficiary_id', $beneficiary->id)
            ->where('is_valid', true)
            ->latest('generated_at')
            ->first();

        if (! $qrCode) {
            return response()->json([
                'success' => false,
                'message' => 'No active QR code found for this beneficiary.',
                'qr_code' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'qr_code' => $this->formatQrCode($qrCode),
            'beneficiary' => [
                'id'               => $beneficiary->id,
                'bin'              => $beneficiary->bin,
                'family_head_name' => $beneficiary->family_head_name,
                'is_active'        => $beneficiary->is_active,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // validateQR — verify a token is valid and active
    // ─────────────────────────────────────────────────────────────────────────

    public function validateQR($token)
    {
        $qrCode = QrCode::where('verification_token', $token)
            ->where('is_valid', true)
            ->latest('generated_at')
            ->first();

        if (! $qrCode) {
            return response()->json([
                'valid'   => false,
                'message' => 'Invalid or revoked QR code token.',
            ]);
        }

        // Expiration check
        if ($qrCode->expires_at && $qrCode->expires_at->isPast()) {
            return response()->json([
                'valid'   => false,
                'message' => 'QR code has expired.',
                'expired_at' => $qrCode->expires_at->toIso8601String(),
            ]);
        }

        // Beneficiary active check
        $beneficiary = $qrCode->beneficiary;
        if (! $beneficiary || ! $beneficiary->is_active) {
            return response()->json([
                'valid'   => false,
                'message' => 'The beneficiary linked to this QR code is no longer active.',
            ]);
        }

        return response()->json([
            'valid'   => true,
            'message' => 'QR code is valid.',
            'qr_code' => $this->formatQrCode($qrCode),
            'beneficiary' => [
                'id'               => $beneficiary->id,
                'bin'              => $beneficiary->bin,
                'family_head_name' => $beneficiary->family_head_name,
                'municipality'     => $beneficiary->municipality,
                'province'         => $beneficiary->province,
                'is_active'        => $beneficiary->is_active,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // getHistory — all QR codes for a beneficiary
    // ─────────────────────────────────────────────────────────────────────────

    public function getHistory($beneficiaryId)
    {
        $beneficiary = Beneficiary::findOrFail($beneficiaryId);

        $history = QrCode::where('beneficiary_id', $beneficiary->id)
            ->orderByDesc('generated_at')
            ->get()
            ->map(function (QrCode $qr) {
                return $this->formatQrCode($qr);
            });

        return response()->json([
            'success' => true,
            'history' => $history,
            'total'   => $history->count(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // generateCard — create a PDF ID card using DOMPDF
    // ─────────────────────────────────────────────────────────────────────────

    public function generateCard($beneficiaryId)
    {
        $beneficiary = Beneficiary::findOrFail($beneficiaryId);

        abort_if(! Auth::user()->hasPermission('beneficiary.view'), 403);

        $qrCode = QrCode::where('beneficiary_id', $beneficiary->id)
            ->where('is_valid', true)
            ->latest('generated_at')
            ->first();

        if (! $qrCode) {
            return response()->json([
                'success' => false,
                'message' => 'No active QR code found. Generate a QR code first.',
            ], 422);
        }

        try {
            // Read QR image and base64-encode for embedding in PDF
            $qrFilename  = basename($qrCode->qr_image_path);
            $qrImageData = Storage::disk('qr_codes')->get($qrFilename);
            $ext         = strtolower(pathinfo($qrFilename, PATHINFO_EXTENSION));
            $mime        = $ext === 'svg' ? 'image/svg+xml' : 'image/png';
            $qrBase64    = 'data:' . $mime . ';base64,' . base64_encode($qrImageData);

            $cardData = [
                'beneficiary' => $beneficiary,
                'qrCode'      => $qrCode,
                'qrBase64'    => $qrBase64,
                'issueDate'   => now()->format('M d, Y'),
                'expiryDate'  => $qrCode->expires_at ? $qrCode->expires_at->format('M d, Y') : 'N/A',
                'cardNumber'  => 'CRD-' . str_pad($beneficiary->id, 6, '0', STR_PAD_LEFT),
            ];

            $pdf = app('dompdf.wrapper');
            $pdf->setPaper([0, 0, 242.64, 153.07], 'landscape'); // 85.6mm x 53.98mm at 72dpi
            $pdf->loadView('pdf.id-card', $cardData);

            // Save card to storage
            Storage::disk('id_cards')->put($cardFilename = 'card_' . $beneficiary->bin . '_' . now()->timestamp . '.pdf', $pdf->output());
            $cardFilename = 'card_' . $beneficiary->bin . '_' . now()->timestamp . '.pdf';

            // Log activity
            VerificationActivityLog::create([
                'user_id'              => Auth::id(),
                'beneficiary_id'       => $beneficiary->id,
                'activity_type'        => 'card_generate',
                'activity_description' => 'ID card generated for beneficiary ' . $beneficiary->bin . ' by ' . Auth::user()->name,
                'ip_address'           => request()->ip(),
                'user_agent'           => request()->userAgent(),
                'status'               => 'success',
            ]);

            return $pdf->download($cardFilename);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Card generation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // batchGenerateCards — multi-beneficiary PDF (Admin only)
    // ─────────────────────────────────────────────────────────────────────────

    public function batchGenerateCards(Request $request)
    {
        abort_if(! Auth::user()->isAdministrator(), 403, 'Only Administrators can batch-generate ID cards.');

        $validated = $request->validate([
            'beneficiary_ids'   => 'required|array|min:1|max:100',
            'beneficiary_ids.*' => 'integer|exists:beneficiaries,id',
        ]);

        try {
            $cardsData = [];

            foreach ($validated['beneficiary_ids'] as $beneficiaryId) {
                $beneficiary = Beneficiary::find($beneficiaryId);
                if (! $beneficiary) continue;

                $qrCode = QrCode::where('beneficiary_id', $beneficiary->id)
                    ->where('is_valid', true)
                    ->latest('generated_at')
                    ->first();

                if (! $qrCode) continue;

                $qrFilename  = basename($qrCode->qr_image_path);
                $qrImageData = Storage::disk('qr_codes')->get($qrFilename);
                $ext         = strtolower(pathinfo($qrFilename, PATHINFO_EXTENSION));
                $mime        = $ext === 'svg' ? 'image/svg+xml' : 'image/png';
                $qrBase64    = 'data:' . $mime . ';base64,' . base64_encode($qrImageData);

                $cardsData[] = [
                    'beneficiary' => $beneficiary,
                    'qrCode'      => $qrCode,
                    'qrBase64'    => $qrBase64,
                    'issueDate'   => now()->format('M d, Y'),
                    'expiryDate'  => $qrCode->expires_at ? $qrCode->expires_at->format('M d, Y') : 'N/A',
                    'cardNumber'  => 'CRD-' . str_pad($beneficiary->id, 6, '0', STR_PAD_LEFT),
                ];
            }

            if (empty($cardsData)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid QR codes found for the selected beneficiaries.',
                ], 422);
            }

            $pdf = app('dompdf.wrapper');
            $pdf->setPaper('a4', 'portrait');
            $pdf->loadView('pdf.batch-id-cards', ['cards' => $cardsData]);

            $batchFilename = 'batch_cards_' . now()->timestamp . '.pdf';

            // Log activity
            VerificationActivityLog::create([
                'user_id'              => Auth::id(),
                'beneficiary_id'       => null,
                'activity_type'        => 'batch_card_generate',
                'activity_description' => 'Batch ID cards generated for ' . count($cardsData) . ' beneficiaries by ' . Auth::user()->name,
                'ip_address'           => $request->ip(),
                'user_agent'           => $request->userAgent(),
                'status'               => 'success',
            ]);

            return $pdf->download($batchFilename);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Batch card generation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // serveImage — stream QR file securely through controller (not direct URL)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Serve a QR image file after security validation and access logging.
     * Route: GET /qr/image/{beneficiaryId}/{filename}
     */
    public function serveImage(Request $request, int $beneficiaryId, string $filename)
    {
        // Ensure the QR record belongs to this beneficiary
        $qr = QrCode::where('beneficiary_id', $beneficiaryId)
            ->where('qr_image_path', $filename)
            ->first();

        if (! $qr) {
            abort(404);
        }

        // Increment download tracker in metadata
        $this->storage->incrementDownloadCount($qr);

        // Delegate to storage service — performs path traversal check + logging
        return $this->storage->serveQRImage($filename, $beneficiaryId);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helper — format a QrCode model for JSON responses
    // ─────────────────────────────────────────────────────────────────────────

    private function formatQrCode(QrCode $qr): array
    {
        $meta = $qr->metadata ?? [];

        return [
            'id'                  => $qr->id,
            'beneficiary_id'      => $qr->beneficiary_id,
            'qr_image_url'        => $qr->qr_image_url,
            'qr_image_path'       => $qr->qr_image_path,
            'generated_at'        => $qr->generated_at?->toIso8601String(),
            'expires_at'          => $qr->expires_at?->toIso8601String(),
            'is_valid'            => $qr->is_valid,
            'is_expired'          => $qr->expires_at && $qr->expires_at->isPast(),
            'regenerated_at'      => $qr->regenerated_at?->toIso8601String(),
            'regenerated_reason'  => $qr->regenerated_reason,
            'generated_at_human'  => $qr->generated_at?->diffForHumans(),
            'expires_at_human'    => $qr->expires_at?->format('M d, Y'),
            // Metadata extras
            'download_count'      => $meta['download_count'] ?? 0,
            'operator_name'       => $meta['operator_name']  ?? null,
            'generation_method'   => $meta['generation_method'] ?? 'generate',
        ];
    }
}
