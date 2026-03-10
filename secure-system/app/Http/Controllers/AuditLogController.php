<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\VerificationActivityLog;
use App\Services\AuditLogService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class AuditLogController extends Controller
{
    public function __construct(private AuditLogService $auditSvc) {}

    // ─────────────────────────────────────────────────────────────
    // index — GET /audit-logs
    // Administrator only
    // ─────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        abort_if(! Auth::user()->isAdministrator(), 403);

        $query = VerificationActivityLog::with([
            'user:id,name,role,office_location',
            'beneficiary:id,bin,family_head_name',
        ])->orderByDesc('timestamp');

        // Filters
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('beneficiary_id')) {
            $query->where('beneficiary_id', $request->beneficiary_id);
        }
        if ($request->filled('activity_type')) {
            $query->where('activity_type', $request->activity_type);
        }
        if ($request->filled('activity_category')) {
            $query->where('activity_category', $request->activity_category);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('timestamp', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('timestamp', '<=', $request->date_to);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }
        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('activity_description', 'ilike', "%{$term}%")
                  ->orWhere('ip_address', 'ilike', "%{$term}%")
                  ->orWhereHas('user', fn ($u) => $u->where('name', 'ilike', "%{$term}%"));
            });
        }

        $paginated = $query->paginate(50)->withQueryString();

        return response()->json([
            'success'    => true,
            'data'       => $paginated->getCollection()->map(fn ($l) => $this->formatLog($l)),
            'pagination' => [
                'total'        => $paginated->total(),
                'per_page'     => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'from'         => $paginated->firstItem(),
                'to'           => $paginated->lastItem(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // show — GET /audit-logs/{id}
    // ─────────────────────────────────────────────────────────────

    public function show($logId)
    {
        abort_if(! Auth::user()->isAdministrator(), 403);

        $log = VerificationActivityLog::with([
            'user:id,name,role,office_location',
            'beneficiary:id,bin,family_head_name,municipality',
            'acknowledgedBy:id,name',
        ])->findOrFail($logId);

        // Fetch surrounding context (5 before, 5 after)
        $timeline = VerificationActivityLog::where('id', '!=', $logId)
            ->where(function ($q) use ($log) {
                $q->where('user_id', $log->user_id)
                  ->orWhere('beneficiary_id', $log->beneficiary_id)
                  ->orWhere('ip_address', $log->ip_address);
            })
            ->where('timestamp', '>=', $log->timestamp->subMinutes(30))
            ->where('timestamp', '<=', $log->timestamp->addMinutes(30))
            ->orderBy('timestamp')
            ->limit(10)
            ->get(['id', 'activity_type', 'activity_description', 'timestamp', 'status', 'user_id']);

        return response()->json([
            'success'        => true,
            'log'            => $this->formatLog($log, detailed: true),
            'changed_fields' => $log->changed_fields_attribute,
            'timeline'       => $timeline->map(fn ($t) => [
                'id'          => $t->id,
                'type'        => $t->activity_type,
                'description' => \Illuminate\Support\Str::limit($t->activity_description, 80),
                'timestamp'   => $t->timestamp?->toIso8601String(),
                'status'      => $t->status,
            ]),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // export — POST /audit-logs/export
    // ─────────────────────────────────────────────────────────────

    public function export(Request $request)
    {
        abort_if(! Auth::user()->isAdministrator(), 403);

        $request->validate([
            'format'    => ['required', 'in:csv,excel,pdf'],
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date'],
        ]);

        $query = $this->buildFilteredQuery($request);
        $logs  = $query->with('user:id,name,role', 'beneficiary:id,bin,family_head_name')
                       ->orderByDesc('timestamp')
                       ->get();

        $filename = 'audit_logs_' . now()->format('Ymd_His');

        $this->auditSvc->logActivity('export', "Exported audit logs ({$request->format}). Count: {$logs->count()}.", [
            'activity_category' => 'data_access',
            'severity'          => 'medium',
        ]);

        return match ($request->format) {
            'csv'   => $this->exportCsv($logs, $filename),
            'pdf'   => $this->exportPdf($logs, $filename, $request),
            default => $this->exportCsv($logs, $filename), // excel uses csv format for now
        };
    }

    // ─────────────────────────────────────────────────────────────
    // statistics — GET /audit-logs/statistics
    // ─────────────────────────────────────────────────────────────

    public function statistics(Request $request)
    {
        abort_if(! Auth::user()->isAdministrator(), 403);

        $from = $request->filled('from') ? Carbon::parse($request->from)->startOfDay() : now()->subDays(30);
        $to   = $request->filled('to')   ? Carbon::parse($request->to)->endOfDay()     : now()->endOfDay();

        $baseQuery = VerificationActivityLog::whereBetween('timestamp', [$from, $to]);

        // Totals
        $total       = (clone $baseQuery)->count();
        $totalSuccess = (clone $baseQuery)->where('status', 'success')->count();
        $totalFailed  = (clone $baseQuery)->where('status', 'failed')->count();

        // By activity type
        $byType = (clone $baseQuery)
            ->select('activity_type', DB::raw('count(*) as count'))
            ->groupBy('activity_type')
            ->orderByDesc('count')
            ->get()
            ->pluck('count', 'activity_type');

        // By category
        $byCategory = (clone $baseQuery)
            ->select('activity_category', DB::raw('count(*) as count'))
            ->groupBy('activity_category')
            ->orderByDesc('count')
            ->get()
            ->pluck('count', 'activity_category');

        // Top 10 active users
        $topUsers = (clone $baseQuery)
            ->whereNotNull('user_id')
            ->select('user_id', DB::raw('count(*) as count'))
            ->groupBy('user_id')
            ->orderByDesc('count')
            ->limit(10)
            ->with('user:id,name,role')
            ->get()
            ->map(fn ($r) => [
                'user_id' => $r->user_id,
                'name'    => $r->user?->name ?? 'Unknown',
                'role'    => $r->user?->role ?? '—',
                'count'   => $r->count,
            ]);

        // Activity by hour (0-23)
        $byHour = (clone $baseQuery)
            ->select(DB::raw('EXTRACT(HOUR FROM timestamp)::int AS hour'), DB::raw('count(*) as count'))
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->pluck('count', 'hour');

        // Daily activity for trend chart
        $byDay = (clone $baseQuery)
            ->select(DB::raw("DATE(timestamp) AS day"), DB::raw('count(*) as count'))
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($r) => ['date' => $r->day, 'count' => $r->count]);

        // Security severity breakdown
        $bySeverity = (clone $baseQuery)
            ->whereNotNull('severity')
            ->select('severity', DB::raw('count(*) as count'))
            ->groupBy('severity')
            ->get()
            ->pluck('count', 'severity');

        return response()->json([
            'success'        => true,
            'period'         => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'total'          => $total,
            'total_success'  => $totalSuccess,
            'total_failed'   => $totalFailed,
            'success_rate'   => $total > 0 ? round($totalSuccess / $total * 100, 2) : 0,
            'by_type'        => $byType,
            'by_category'    => $byCategory,
            'by_severity'    => $bySeverity,
            'top_users'      => $topUsers,
            'by_hour'        => $byHour,
            'by_day'         => $byDay,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // securityAlerts — GET /audit-logs/security-alerts
    // ─────────────────────────────────────────────────────────────

    public function securityAlerts(Request $request)
    {
        abort_if(! Auth::user()->isAdministrator(), 403);

        $alerts = [];

        // 1. Multiple failed attempts (> 5 per IP in 1 hour)
        $failedByIp = VerificationActivityLog::where('status', 'failed')
            ->where('timestamp', '>=', now()->subHour())
            ->select('ip_address', DB::raw('count(*) as count'))
            ->groupBy('ip_address')
            ->having('count', '>', 5)
            ->get();

        foreach ($failedByIp as $item) {
            $alerts[] = [
                'type'      => 'multiple_failed_attempts',
                'severity'  => $item->count > 15 ? 'critical' : 'high',
                'message'   => "IP {$item->ip_address} has {$item->count} failed attempts in the last hour.",
                'ip'        => $item->ip_address,
                'count'     => $item->count,
                'detected_at' => now()->toIso8601String(),
            ];
        }

        // 2. Unusual access hours (2 AM – 5 AM)
        $unusualHour = VerificationActivityLog::whereRaw("EXTRACT(HOUR FROM timestamp) BETWEEN 2 AND 5")
            ->where('timestamp', '>=', now()->subDay())
            ->select('user_id', DB::raw('count(*) as count'))
            ->groupBy('user_id')
            ->having('count', '>', 3)
            ->with('user:id,name')
            ->get();

        foreach ($unusualHour as $item) {
            $alerts[] = [
                'type'     => 'unusual_access_time',
                'severity' => 'medium',
                'message'  => "User {$item->user?->name} had {$item->count} actions between 2–5 AM.",
                'user_id'  => $item->user_id,
                'count'    => $item->count,
                'detected_at' => now()->toIso8601String(),
            ];
        }

        // 3. High-volume single user (> 500 requests in 1 hour)
        $highVolume = VerificationActivityLog::where('timestamp', '>=', now()->subHour())
            ->whereNotNull('user_id')
            ->select('user_id', DB::raw('count(*) as count'))
            ->groupBy('user_id')
            ->having('count', '>', 500)
            ->with('user:id,name,role')
            ->get();

        foreach ($highVolume as $item) {
            $alerts[] = [
                'type'     => 'high_volume_user',
                'severity' => 'high',
                'message'  => "User {$item->user?->name} made {$item->count} requests in the last hour.",
                'user_id'  => $item->user_id,
                'count'    => $item->count,
                'detected_at' => now()->toIso8601String(),
            ];
        }

        // 4. Unacknowledged high/critical security logs
        $unacked = VerificationActivityLog::security()
            ->unacknowledged()
            ->whereIn('severity', ['high', 'critical'])
            ->orderByDesc('timestamp')
            ->limit(20)
            ->get();

        foreach ($unacked as $log) {
            $alerts[] = [
                'type'      => $log->remarks ?? 'security_event',
                'severity'  => $log->severity,
                'message'   => $log->activity_description,
                'log_id'    => $log->id,
                'user_id'   => $log->user_id,
                'ip'        => $log->ip_address,
                'detected_at' => $log->timestamp?->toIso8601String(),
            ];
        }

        // Sort by severity weight
        $severityOrder = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
        usort($alerts, fn ($a, $b) =>
            ($severityOrder[$a['severity']] ?? 9) <=> ($severityOrder[$b['severity']] ?? 9)
        );

        return response()->json([
            'success' => true,
            'count'   => count($alerts),
            'alerts'  => $alerts,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // acknowledgeAlert — POST /audit-logs/{id}/acknowledge
    // ─────────────────────────────────────────────────────────────

    public function acknowledgeAlert($logId)
    {
        abort_if(! Auth::user()->isAdministrator(), 403);

        $log = VerificationActivityLog::findOrFail($logId);
        $log->update([
            'is_acknowledged'  => true,
            'acknowledged_at'  => now(),
            'acknowledged_by'  => Auth::id(),
        ]);

        return response()->json(['success' => true, 'message' => 'Alert acknowledged.']);
    }

    // ─────────────────────────────────────────────────────────────
    // page — GET /audit-logs/page (Inertia)
    // ─────────────────────────────────────────────────────────────

    public function page()
    {
        abort_if(! Auth::user()->isAdministrator(), 403);

        $users = User::orderBy('name')->get(['id', 'name', 'role']);

        return Inertia::render('AuditLogs/Index', [
            'users' => $users,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // generatePrivacyComplianceReport — GET /audit-logs/privacy-report
    // ─────────────────────────────────────────────────────────────

    public function generatePrivacyComplianceReport(Request $request)
    {
        abort_if(! Auth::user()->isAdministrator(), 403);

        $request->validate([
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after_or_equal:from'],
        ]);

        $from = Carbon::parse($request->from)->startOfDay();
        $to   = Carbon::parse($request->to)->endOfDay();

        $base = VerificationActivityLog::whereBetween('timestamp', [$from, $to]);

        // Data access events
        $accessTotal  = (clone $base)->where('activity_category', 'data_access')->count();
        $accessByUser = (clone $base)->where('activity_category', 'data_access')
            ->select('user_id', DB::raw('count(*) as count'))
            ->groupBy('user_id')
            ->with('user:id,name')
            ->get()
            ->map(fn ($r) => ['name' => $r->user?->name ?? 'Unknown', 'count' => $r->count]);

        $mostAccessedBeneficiaries = (clone $base)->where('activity_category', 'data_access')
            ->whereNotNull('beneficiary_id')
            ->select('beneficiary_id', DB::raw('count(*) as count'))
            ->groupBy('beneficiary_id')
            ->orderByDesc('count')
            ->limit(10)
            ->with('beneficiary:id,bin,family_head_name')
            ->get()
            ->map(fn ($r) => [
                'beneficiary' => $r->beneficiary?->family_head_name ?? 'Unknown',
                'bin'         => $r->beneficiary?->bin,
                'count'       => $r->count,
            ]);

        $exportEvents    = (clone $base)->where('activity_type', 'export')->count();
        $downloadEvents  = (clone $base)->where('activity_type', 'download')->count();

        $securityIncidents = (clone $base)->where('activity_category', 'security')
            ->whereIn('severity', ['high', 'critical'])
            ->count();

        $dataChanges = (clone $base)->where('activity_category', 'data_change')->count();

        $reportData = [
            'period'                   => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'generated_at'             => now()->toDateTimeString(),
            'generated_by'             => Auth::user()->name,
            'total_access_events'      => $accessTotal,
            'access_by_user'           => $accessByUser,
            'most_accessed_beneficiaries' => $mostAccessedBeneficiaries,
            'export_events'            => $exportEvents,
            'download_events'          => $downloadEvents,
            'security_incidents'       => $securityIncidents,
            'data_change_events'       => $dataChanges,
        ];

        // Render as PDF
        $pdf = Pdf::loadView('reports.privacy_compliance', $reportData)
                  ->setPaper('a4', 'portrait');

        $filename = "privacy_compliance_report_{$from->format('Ymd')}_{$to->format('Ymd')}.pdf";

        $this->auditSvc->logDataAccess('PrivacyComplianceReport', 0, 'export', 'confidential');

        return $pdf->download($filename);
    }

    // ─────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────

    private function buildFilteredQuery(Request $request)
    {
        $query = VerificationActivityLog::query();
        if ($request->filled('user_id'))        $query->where('user_id', $request->user_id);
        if ($request->filled('beneficiary_id')) $query->where('beneficiary_id', $request->beneficiary_id);
        if ($request->filled('activity_type'))  $query->where('activity_type', $request->activity_type);
        if ($request->filled('date_from'))      $query->whereDate('timestamp', '>=', $request->date_from);
        if ($request->filled('date_to'))        $query->whereDate('timestamp', '<=', $request->date_to);
        if ($request->filled('status'))         $query->where('status', $request->status);
        if ($request->filled('severity'))       $query->where('severity', $request->severity);
        return $query;
    }

    private function exportCsv($logs, string $filename)
    {
        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
        ];

        $callback = function () use ($logs) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'ID', 'Timestamp', 'User', 'Role', 'Beneficiary', 'BIN',
                'Activity Type', 'Category', 'Description', 'IP Address',
                'Status', 'Severity', 'Response Status', 'Execution Time (s)', 'Remarks',
            ]);
            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->id,
                    $log->timestamp?->toDateTimeString(),
                    $log->user?->name,
                    $log->user?->role,
                    $log->beneficiary?->family_head_name,
                    $log->beneficiary?->bin,
                    $log->activity_type,
                    $log->activity_category,
                    $log->activity_description,
                    $log->ip_address,
                    $log->status,
                    $log->severity,
                    $log->response_status,
                    $log->execution_time,
                    $log->remarks,
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportPdf($logs, string $filename, Request $request)
    {
        $pdf = Pdf::loadView('reports.audit_log_export', [
            'logs'    => $logs,
            'filters' => $request->only(['date_from', 'date_to', 'status', 'activity_type']),
            'generated_at' => now()->toDateTimeString(),
            'generated_by' => Auth::user()->name,
        ])->setPaper('a4', 'landscape');

        return $pdf->download("{$filename}.pdf");
    }

    private function formatLog(VerificationActivityLog $log, bool $detailed = false): array
    {
        $data = [
            'id'                   => $log->id,
            'timestamp'            => $log->timestamp?->toIso8601String(),
            'timestamp_human'      => $log->timestamp?->diffForHumans(),
            'user_id'              => $log->user_id,
            'user'                 => $log->relationLoaded('user') ? [
                'id'              => $log->user?->id,
                'name'            => $log->user?->name,
                'role'            => $log->user?->role,
                'office_location' => $log->user?->office_location,
            ] : null,
            'beneficiary_id'       => $log->beneficiary_id,
            'beneficiary'          => $log->relationLoaded('beneficiary') ? [
                'id'               => $log->beneficiary?->id,
                'bin'              => $log->beneficiary?->bin,
                'family_head_name' => $log->beneficiary?->family_head_name,
            ] : null,
            'activity_type'        => $log->activity_type,
            'activity_category'    => $log->activity_category,
            'activity_description' => $log->activity_description,
            'ip_address'           => $log->ip_address,
            'status'               => $log->status,
            'severity'             => $log->severity,
            'severity_color'       => $log->severity_color_attribute,
            'response_status'      => $log->response_status,
            'execution_time'       => $log->execution_time,
            'is_acknowledged'      => $log->is_acknowledged,
            'resource_type'        => $log->resource_type,
            'resource_id'          => $log->resource_id,
        ];

        if ($detailed) {
            $data['old_values']    = $log->old_values;
            $data['new_values']    = $log->new_values;
            $data['request_data']  = $log->request_data;
            $data['remarks']       = $log->remarks;
            $data['user_agent']    = $log->user_agent;
            $data['acknowledged_at'] = $log->acknowledged_at?->toIso8601String();
            $data['acknowledged_by'] = $log->relationLoaded('acknowledgedBy')
                ? $log->acknowledgedBy?->name : null;
        }

        return $data;
    }
}
