<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>QR Code Report</title>
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
            background: linear-gradient(135deg, #0c4a6e 0%, #0284c7 100%);
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
            background: #f0f9ff;
        }

        .card-label {
            font-size: 8px;
            text-transform: uppercase;
            color: #64748b;
        }

        .card-value {
            font-size: 22px;
            font-weight: 700;
            color: #0284c7;
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
            color: #0c4a6e;
            border-bottom: 2px solid #0284c7;
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
            background: #0c4a6e;
            color: white;
        }

        thead th {
            padding: 7px 10px;
            text-align: left;
            font-weight: 600;
        }

        tbody tr:nth-child(even) {
            background: #f0f9ff;
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
            <h1>QR Code Management Report</h1>
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
            <div class="card-label">Total QR Codes</div>
            <div class="card-value">{{ number_format($data['total']) }}</div>
        </div>
        <div class="card">
            <div class="card-label">Active</div>
            <div class="card-value" style="color:#16a34a;">{{ number_format($data['active']) }}</div>
        </div>
        <div class="card">
            <div class="card-label">Expired</div>
            <div class="card-value" style="color:#d97706;">{{ number_format($data['expired']) }}</div>
        </div>
        <div class="card">
            <div class="card-label">Invalid</div>
            <div class="card-value" style="color:#dc2626;">{{ number_format($data['invalid']) }}</div>
        </div>
        <div class="card">
            <div class="card-label">Regenerated</div>
            <div class="card-value" style="color:#7c3aed;">{{ number_format($data['regenerated']) }}</div>
        </div>
        <div class="card">
            <div class="card-label">Failed Scans</div>
            <div class="card-value" style="color:#dc2626;">{{ number_format($data['failed_scans']) }}</div>
        </div>
    </div>

    <div class="col-2">
        <div class="section">
            <div class="section-title">Replacement Statistics</div>
            <table>
                <thead>
                    <tr>
                        <th>Metric</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Replacement Rate</td>
                        <td>{{ $data['replacement_rate'] }}%</td>
                    </tr>
                    <tr>
                        <td>Avg Lifespan (days)</td>
                        <td>{{ $data['avg_lifespan_days'] }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="section">
            <div class="section-title">Regeneration Reasons</div>
            <table>
                <thead>
                    <tr>
                        <th>Reason</th>
                        <th>Count</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['by_reason'] ?? [] as $reason => $count)
                        <tr>
                            <td>{{ ucfirst(str_replace('_', ' ', $reason)) }}</td>
                            <td>{{ $count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="section">
        <div class="section-title">High Regeneration Rate by Office</div>
        <table>
            <thead>
                <tr>
                    <th>Office</th>
                    <th>Regeneration Count</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['high_regen_by_office'] ?? [] as $row)
                    <tr>
                        <td>{{ $row->office ?? 'Unknown' }}</td>
                        <td>{{ $row->regen_count }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Beneficiaries with Frequent Scan Failures</div>
        <table>
            <thead>
                <tr>
                    <th>Beneficiary</th>
                    <th>BIN</th>
                    <th>Failures</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['failed_scans_by_beneficiary'] ?? [] as $row)
                    <tr>
                        <td>{{ $row['beneficiary'] }}</td>
                        <td>{{ $row['bin'] }}</td>
                        <td>{{ $row['failures'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="footer">
        <span>DSWD SECURE System – QR Code Management Report</span>
        <span>Confidential – For Official Use Only</span>
        <span>Page <span class="pagenum"></span></span>
    </div>
</body>

</html>