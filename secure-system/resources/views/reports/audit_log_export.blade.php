<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Log Export</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            color: #1e293b;
        }

        .report-header {
            background: #1e3a5f;
            color: white;
            padding: 14px 18px;
            margin-bottom: 14px;
        }

        .report-header h1 {
            font-size: 16px;
            font-weight: bold;
        }

        .report-header p {
            font-size: 9px;
            opacity: 0.8;
            margin-top: 3px;
        }

        .meta-row {
            display: flex;
            gap: 20px;
            padding: 6px 18px 10px;
            font-size: 9px;
            color: #64748b;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 0 10px;
            width: calc(100% - 20px);
        }

        thead tr {
            background: #1e3a5f;
            color: white;
        }

        thead th {
            padding: 6px 5px;
            text-align: left;
            font-size: 8.5px;
            font-weight: bold;
        }

        tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        tbody tr:hover {
            background: #eff6ff;
        }

        tbody td {
            padding: 5px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 8.5px;
            vertical-align: top;
        }

        .status-success {
            color: #15803d;
            font-weight: bold;
        }

        .status-failed {
            color: #dc2626;
            font-weight: bold;
        }

        .sev-critical {
            color: #dc2626;
            font-weight: bold;
        }

        .sev-high {
            color: #ea580c;
            font-weight: bold;
        }

        .sev-medium {
            color: #d97706;
        }

        .sev-low {
            color: #65a30d;
        }

        .footer {
            margin-top: 14px;
            padding: 8px 18px;
            font-size: 8px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            text-align: center;
        }

        .desc-cell {
            max-width: 220px;
            word-wrap: break-word;
        }
    </style>
</head>

<body>
    <div class="report-header">
        <h1>🔍 Audit Log Export — SECURE System</h1>
        <p>Generated: {{ $generated_at }} by {{ $generated_by }}</p>
    </div>

    <div class="meta-row">
        @if(!empty($filters['date_from'])) <span>From: <strong>{{ $filters['date_from'] }}</strong></span> @endif
        @if(!empty($filters['date_to'])) <span>To: <strong>{{ $filters['date_to'] }}</strong></span> @endif
        @if(!empty($filters['status'])) <span>Status: <strong>{{ ucfirst($filters['status']) }}</strong></span> @endif
        @if(!empty($filters['activity_type'])) <span>Type: <strong>{{ $filters['activity_type'] }}</strong></span>
        @endif
        <span>Total Records: <strong>{{ $logs->count() }}</strong></span>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Timestamp</th>
                <th>User</th>
                <th>Role</th>
                <th>Beneficiary</th>
                <th>BIN</th>
                <th>Type</th>
                <th>Category</th>
                <th>Description</th>
                <th>IP Address</th>
                <th>Status</th>
                <th>Severity</th>
                <th>Response</th>
                <th>Time (s)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($logs as $log)
                <tr>
                    <td>{{ $log->id }}</td>
                    <td>{{ $log->timestamp?->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $log->user?->name ?? '—' }}</td>
                    <td>{{ $log->user?->role ?? '—' }}</td>
                    <td>{{ $log->beneficiary?->family_head_name ?? '—' }}</td>
                    <td>{{ $log->beneficiary?->bin ?? '—' }}</td>
                    <td>{{ $log->activity_type }}</td>
                    <td>{{ $log->activity_category ?? '—' }}</td>
                    <td class="desc-cell">{{ Str::limit($log->activity_description, 80) }}</td>
                    <td>{{ $log->ip_address }}</td>
                    <td class="status-{{ $log->status }}">{{ strtoupper($log->status) }}</td>
                    <td @if($log->severity) class="sev-{{ $log->severity }}" @endif>
                        {{ $log->severity ? strtoupper($log->severity) : '—' }}
                    </td>
                    <td>{{ $log->response_status ?? '—' }}</td>
                    <td>{{ $log->execution_time ? number_format($log->execution_time, 3) : '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        SECURE System — Confidential Audit Log Export · {{ now()->toDateTimeString() }}
        · This document is for authorized personnel only.
    </div>
</body>

</html>