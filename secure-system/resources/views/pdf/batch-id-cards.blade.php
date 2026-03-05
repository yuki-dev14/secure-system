<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f8fafc;
        }

        .page-title {
            text-align: center;
            font-size: 10pt;
            font-weight: 700;
            color: #1e3a5f;
            padding: 12pt 0 4pt;
            border-bottom: 1pt solid #e2e8f0;
            margin-bottom: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.5pt;
        }

        .page-sub {
            text-align: center;
            font-size: 7pt;
            color: #64748b;
            margin-bottom: 14pt;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: 242.64pt 242.64pt;
            gap: 12pt;
            justify-content: center;
            padding: 0 12pt;
        }

        /* Reuse card styles from individual template */
        .card {
            width: 242.64pt;
            height: 153.07pt;
            border: 1.5pt solid #1e3a5f;
            border-radius: 6pt;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            background: #fff;
            position: relative;
            page-break-inside: avoid;
        }

        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 42pt;
            color: rgba(30, 58, 95, 0.04);
            font-weight: 900;
            white-space: nowrap;
            z-index: 0;
            letter-spacing: 2pt;
        }

        .card-header {
            background: linear-gradient(135deg, #1e3a5f 0%, #2d6a8f 100%);
            padding: 5pt 8pt;
            display: flex;
            align-items: center;
            gap: 6pt;
            z-index: 1;
        }

        .header-logo {
            width: 22pt;
            height: 22pt;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .logo-text {
            font-size: 7pt;
            font-weight: 900;
            color: #1e3a5f;
            line-height: 1;
        }

        .header-text {
            flex: 1;
        }

        .header-title {
            font-size: 6.5pt;
            font-weight: 900;
            color: #fff;
            letter-spacing: 0.5pt;
            text-transform: uppercase;
            line-height: 1.2;
        }

        .header-sub {
            font-size: 5pt;
            color: rgba(255, 255, 255, 0.75);
            letter-spacing: 0.3pt;
            text-transform: uppercase;
        }

        .validity-badge {
            background: #dcfce7;
            color: #16a34a;
            padding: 1pt 4pt;
            border-radius: 2pt;
            font-size: 4pt;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.3pt;
        }

        .card-body {
            flex: 1;
            display: flex;
            padding: 6pt;
            gap: 6pt;
            z-index: 1;
            position: relative;
        }

        .qr-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .qr-img {
            width: 68pt;
            height: 68pt;
            border: 1pt solid #e2e8f0;
            border-radius: 3pt;
            padding: 2pt;
        }

        .qr-label {
            font-size: 4.5pt;
            color: #64748b;
            text-align: center;
            margin-top: 2pt;
            text-transform: uppercase;
            letter-spacing: 0.3pt;
        }

        .info-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .info-block {
            display: flex;
            flex-direction: column;
            gap: 3pt;
        }

        .info-row {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 4.5pt;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.4pt;
            font-weight: 700;
        }

        .info-value {
            font-size: 7pt;
            color: #1e293b;
            font-weight: 700;
            line-height: 1.2;
        }

        .info-value.bin {
            font-size: 8pt;
            color: #1e3a5f;
            letter-spacing: 0.5pt;
            font-family: 'Courier New', monospace;
        }

        .info-value.name {
            font-size: 6.5pt;
            font-weight: 800;
            text-transform: uppercase;
        }

        .card-footer {
            background: #f1f5f9;
            border-top: 0.5pt solid #e2e8f0;
            padding: 3pt 8pt;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1;
        }

        .footer-left,
        .footer-right {
            font-size: 4.5pt;
            color: #64748b;
            font-weight: 600;
        }

        .card-id {
            font-family: 'Courier New', monospace;
            color: #1e3a5f;
        }

        .page-break {
            page-break-before: always;
        }

        .batch-row {
            display: flex;
            gap: 12pt;
            margin-bottom: 12pt;
        }

        .print-info {
            text-align: center;
            font-size: 7pt;
            color: #94a3b8;
            margin-top: 14pt;
            padding-top: 8pt;
            border-top: 0.5pt solid #e2e8f0;
        }
    </style>
</head>

<body>
    <div class="page-title">PANTAWID PAMILYA PILIPINO PROGRAM — BATCH ID CARDS</div>
    <div class="page-sub">
        Generated on {{ now()->format('F d, Y \a\t h:i A') }} &nbsp;|&nbsp; Total Cards: {{ count($cards) }}
    </div>

    @php $chunks = array_chunk($cards, 4); @endphp

    @foreach ($chunks as $pageIndex => $pageCards)
        @if ($pageIndex > 0)
            <div class="page-break"></div>
        @endif

        @php $rows = array_chunk($pageCards, 2); @endphp

        @foreach ($rows as $rowCards)
            <div class="batch-row">
                @foreach ($rowCards as $cardData)
                    <div class="card">
                        <div class="watermark">SECURE</div>

                        <div class="card-header">
                            <div class="header-logo">
                                <div class="logo-text">DSWD</div>
                            </div>
                            <div class="header-text">
                                <div class="header-title">PANTAWID PAMILYA PILIPINO PROGRAM</div>
                                <div class="header-sub">SECURE System — Beneficiary ID Card</div>
                            </div>
                            <div class="validity-badge">VALID</div>
                        </div>

                        <div class="card-body">
                            <div class="qr-section">
                                <img class="qr-img" src="{{ $cardData['qrBase64'] }}" alt="QR Code" />
                                <div class="qr-label">Scan to Verify</div>
                            </div>
                            <div class="info-section">
                                <div class="info-block">
                                    <div class="info-row">
                                        <span class="info-label">Beneficiary ID Number</span>
                                        <span class="info-value bin">{{ $cardData['beneficiary']->bin }}</span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Name of Family Head</span>
                                        <span class="info-value name">{{ $cardData['beneficiary']->family_head_name }}</span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Location</span>
                                        <span class="info-value" style="font-size:5.5pt;">
                                            {{ $cardData['beneficiary']->barangay }}, {{ $cardData['beneficiary']->municipality }}
                                        </span>
                                    </div>
                                </div>
                                <div class="info-block">
                                    <div style="display:flex; gap:8pt;">
                                        <div class="info-row">
                                            <span class="info-label">Issued</span>
                                            <span class="info-value" style="font-size:5.5pt;">{{ $cardData['issueDate'] }}</span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Valid Until</span>
                                            <span class="info-value"
                                                style="font-size:5.5pt; color:#1e3a5f;">{{ $cardData['expiryDate'] }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card-footer">
                            <div class="footer-left">DSWD — Department of Social Welfare and Development</div>
                            <div class="footer-right card-id">{{ $cardData['cardNumber'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
    @endforeach

    <div class="print-info">
        SECURE System — Batch ID Card Print Job &nbsp;|&nbsp; DSWD Philippines &nbsp;|&nbsp; CONFIDENTIAL
    </div>
</body>

</html>