<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Fraud Detection Report</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            color: #1e293b;
            line-height: 1.5;
        }

        .header {
            background: linear-gradient(135deg, #7f1d1d 0%, #dc2626 100%);
            color: white;
            padding: 20px 30px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-logo {
            font-size: 22px;
            font-weight: 900;
            letter-spacing: 2px;
        }

        .header-left h1 {
            font-size: 18px;
            font-weight: 700;
        }

        .header-left p {
            font-size: 10px;
            opacity: 0.85;
        }

        .header-right {
            text-align: right;
            font-size: 9px;
            opacity: 0.85;
        }

        .cards {
            display: flex;
            gap: 10px;
            margin: 0 30px 20px;
        }

        .card {
            flex: 1;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px;
            background: #fff1f2;
        }

        .card-label {
            font-size: 8px;
            text-transform: uppercase;
            color: #64748b;
        }

        .card-value {
            font-size: 22px;
            font-weight: 700;
            color: #dc2626;
            margin: 4px 0;
        }

        .section {
            margin: 0 30px 20px;
        }

        .section-title {
            font-size: 12px;
            font-weight: 700;
            color: #7f1d1d;
            border-bottom: 2px solid #dc2626;
            padding-bottom: 5px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
        }

        thead tr {
            background: #7f1d1d;
            color: white;
        }

        thead th {
            padding: 7px 10px;
            text-align: left;
            font-weight: 600;
        }

        tbody tr:nth-child(even) {
            background: #fff1f2;
        }

        tbody td {
            padding: 6px 10px;
            border-bottom: 1px solid #e2e8f0;
        }

        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 8px 30px;
            display: flex;
            justify-content: space-between;
            font-size: 8px;
            color: #64748b;
        }

        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 8px;
            font-weight: 600;
        }

        .badge-high {
            background: #fee2e2;
            color: #dc2626;
        }

        .badge-med {
            background: #fef9c3;
            color: #a16207;
        }

        .badge-low {
            background: #dcfce7;
            color: #16a34a;
        }

        .col-2 {
            display: flex;
            gap: 20px;
            margin: 0 30px 20px;
        }

        .col-2>* {
            flex: 1;
            margin: 0;
        }
    </style>
</head>

<body>

    <div class="header">
        <div class="header-left">
            <div class="header-logo">DSWD SECURE</div>
            <h1>Fraud Detection Report</h1>
            <p>Period: {{ $filters['date_from'] ?? 'All time' }} – {{ $filters['date_to'] ?? 'Present' }} | Office:
                {{ $filters['office'] ?? 'All Offices' }}</p>
        </div>
        <div class="header-right">
            <div>Generated: {{ $generated_at }}</div>
            <div>By: {{ $generated_by }}</div>
        </div>
    </div>

    <div class="cards">
        <div class="card">
            <div class="card-label">Total Detections</div>
            <div class="card-value">{{ number_format($data['total']) }}</div>
        </div>
        <div class="card">
            <div class="card-label">High Confidence (&gt;80%)</div>
            <div class="card-value">{{ number_format($data['high_confidence']) }}</div>
        </div>
        <div class="card">
            <div class="card-label">Flagged</div>
            <div class="card-value">{{ number_format($data['action_breakdown']['flagged'] ?? 0) }}</div>
        </div>
        <div class="card">
            <div class="card-label">Merged</div>
            <div class="card-value" style="color:#d97706;">{{ number_format($data['action_breakdown']['merged'] ?? 0) }}
            </div>
        </div>
        <div class="card">
            <div class="card-label">Dismissed</div>
            <div class="card-value" style="color:#64748b;">
                {{ number_format($data['action_breakdown']['dismissed'] ?? 0) }}</div>
        </div>
        <div class="card">
            <div class="card-label">Failed Verifications</div>
            <div class="card-value">{{ number_format($data['failed_verifications']) }}</div>
        </div>
    </div>

    <div class="col-2">
        <div class="section">
            <div class="section-title">By Detection Type</div>
            <table>
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Count</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['by_type'] ?? [] as $type => $count)
                        <tr>
                            <td>{{ ucfirst(str_replace('_', ' ', $type)) }}</td>
                            <td>{{ $count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="section">
            <div class="section-title">Multiple QR Scans (Suspicious)</div>
            <table>
                <thead>
                    <tr>
                        <th>Beneficiary</th>
                        <th>BIN</th>
                        <th>Scans</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['multiple_scans'] ?? [] as $row)
                        <tr>
                            <td>{{ $row['beneficiary'] }}</td>
                            <td>{{ $row['bin'] }}</td>
                            <td>{{ $row['scan_count'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Recent Fraud Events</div>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Type</th>
                    <th>Confidence</th>
                    <th>Primary</th>
                    <th>Duplicate</th>
                    <th>Action</th>
                    <th>Status</th>
                    <th>Detected At</th>
                </tr>
            </thead>
            <tbody>
                @foreach(array_slice($data['events'] ?? [], 0, 25) as $idx => $event)
                    <tr>
                        <td>{{ $idx + 1 }}</td>
                        <td>{{ str_replace('_', ' ', $event['type']) }}</td>
                        <td>
                            <span
                                class="badge {{ $event['confidence'] >= 80 ? 'badge-high' : ($event['confidence'] >= 50 ? 'badge-med' : 'badge-low') }}">
                                {{ $event['confidence'] }}%
                            </span>
                        </td>
                        <td>{{ $event['primary'] }}</td>
                        <td>{{ $event['duplicate'] }}</td>
                        <td>{{ $event['action_taken'] ?? 'Pending' }}</td>
                        <td>{{ $event['status'] }}</td>
                        <td>{{ $event['detected_at'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="footer">
        <span>DSWD SECURE System – Fraud Detection Report</span>
        <span>CONFIDENTIAL – Restricted Access</span>
        <span>Page <span class="pagenum"></span></span>
    </div>
</body>

</html>