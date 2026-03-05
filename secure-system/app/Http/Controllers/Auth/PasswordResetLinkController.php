<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\VerificationActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     * Generates a 24-hour reset token and sends it via email.
     * Invalidates any previous reset tokens for this email.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        // Log password reset request
        VerificationActivityLog::create([
            'user_id'              => null,
            'activity_type'        => 'verify',
            'activity_description' => "Password reset link requested for email: {$request->email}. Status: " . __($status),
            'ip_address'           => $request->ip() ?? '0.0.0.0',
            'user_agent'           => $request->userAgent(),
            'timestamp'            => now(),
            'status'               => $status === Password::RESET_LINK_SENT ? 'success' : 'failed',
        ]);

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', __($status));
        }

        throw ValidationException::withMessages([
            'email' => [trans($status)],
        ]);
    }
}
