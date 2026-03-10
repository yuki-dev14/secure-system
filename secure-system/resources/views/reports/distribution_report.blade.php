<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Distribution Report</title>
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
            background: linear-gradient(135deg, #1d4ed8 0%, #6366f1 100%);
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
            background: #eff6ff;
        }

        .card-label {
            font-size: 8px;
            text-transform: uppercase;
            color: #64748b;
        }

        .card-value {
            font-size: 18px;
            font-weight: 700;
            color: #1d4ed8;
            margin: 4px 0;
        }

        .card-sub {
            font-size: 8px;
            color: #64748b;
        }

        .section {
            margin: 0 30px 20px;
        }

        .section-title {
            font-size: 12px;
            font-weight: 700;
            color: #1d4ed8;
            border-bottom: 2px solid #6366f1;
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
            background: #1d4ed8;
            color: white;
        }

        thead th {
            padding: 7px 10px;
            text-align: left;
            font-weight: 600;
        }

        tbody tr:nth-child(even) {
            background: #eff6ff;
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

        .alert {
            background: #fef9c3;
            border: 1px solid #fde047;
            border-radius: 4px;
            padding: 8px 12px;
            margin: 0 30px 15px;
            font-size: 9px;
            color: #a16207;
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
            <h1>Cash Grant Distribution Report</h1>
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
            <div class="card-label">Total Amount Distributed</div>
            <div class="card-value">₱{{ number_format($data['total_amount'], 2) }}</div>
        </div>
        <div class="card">
            <div class="card-label">Distribution Count</div>
            <div class="card-value">{{ number_format($data['total_count']) }}</div>
        </div>
        <div class="card">
            <div class="card-label">Average Payout</div>
            <div class="card-value">₱{{ number_format($data['avg_payout'], 2) }}</div>
        </div>
        <div class="card">
            <div class="card-label">Beneficiaries Served</div>
            <div class="card-value">{{ number_format($data['unique_beneficiaries']) }}</div>
        </div>
        <div class="card">
            <div class="card-label">Reconciled</div>
            <div class="card-value" style="color:#16a34a;">{{ number_format($data['reconciled']) }}</div>
        </div>
        <div class="card">
            <div class="card-label">Unreconciled</div>
            <div class="card-value" style="color:#dc2626;">{{ number_format($data['unreconciled']) }}</div>
        </div>
    </div>

    @if(!empty($data['anomalies']))
        <div class="alert">
            ⚠️ {{ count($data['anomalies']) }} anomaly/anomalies detected in this period. Please review.
        </div>
    @endif

    <div class="col-2">
        <div class="section">
            <div class="section-title">By Payment Method</div>
            <table>
                <thead>
                    <tr>
                        <th>Method</th>
                        <th>Count</th>
                        <th>Total (₱)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['by_payment_method'] ?? [] as $row)
                        <tr>
                            <td>{{ ucfirst(str_replace('_', ' ', $row->payment_method ?? 'Unknown')) }}</td>
                            <td>{{ number_format($row->count) }}</td>
                            <td>₱{{ number_format($row->total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="section">
            <div class="section-title">By Office</div>
            <table>
                <thead>
                    <tr>
                        <th>Office</th>
                        <th>Count</th>
                        <th>Total (₱)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['by_office'] ?? [] as $row)
                        <tr>
                            <td>{{ $row->office ?? 'Unknown' }}</td>
                            <td>{{ number_format($row->count) }}</td>
                            <td>₱{{ number_format($row->total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Top Distribution Officers</div>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Office</th>
                    <th>Count</th>
                    <th>Total Distributed (₱)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['by_officer'] ?? [] as $idx => $row)
                    <tr>
                        <td>{{ $idx + 1 }}</td>
                        <td>{{ $row['name'] }}</td>
                        <td>{{ $row['office'] }}</td>
                        <td>{{ number_format($row['count']) }}</td>
                        <td>₱{{ number_format($row['total'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="footer">
        <span>DSWD SECURE System – Distribution Report</span>
        <span>Confidential – For Official Use Only</span>
        <span>Page <span class="pagenum"></span></span>
    </div>
</body>

</html>