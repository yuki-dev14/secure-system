<?php

namespace App\Http\Middleware;

use App\Services\AuditLogService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * AuditLogMiddleware
 *
 * Automatically records an audit log entry for every non-trivial
 * HTTP request that passes through the authenticated routes.
 *
 * Excluded from logging:
 *   - Static asset requests (images, JS, CSS, fonts)
 *   - Health-check endpoints
 *   - Audit-log read endpoints (to avoid recursive growth)
 *   - OPTIONS pre-flight requests
 */
class AuditLogMiddleware
{
    // URI patterns that should NOT be logged
    private const SKIP_PATTERNS = [
        '/up',
        '/audit-logs',       // avoid log-reading generating more logs
        '/sanctum/csrf-cookie',
    ];

    // File extensions that should NOT be logged
    private const SKIP_EXTENSIONS = [
        'js', 'css', 'png', 'jpg', 'jpeg', 'gif', 'svg',
        'ico', 'woff', 'woff2', 'ttf', 'eot', 'map',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        $response = $next($request);

        // Only log for authenticated users
        if (! Auth::check()) {
            return $response;
        }

        // Skip OPTIONS pre-flight
        if ($request->isMethod('OPTIONS')) {
            return $response;
        }

        // Skip static asset extensions
        $extension = strtolower(pathinfo($request->path(), PATHINFO_EXTENSION));
        if (in_array($extension, self::SKIP_EXTENSIONS)) {
            return $response;
        }

        // Skip excluded URI patterns
        foreach (self::SKIP_PATTERNS as $pattern) {
            if (str_starts_with('/' . $request->path(), $pattern)) {
                return $response;
            }
        }

        // Determine activity type from method
        $activityType = match ($request->method()) {
            'GET'    => 'view',
            'POST'   => 'approve', // generic create/action
            'PUT', 'PATCH' => 'edit',
            'DELETE' => 'delete',
            default  => 'view',
        };

        $executionTime = round(microtime(true) - $startTime, 4);
        $statusCode    = $response->getStatusCode();

        try {
            $requestData = $request->except(array_merge(
                ['password', 'password_confirmation', 'current_password',
                 '_token', 'signature_data_url']
            ));

            app(AuditLogService::class)->logActivity(
                $activityType,
                sprintf(
                    '%s %s → %d [%.3fs]',
                    $request->method(),
                    '/' . $request->path(),
                    $statusCode,
                    $executionTime
                ),
                [
                    'activity_category' => 'system',
                    'request_data'      => array_slice($requestData, 0, 30), // cap fields
                    'response_status'   => $statusCode,
                    'execution_time'    => $executionTime,
                    'status'            => $statusCode < 400 ? 'success' : 'failed',
                ]
            );
        } catch (\Throwable $e) {
            // Never let audit logging crash the response
        }

        return $response;
    }
}
