<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CheckPermission Middleware
 *
 * Restricts route access based on a specific permission string.
 *
 * Usage in routes:
 *   ->middleware('permission:beneficiary.create')
 *   ->middleware('permission:compliance.approve')
 */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $allowedPermissions = User::permissionsFor($user->role);

        if (! in_array($permission, $allowedPermissions)) {
            abort(403, "You do not have the '{$permission}' permission.");
        }

        return $next($request);
    }
}
