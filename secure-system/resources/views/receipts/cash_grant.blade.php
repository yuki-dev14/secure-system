<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cash Grant Distribution Receipt - {{ $receiptNumber }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            color: #1a1a2e;
            background: #fff;
            padding: 20px;
        }

        .header {
            text-align: center;
            border-bottom: 3px solid #1565c0;
            padding-bottom: 14px;
            margin-bottom: 16px;
        }

        .header .system-name {
            font-size: 18px;
            font-weight: bold;
            color: #1565c0;
            letter-spacing: 1px;
        }

        .header .receipt-title {
            font-size: 13px;
            color: #555;
            margin-top: 2px;
        }

        .header .receipt-number {
            font-size: 11px;
            color: #1565c0;
            font-weight: bold;
            margin-top: 4px;
        }

        .row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .section {
            flex: 1;
        }

        .section-title {
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
            color: #1565c0;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 3px;
            margin-bottom: 6px;
            letter-spacing: 0.5px;
        }

        .field-row {
            display: flex;
            margin-bottom: 4px;
        }

        .field-label {
            color: #666;
            width: 130px;
            flex-shrink: 0;
            font-size: 10px;
        }

        .field-value {
            font-weight: 600;
            font-size: 10px;
        }

        .amount-box {
            background: linear-gradient(135deg, #1565c0, #0d47a1);
            color: white;
            text-align: center;
            padding: 14px;
            border-radius: 8px;
            margin: 16px 0;
        }

        .amount-box .label {
            font-size: 10px;
            opacity: 0.85;
            margin-bottom: 4px;
        }

        .amount-box .amount {
            font-size: 28px;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .amount-box .period {
            font-size: 10px;
            opacity: 0.85;
            margin-top: 4px;
        }

        .qr-sig-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 12px;
        }

        .sig-area {
            text-align: center;
        }

        .sig-area img {
            max-height: 60px;
            max-width: 160px;
        }

        .sig-line {
            border-top: 1px solid #333;
            width: 160px;
            margin: 6px auto 2px;
        }

        .sig-label {
            font-size: 9px;
            color: #666;
        }

        .qr-area {
            text-align: center;
        }

        .qr-area img {
            width: 90px;
            height: 90px;
        }

        .qr-label {
            font-size: 9px;
            color: #888;
            margin-top: 3px;
        }

        .terms {
            background: #f5f5f5;
            border-radius: 6px;
            padding: 10px 12px;
            margin-top: 14px;
            font-size: 9px;
            color: #666;
            border-left: 3px solid #1565c0;
        }

        .terms p {
            margin-bottom: 3px;
        }

        .footer {
            text-align: center;
            margin-top: 14px;
            font-size: 9px;
            color: #aaa;
            border-top: 1px solid #eee;
            padding-top: 8px;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: bold;
        }

        .badge-cash {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .badge-ewallet {
            background: #e3f2fd;
            color: #0277bd;
        }

        .badge-bank {
            background: #fff3e0;
            color: #e65100;
        }
    </style>
</head>

<body>
    <!-- Header -->
    <div class="header">
        <div class="system-name">🏛 SECURE System</div>
        <div class="receipt-title">Cash Grant Distribution Receipt</div>
        <div class="receipt-number">{{ $receiptNumber }}</div>
    </div>

    <!-- Amount -->
    <div class="amount-box">
        <div class="label">AMOUNT RECEIVED</div>
        <div class="amount">₱{{ number_format($distribution->payout_amount, 2) }}</div>
        <div class="period">For the period: {{ $distribution->payout_period }}</div>
    </div>

    <!-- Beneficiary & Distribution Info -->
    <div class="row">
        <div class="section" style="padding-right: 12px;">
            <div class="section-title">Beneficiary Information</div>
            <div class="field-row">
                <span class="field-label">Name:</span>
                <span class="field-value">{{ $distribution->beneficiary->family_head_name }}</span>
            </div>
            <div class="field-row">
                <span class="field-label">BIN:</span>
                <span class="field-value">{{ $distribution->beneficiary->bin }}</span>
            </div>
            <div class="field-row">
                <span class="field-label">Barangay:</span>
                <span class="field-value">{{ $distribution->beneficiary->barangay }}</span>
            </div>
            <div class="field-row">
                <span class="field-label">Municipality:</span>
                <span class="field-value">{{ $distribution->beneficiary->municipality }}</span>
            </div>
            <div class="field-row">
                <span class="field-label">Province:</span>
                <span class="field-value">{{ $distribution->beneficiary->province }}</span>
            </div>
        </div>
        <div class="section" style="padding-left: 12px;">
            <div class="section-title">Distribution Details</div>
            <div class="field-row">
                <span class="field-label">Date & Time:</span>
                <span class="field-value">{{ $distribution->distributed_at->format('M d, Y h:i A') }}</span>
            </div>
            <div class="field-row">
                <span class="field-label">Payment Method:</span>
                <span class="field-value">
                    @php
                        $cls = match ($distribution->payment_method) {
                            'cash' => 'badge-cash',
                            'e-wallet' => 'badge-ewallet',
                            default => 'badge-bank'
                        };
                    @endphp
                    <span
                        class="badge {{ $cls }}">{{ strtoupper(str_replace('_', ' ', $distribution->payment_method)) }}</span>
                </span>
            </div>
            <div class="field-row">
                <span class="field-label">Reference No.:</span>
                <span class="field-value">{{ $distribution->transaction_reference_number }}</span>
            </div>
            <div class="field-row">
                <span class="field-label">Distributed By:</span>
                <span class="field-value">{{ $distribution->distributedBy?->name }}</span>
            </div>
            <div class="field-row">
                <span class="field-label">Office:</span>
                <span class="field-value">{{ $distribution->distributedBy?->office_location ?? '—' }}</span>
            </div>
            @if($distribution->approved_by_user_id !== $distribution->distributed_by_user_id)
                <div class="field-row">
                    <span class="field-label">Approved By:</span>
                    <span class="field-value">{{ $distribution->approvedBy?->name }}</span>
                </div>
            @endif
            @if($distribution->remarks)
                <div class="field-row">
                    <span class="field-label">Remarks:</span>
                    <span class="field-value">{{ Str::limit($distribution->remarks, 60) }}</span>
                </div>
            @endif
        </div>
    </div>

    <!-- Signature & QR -->
    <div class="qr-sig-row">
        <div class="sig-area">
            @if($signatureBase64)
                <img src="{{ $signatureBase64 }}" alt="Beneficiary Signature">
            @else
                <div style="height:60px; display:flex; align-items:flex-end;">
                    <div style="width:160px;"></div>
                </div>
            @endif
            <div class="sig-line"></div>
            <div class="sig-label">Beneficiary / Authorized Representative Signature</div>
        </div>

        @if($qrBase64)
            <div class="qr-area">
                <img src="{{ $qrBase64 }}" alt="QR Verification Code">
                <div class="qr-label">Scan to verify receipt</div>
            </div>
        @endif
    </div>

    <!-- Terms -->
    <div class="terms">
        <p><strong>Terms & Conditions:</strong></p>
        <p>1. This receipt is official proof of cash grant distribution under the SECURE System.</p>
        <p>2. The beneficiary acknowledges receipt of the stated amount and agrees that the funds are for household
            welfare improvement.</p>
        <p>3. Any discrepancy must be reported within 5 business days to the issuing office.</p>
        <p>4. This receipt may be verified by scanning the QR code or visiting: <strong>{{ $verificationUrl }}</strong>
        </p>
    </div>

    <!-- Footer -->
    <div class="footer">
        Generated by SECURE System &bull; {{ now()->format('M d, Y h:i A') }} &bull; For inquiries, contact your DSWD
        field office.
    </div>
</body>

</html>