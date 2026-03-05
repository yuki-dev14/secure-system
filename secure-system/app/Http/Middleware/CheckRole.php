<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CheckRole Middleware
 *
 * Restricts route access based on the authenticated user's role.
 *
 * Usage in routes:
 *   ->middleware('role:Administrator')
 *   ->middleware('role:Administrator,Field Officer')
 */
class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        if (! $request->user()->hasRole($roles)) {
            abort(403, 'You do not have permission to access this resource.');
        }

        return $next($request);
    }
}
