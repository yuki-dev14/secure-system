<?php

namespace App\Http\Middleware;

use App\Models\VerificationActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * TrackActivity Middleware
 *
 * Updates session activity time and auto-logs out users
 * after 30 minutes of inactivity (SESSION_LIFETIME).
 * Applied globally to all authenticated routes.
 */
class TrackActivity
{
    /**
     * Inactivity timeout in seconds (30 minutes).
     */
    private const TIMEOUT_SECONDS = 1800;

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $lastActivity = session('last_activity_at');

            if ($lastActivity !== null) {
                $elapsed = now()->timestamp - $lastActivity;

                if ($elapsed > self::TIMEOUT_SECONDS) {
                    $user = Auth::user();

                    // Log auto-logout
                    VerificationActivityLog::create([
                        'user_id'              => $user->id,
                        'activity_type'        => 'verify',
                        'activity_description' => "Session auto-expired due to inactivity ({$elapsed}s).",
                        'ip_address'           => $request->ip() ?? '0.0.0.0',
                        'user_agent'           => $request->userAgent(),
                        'timestamp'            => now(),
                        'status'               => 'success',
                    ]);

                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    if ($request->expectsJson()) {
                        return response()->json(['message' => 'Session expired.'], 401);
                    }

                    return redirect()->route('login')->with('status', 'Your session has expired. Please log in again.');
                }
            }

            // Refresh the timestamp on every authenticated request
            session(['last_activity_at' => now()->timestamp]);
        }

        return $next($request);
    }
}
