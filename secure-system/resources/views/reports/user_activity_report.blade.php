<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>User Activity Report</title>
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
            background: linear-gradient(135deg, #4c1d95 0%, #7c3aed 100%);
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
            background: #faf5ff;
        }

        .card-label {
            font-size: 8px;
            text-transform: uppercase;
            color: #64748b;
        }

        .card-value {
            font-size: 22px;
            font-weight: 700;
            color: #7c3aed;
            margin: 4px 0;
        }

        .section {
            margin: 0 30px 20px;
        }

        .section-title {
            font-size: 12px;
            font-weight: 700;
            color: #4c1d95;
            border-bottom: 2px solid #7c3aed;
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
            background: #4c1d95;
            color: white;
        }

        thead th {
            padding: 7px 10px;
            text-align: left;
            font-weight: 600;
        }

        tbody tr:nth-child(even) {
            background: #faf5ff;
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
    </style>
</head>

<body>

    <div class="header">
        <div class="header-left">
            <div class="header-logo">DSWD SECURE</div>
            <h1>User Activity Report</h1>
            <p>Period: {{ $filters['date_from'] ?? 'All time' }} – {{ $filters['date_to'] ?? 'Present' }}</p>
        </div>
        <div class="header-right">
            <div>Generated: {{ $generated_at }}</div>
            <div>By: {{ $generated_by }}</div>
        </div>
    </div>

    <div class="cards">
        <div class="card">
            <div class="card-label">Active Users</div>
            <div class="card-value">{{ count($data['per_user'] ?? []) }}</div>
        </div>
        <div class="card">
            <div class="card-label">Inactive Users</div>
            <div class="card-value" style="color:#dc2626;">{{ count($data['inactive_users'] ?? []) }}</div>
        </div>
        <div class="card">
            <div class="card-label">Unusual Patterns</div>
            <div class="card-value" style="color:#d97706;">{{ count($data['unusual_patterns'] ?? []) }}</div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">User Productivity Summary</div>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Role</th>
                    <th>Office</th>
                    <th>Total Activities</th>
                    <th>Per Day</th>
                    <th>Per Hour</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['productivity'] ?? [] as $idx => $r)
                    <tr>
                        <td>{{ $idx + 1 }}</td>
                        <td>{{ $r['name'] }}</td>
                        <td>—</td>
                        <td>—</td>
                        <td>{{ number_format($r['total_activities']) }}</td>
                        <td>{{ $r['activities_per_day'] }}</td>
                        <td>{{ $r['activities_per_hour'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Inactive Users (No activity in period)</div>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Role</th>
                    <th>Office</th>
                    <th>Last Login</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['inactive_users'] ?? [] as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->role }}</td>
                        <td>{{ $user->office_location ?? '—' }}</td>
                        <td>{{ $user->last_login_at ? \Carbon\Carbon::parse($user->last_login_at)->toDateString() : 'Never' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="footer">
        <span>DSWD SECURE System – User Activity Report</span>
        <span>Confidential – For Official Use Only</span>
        <span>Page <span class="pagenum"></span></span>
    </div>
</body>

</html>