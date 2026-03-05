<?php

namespace App\Http\Controllers;

use App\Models\VerificationActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SecurityController extends Controller
{
    /**
     * Display the Security Dashboard.
     *
     * Shows: active sessions, recent login attempts, password status,
     * and account status.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Active sessions from the sessions table
        $sessions = DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(function ($session) use ($request) {
                return [
                    'id'              => $session->id,
                    'ip_address'      => $session->ip_address,
                    'user_agent'      => $session->user_agent,
                    'last_activity'   => $session->last_activity,
                    'is_current'      => $session->id === $request->session()->getId(),
                ];
            });

        // Recent login attempts (last 10)
        $loginAttempts = VerificationActivityLog::where('user_id', $user->id)
            ->where('activity_type', 'verify')
            ->whereIn('activity_description', function ($query) {
                $query->selectRaw("activity_description")
                      ->from('verification_activity_logs')
                      ->whereRaw("activity_description LIKE '%logged in%' OR activity_description LIKE '%Invalid credentials%' OR activity_description LIKE '%Session auto-expired%'");
            })
            ->orderByDesc('timestamp')
            ->limit(10)
            ->get(['activity_description', 'ip_address', 'timestamp', 'status']);

        // Summary counts
        $stats = [
            'active_sessions'        => $sessions->count(),
            'failed_logins_24h'      => VerificationActivityLog::where('user_id', $user->id)
                ->where('activity_type', 'verify')
                ->where('status', 'failed')
                ->where('timestamp', '>=', now()->subHours(24))
                ->count(),
            'password_changed_at'    => $user->password_changed_at?->toIso8601String(),
            'last_login_at'          => $user->last_login_at?->toIso8601String(),
            'account_status'         => $user->status,
        ];

        return Inertia::render('Profile/SecurityDashboard', [
            'sessions'      => $sessions,
            'loginAttempts' => $loginAttempts,
            'stats'         => $stats,
        ]);
    }

    /**
     * Terminate all sessions except the current one.
     */
    public function terminateOtherSessions(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user    = $request->user();
        $current = $request->session()->getId();

        // Delete all other sessions from DB
        $deleted = DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $current)
            ->delete();

        VerificationActivityLog::create([
            'user_id'              => $user->id,
            'activity_type'        => 'delete',
            'activity_description' => "Terminated {$deleted} other session(s) for user '{$user->name}'.",
            'ip_address'           => $request->ip() ?? '0.0.0.0',
            'user_agent'           => $request->userAgent(),
            'timestamp'            => now(),
            'status'               => 'success',
        ]);

        return back()->with('success', "{$deleted} other session(s) have been terminated.");
    }
}
