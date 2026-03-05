<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beneficiary List Export</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 9px;
            color: #1e293b;
            background: #fff;
        }

        /* Header */
        .report-header {
            padding: 16px 20px 12px;
            border-bottom: 2px solid #4f46e5;
            margin-bottom: 12px;
        }

        .report-title {
            font-size: 16px;
            font-weight: bold;
            color: #4f46e5;
        }

        .report-meta {
            font-size: 8px;
            color: #64748b;
            margin-top: 4px;
        }

        .report-meta span {
            margin-right: 16px;
        }

        /* Summary strip */
        .summary {
            display: flex;
            gap: 12px;
            margin-bottom: 12px;
        }

        .scard {
            background: #f1f5f9;
            border-left: 3px solid #4f46e5;
            padding: 6px 10px;
            min-width: 80px;
        }

        .scard-label {
            font-size: 7px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .scard-val {
            font-size: 13px;
            font-weight: bold;
            color: #1e293b;
        }

        /* Table */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
        }

        thead tr {
            background: #4f46e5;
        }

        thead th {
            padding: 6px 8px;
            color: #fff;
            font-weight: bold;
            text-align: left;
            white-space: nowrap;
        }

        tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        tbody tr:hover {
            background: #eff6ff;
        }

        tbody td {
            padding: 5px 8px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        /* Status badges */
        .badge {
            display: inline-block;
            padding: 1px 5px;
            border-radius: 9999px;
            font-size: 7px;
            font-weight: bold;
        }

        .badge-active {
            background: #dcfce7;
            color: #166534;
        }

        .badge-inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Footer */
        .report-footer {
            margin-top: 14px;
            padding-top: 8px;
            border-top: 1px solid #e2e8f0;
            font-size: 7.5px;
            color: #94a3b8;
            display: flex;
            justify-content: space-between;
        }

        /* Page number */
        .page-num:before {
            content: "Page " counter(page);
        }
    </style>
</head>

<body>

    <!-- Header -->
    <div class="report-header">
        <div class="report-title">SECURE System — Beneficiary Extract</div>
        <div class="report-meta">
            <span>Exported by: <strong>{{ $exportedBy }}</strong></span>
            <span>Date: <strong>{{ $exportedAt }}</strong></span>
            <span>Total records: <strong>{{ $totalCount }}</strong></span>
        </div>
    </div>

    <!-- Table -->
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>BIN</th>
                <th>Full Name</th>
                <th>Gender</th>
                <th>Civil Status</th>
                <th>Contact</th>
                <th>Barangay</th>
                <th>Municipality</th>
                <th>Province</th>
                <th>Annual Income</th>
                <th>HH Size</th>
                <th>Status</th>
                <th>Registered</th>
            </tr>
        </thead>
        <tbody>
            @foreach($beneficiaries as $i => $b)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td><strong>{{ $b->bin }}</strong></td>
                    <td>{{ $b->family_head_name }}</td>
                    <td>{{ $b->gender }}</td>
                    <td>{{ $b->civil_status }}</td>
                    <td>{{ $b->contact_number }}</td>
                    <td>{{ $b->barangay }}</td>
                    <td>{{ $b->municipality }}</td>
                    <td>{{ $b->province }}</td>
                    <td>₱{{ number_format((float) $b->annual_income, 2) }}</td>
                    <td style="text-align:center">{{ $b->household_size }}</td>
                    <td>
                        <span class="badge {{ $b->is_active ? 'badge-active' : 'badge-inactive' }}">
                            {{ $b->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td>{{ $b->created_at?->format('Y-m-d') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Footer -->
    <div class="report-footer">
        <span>SECURE System — Confidential. For authorized use only.</span>
        <span class="page-num"></span>
    </div>

</body>

</html>