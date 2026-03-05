<?php

namespace App\Http\Controllers;

use App\Models\VerificationActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    /**
     * Display the authenticated user's activity history.
     * Supports filtering by activity_type and date range.
     * Paginated at 20 per page.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $query = VerificationActivityLog::where('user_id', $user->id)
            ->select([
                'id',
                'activity_type',
                'activity_description',
                'ip_address',
                'user_agent',
                'timestamp',
                'status',
                'remarks',
            ]);

        // Filter by activity type
        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('activity_type', $request->type);
        }

        // Filter by date range
        if ($request->filled('from')) {
            $query->whereDate('timestamp', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('timestamp', '<=', $request->to);
        }

        // Filter by status
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $logs = $query->orderByDesc('timestamp')->paginate(20)->withQueryString();

        return Inertia::render('Profile/ActivityHistory', [
            'logs'    => $logs,
            'filters' => $request->only(['type', 'from', 'to', 'status']),
        ]);
    }
}
