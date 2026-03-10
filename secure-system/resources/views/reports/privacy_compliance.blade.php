<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Data Privacy Compliance Report</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
        }

        .cover-header {
            background: linear-gradient(135deg, #1e3a5f, #1565c0);
            color: white;
            padding: 28px 24px 20px;
            margin-bottom: 20px;
        }

        .cover-header h1 {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 4px;
        }

        .cover-header .subtitle {
            font-size: 11px;
            opacity: 0.8;
        }

        .cover-header .period {
            margin-top: 10px;
            font-size: 12px;
            font-weight: bold;
            background: rgba(255, 255, 255, 0.15);
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
        }

        .section {
            padding: 0 20px;
            margin-bottom: 18px;
        }

        .section h2 {
            font-size: 13px;
            font-weight: bold;
            color: #1e3a5f;
            border-bottom: 2px solid #1e3a5f;
            padding-bottom: 4px;
            margin-bottom: 10px;
        }

        .kpi-grid {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .kpi-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px 16px;
            flex: 1;
            min-width: 120px;
            text-align: center;
        }

        .kpi-card .val {
            font-size: 22px;
            font-weight: bold;
            color: #1e3a5f;
        }

        .kpi-card .lbl {
            font-size: 9px;
            color: #94a3b8;
            margin-top: 2px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead tr {
            background: #1e3a5f;
            color: white;
        }

        thead th {
            padding: 7px 8px;
            text-align: left;
            font-size: 9.5px;
            font-weight: bold;
        }

        tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        tbody td {
            padding: 6px 8px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 10px;
        }

        .alert-box {
            background: #fff5f5;
            border: 1px solid #fecaca;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 10px;
        }

        .alert-box.safe {
            background: #f0fdf4;
            border-color: #86efac;
        }

        .alert-box-title {
            font-weight: bold;
            font-size: 11px;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .alert-body {
            font-size: 10px;
            color: #475569;
            line-height: 1.6;
        }

        .footer {
            padding: 12px 20px;
            font-size: 8.5px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            margin-top: 20px;
        }

        .signature-area {
            margin-top: 24px;
            padding: 0 20px;
            display: flex;
            gap: 40px;
        }

        .sig-block {
            flex: 1;
            border-top: 1px solid #1e293b;
            padding-top: 4px;
            font-size: 9px;
            color: #64748b;
        }
    </style>
</head>

<body>
    <div class="cover-header">
        <h1>🔒 Data Privacy Compliance Report</h1>
        <div class="subtitle">SECURE System — Conditional Cash Transfer Management</div>
        <div class="period">Reporting Period: {{ $period['from'] }} → {{ $period['to'] }}</div>
    </div>

    <!-- KPI Summary -->
    <div class="section">
        <h2>📊 Summary Statistics</h2>
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="val">{{ number_format($total_access_events) }}</div>
                <div class="lbl">Total Data Access Events</div>
            </div>
            <div class="kpi-card">
                <div class="val">{{ number_format($export_events) }}</div>
                <div class="lbl">Export Events</div>
            </div>
            <div class="kpi-card">
                <div class="val">{{ number_format($download_events) }}</div>
                <div class="lbl">Download Events</div>
            </div>
            <div class="kpi-card">
                <div class="val" style="color: {{ $security_incidents > 0 ? '#dc2626' : '#15803d' }}">
                    {{ number_format($security_incidents) }}
                </div>
                <div class="lbl">Security Incidents</div>
            </div>
            <div class="kpi-card">
                <div class="val">{{ number_format($data_change_events) }}</div>
                <div class="lbl">Data Change Events</div>
            </div>
        </div>
    </div>

    <!-- Security Assessment -->
    <div class="section">
        <h2>🛡️ Security Assessment</h2>
        @if($security_incidents === 0)
            <div class="alert-box safe">
                <div class="alert-box-title">✅ No High/Critical Security Incidents</div>
                <div class="alert-body">No high or critical severity security events were recorded during this reporting
                    period. The system's security posture is satisfactory.</div>
            </div>
        @else
            <div class="alert-box">
                <div class="alert-box-title">⚠️ {{ $security_incidents }} High/Critical Security Incident(s) Recorded</div>
                <div class="alert-body">Security incidents were detected during this period. Please review the security
                    alerts section in the full audit log for details and ensure all incidents have been acknowledged and
                    addressed.</div>
            </div>
        @endif
    </div>

    <!-- Data Access by User -->
    <div class="section">
        <h2>👤 Data Access by User</h2>
        <table>
            <thead>
                <tr>
                    <th>User Name</th>
                    <th>Number of Access Events</th>
                </tr>
            </thead>
            <tbody>
                @forelse($access_by_user as $u)
                    <tr>
                        <td>{{ $u['name'] }}</td>
                        <td>{{ number_format($u['count']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" style="text-align:center;color:#94a3b8;">No data access events recorded.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Most Accessed Beneficiaries -->
    <div class="section">
        <h2>📋 Most Frequently Accessed Beneficiaries</h2>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Beneficiary Name</th>
                    <th>BIN</th>
                    <th>Access Count</th>
                </tr>
            </thead>
            <tbody>
                @forelse($most_accessed_beneficiaries as $i => $b)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $b['beneficiary'] }}</td>
                        <td>{{ $b['bin'] ?? '—' }}</td>
                        <td>{{ number_format($b['count']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align:center;color:#94a3b8;">No beneficiary access data available.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Compliance Statement -->
    <div class="section">
        <h2>⚖️ Data Privacy Act Compliance Statement</h2>
        <div class="alert-box safe">
            <div class="alert-box-title">Data Retention Policy</div>
            <div class="alert-body">
                All audit logs are retained for a minimum of 5 years in compliance with Republic Act No. 10173 (Data
                Privacy Act of 2012).
                Logs older than 2 years are archived to optimized storage while remaining accessible for compliance
                review.
                Personal data is collected and processed with lawful basis and is not shared with unauthorized parties.
            </div>
        </div>
    </div>

    <!-- Signature Block -->
    <div class="signature-area">
        <div class="sig-block">
            Data Privacy Officer
            <div style="margin-top:2px;">Date: ____________________</div>
        </div>
        <div class="sig-block">
            System Administrator
            <div style="margin-top:2px;">Date: ____________________</div>
        </div>
        <div class="sig-block">
            Report Generated By: {{ $generated_by }}
            <div style="margin-top:2px;">{{ $generated_at }}</div>
        </div>
    </div>

    <div class="footer">
        SECURE System — Data Privacy Compliance Report · Confidential · For authorized personnel only.
        Republic Act No. 10173 (Data Privacy Act of 2012) — Data Protection Compliance Documentation.
    </div>
</body>

</html>