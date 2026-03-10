<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Compliance Report</title>
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
            background: linear-gradient(135deg, #065f46 0%, #059669 100%);
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
            background: #f0fdf4;
        }

        .card-label {
            font-size: 8px;
            text-transform: uppercase;
            color: #64748b;
        }

        .card-value {
            font-size: 22px;
            font-weight: 700;
            color: #065f46;
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
            color: #065f46;
            border-bottom: 2px solid #059669;
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
            background: #065f46;
            color: white;
        }

        thead th {
            padding: 7px 10px;
            text-align: left;
            font-weight: 600;
        }

        tbody tr:nth-child(even) {
            background: #f0fdf4;
        }

        tbody td {
            padding: 6px 10px;
            border-bottom: 1px solid #e2e8f0;
        }

        .gauge-row {
            display: flex;
            gap: 15px;
            margin: 0 30px 20px;
        }

        .gauge-item {
            flex: 1;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px;
            text-align: center;
            background: #f8fafc;
        }

        .gauge-pct {
            font-size: 24px;
            font-weight: 700;
        }

        .gauge-label {
            font-size: 9px;
            color: #64748b;
            margin-top: 4px;
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

        .badge-green {
            background: #dcfce7;
            color: #16a34a;
        }

        .badge-red {
            background: #fee2e2;
            color: #dc2626;
        }

        .badge-yellow {
            background: #fef9c3;
            color: #a16207;
        }
    </style>
</head>

<body>

    <div class="header">
        <div class="header-left">
            <div class="header-logo">DSWD SECURE</div>
            <h1>Compliance Report</h1>
            <p>Period: {{ $filters['date_from'] ?? 'All time' }} – {{ $filters['date_to'] ?? 'Present' }} | Office:
                {{ $filters['office'] ?? 'All Offices' }}</p>
        </div>
        <div class="header-right">
            <div>Generated: {{ $generated_at }}</div>
            <div>By: {{ $generated_by }}</div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="cards">
        <div class="card">
            <div class="card-label">Total Beneficiaries</div>
            <div class="card-value">{{ number_format($data['total']) }}</div>
        </div>
        <div class="card">
            <div class="card-label">Compliant</div>
            <div class="card-value" style="color:#16a34a;">{{ number_format($data['compliant_count']) }}</div>
        </div>
        <div class="card">
            <div class="card-label">Non-Compliant</div>
            <div class="card-value" style="color:#dc2626;">{{ number_format($data['non_compliant_count']) }}</div>
        </div>
        <div class="card">
            <div class="card-label">Partial</div>
            <div class="card-value" style="color:#d97706;">{{ number_format($data['partial_count']) }}</div>
        </div>
    </div>

    <!-- Compliance Rates -->
    <div class="gauge-row">
        <div class="gauge-item">
            <div class="gauge-pct" style="color:#059669;">{{ $data['overall_rate'] }}%</div>
            <div class="gauge-label">Overall Compliance</div>
        </div>
        <div class="gauge-item">
            <div class="gauge-pct" style="color:#2563eb;">{{ $data['education_rate'] }}%</div>
            <div class="gauge-label">Education Compliance</div>
        </div>
        <div class="gauge-item">
            <div class="gauge-pct" style="color:#dc2626;">{{ $data['health_rate'] }}%</div>
            <div class="gauge-label">Health Compliance</div>
        </div>
        <div class="gauge-item">
            <div class="gauge-pct" style="color:#7c3aed;">{{ $data['fds_rate'] }}%</div>
            <div class="gauge-label">FDS Compliance</div>
        </div>
    </div>

    <!-- Non-Compliant Beneficiaries -->
    <div class="section">
        <div class="section-title">Non-Compliant Beneficiaries</div>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>BIN</th>
                    <th>Municipality</th>
                    <th>Education%</th>
                    <th>Health%</th>
                    <th>FDS%</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach(array_slice($data['non_compliant_list'] ?? [], 0, 30) as $idx => $r)
                    <tr>
                        <td>{{ $idx + 1 }}</td>
                        <td>{{ $r['name'] }}</td>
                        <td>{{ $r['bin'] }}</td>
                        <td>{{ $r['municipality'] }}</td>
                        <td>{{ $r['education_rate'] }}%</td>
                        <td>{{ $r['health_rate'] }}%</td>
                        <td>{{ $r['fds_rate'] }}%</td>
                        <td><span class="badge badge-red">{{ strtoupper($r['overall_status']) }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- By Location -->
    <div class="section">
        <div class="section-title">Compliance by Location</div>
        <table>
            <thead>
                <tr>
                    <th>Municipality</th>
                    <th>Total</th>
                    <th>Compliant</th>
                    <th>Non-Compliant</th>
                    <th>Partial</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['by_location'] ?? [] as $row)
                    <tr>
                        <td>{{ $row->municipality }}</td>
                        <td>{{ $row->total }}</td>
                        <td>{{ $row->compliant }}</td>
                        <td>{{ $row->non_compliant }}</td>
                        <td>{{ $row->partial }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="footer">
        <span>DSWD SECURE System – Compliance Report</span>
        <span>Confidential – For Official Use Only</span>
        <span>Page <span class="pagenum"></span></span>
    </div>
</body>

</html>