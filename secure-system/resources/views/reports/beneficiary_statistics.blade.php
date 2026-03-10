<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Beneficiary Statistics Report</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1e293b; line-height: 1.5; }

    /* Header */
    .header { background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); color: white; padding: 20px 30px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .header-left h1 { font-size: 18px; font-weight: 700; letter-spacing: 0.5px; }
    .header-left p  { font-size: 10px; opacity: 0.85; margin-top: 2px; }
    .header-right   { text-align: right; font-size: 9px; opacity: 0.85; }
    .header-logo    { font-size: 22px; font-weight: 900; letter-spacing: 2px; }

    /* Summary Cards */
    .cards { display: flex; gap: 10px; margin: 0 30px 20px; }
    .card { flex: 1; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; background: #f8fafc; }
    .card-label { font-size: 8px; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px; }
    .card-value { font-size: 22px; font-weight: 700; color: #1e40af; margin: 4px 0; }
    .card-sub   { font-size: 8px; color: #64748b; }

    /* Sections */
    .section { margin: 0 30px 20px; }
    .section-title { font-size: 12px; font-weight: 700; color: #1e40af; border-bottom: 2px solid #1e40af; padding-bottom: 5px; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px; }

    /* Tables */
    table { width: 100%; border-collapse: collapse; font-size: 9px; }
    thead tr { background: #1e40af; color: white; }
    thead th { padding: 7px 10px; text-align: left; font-weight: 600; }
    tbody tr:nth-child(even) { background: #f1f5f9; }
    tbody tr:hover            { background: #e2e8f0; }
    tbody td { padding: 6px 10px; border-bottom: 1px solid #e2e8f0; }

    /* Footer */
    .footer { position: fixed; bottom: 0; left: 0; right: 0; background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 8px 30px; display: flex; justify-content: space-between; font-size: 8px; color: #64748b; }

    /* Page break */
    .page-break { page-break-after: always; }

    /* Two-column layout */
    .col-2 { display: flex; gap: 20px; }
    .col-2 > * { flex: 1; }

    /* Tag */
    .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 8px; font-weight: 600; }
    .badge-green  { background: #dcfce7; color: #16a34a; }
    .badge-red    { background: #fee2e2; color: #dc2626; }
    .badge-yellow { background: #fef9c3; color: #a16207; }
</style>
</head>
<body>

<!-- Header -->
<div class="header">
    <div class="header-left">
        <div class="header-logo">DSWD SECURE</div>
        <h1>Beneficiary Statistics Report</h1>
        <p>
            Period:
            {{ isset($filters['date_from']) ? $filters['date_from'] : 'All time' }}
            –
            {{ isset($filters['date_to'])   ? $filters['date_to']   : 'Present' }}
            &nbsp;|&nbsp;
            Office: {{ isset($filters['office']) ? $filters['office'] : 'All Offices' }}
        </p>
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
        <div class="card-label">Active</div>
        <div class="card-value" style="color:#16a34a;">{{ number_format($data['active']) }}</div>
        <div class="card-sub">{{ $data['active_percentage'] }}% of total</div>
    </div>
    <div class="card">
        <div class="card-label">Inactive</div>
        <div class="card-value" style="color:#dc2626;">{{ number_format($data['inactive']) }}</div>
        <div class="card-sub">{{ $data['inactive_percentage'] }}% of total</div>
    </div>
    <div class="card">
        <div class="card-label">Newly Registered</div>
        <div class="card-value" style="color:#7c3aed;">{{ number_format($data['newly_registered']) }}</div>
    </div>
    <div class="card">
        <div class="card-label">Avg Household Size</div>
        <div class="card-value" style="color:#0369a1;">{{ $data['avg_household_size'] }}</div>
    </div>
    <div class="card">
        <div class="card-label">Avg Annual Income</div>
        <div class="card-value" style="font-size:14px;color:#0369a1;">PHP {{ number_format($data['avg_annual_income'], 0) }}</div>
    </div>
</div>

<!-- By Municipality -->
<div class="section">
    <div class="section-title">Beneficiaries by Municipality</div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Municipality</th>
                <th>Count</th>
                <th>Share</th>
            </tr>
        </thead>
        <tbody>
            @php $total = $data['total'] ?: 1; @endphp
            @foreach($data['by_municipality'] ?? [] as $idx => $row)
            <tr>
                <td>{{ $idx + 1 }}</td>
                <td>{{ $row['municipality'] ?? $row->municipality }}</td>
                <td>{{ number_format($row['count'] ?? $row->count) }}</td>
                <td>{{ round(($row['count'] ?? $row->count) / $total * 100, 1) }}%</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

<!-- Civil Status & Income Distribution -->
<div class="col-2 section">
    <div>
        <div class="section-title">Civil Status Distribution</div>
        <table>
            <thead><tr><th>Status</th><th>Count</th></tr></thead>
            <tbody>
                @foreach($data['civil_status'] ?? [] as $status => $count)
                <tr>
                    <td>{{ ucfirst($status ?? 'Unknown') }}</td>
                    <td>{{ number_format($count) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div>
        <div class="section-title">Income Distribution</div>
        <table>
            <thead><tr><th>Income Range</th><th>Count</th></tr></thead>
            <tbody>
                @foreach([
                    'under_10k' => '< ₱10,000',
                    '10k_20k'   => '₱10,000 – ₱19,999',
                    '20k_30k'   => '₱20,000 – ₱29,999',
                    '30k_50k'   => '₱30,000 – ₱49,999',
                    '50k_100k'  => '₱50,000 – ₱99,999',
                    'over_100k' => '≥ ₱100,000',
                ] as $key => $label)
                <tr>
                    <td>{{ $label }}</td>
                    <td>{{ number_format($data['income_distribution'][$key] ?? 0) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<!-- Footer -->
<div class="footer">
    <span>DSWD SECURE System – Beneficiary Statistics Report</span>
    <span>Confidential – For Official Use Only</span>
    <span>Page <span class="pagenum"></span></span>
</div>

</body>
</html>
