<?php

namespace App\Http\Controllers;

use App\Models\VerificationActivityLog;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile page (all tabs combined).
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status'          => session('status'),
        ]);
    }

    /**
     * Update the user's profile information (name, email, office_location).
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'email'           => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'office_location' => ['required', 'string', 'max:255'],
        ]);

        // Reset email verification if email changed
        if ($user->email !== $validated['email']) {
            $validated['email_verified_at'] = null;
        }

        $user->fill($validated)->save();

        VerificationActivityLog::create([
            'user_id'              => $user->id,
            'activity_type'        => 'edit',
            'activity_description' => "Profile information updated for user '{$user->name}'.",
            'ip_address'           => $request->ip() ?? '0.0.0.0',
            'user_agent'           => $request->userAgent(),
            'timestamp'            => now(),
            'status'               => 'success',
        ]);

        return back()->with('success', 'Profile updated successfully.');
    }

    /**
     * Update the user's password.
     *
     * Security rules:
     * - Must verify current password
     * - New password must meet strength requirements
     * - New password must not match any of the last 5 passwords
     * - After change, all other sessions (other devices) are invalidated
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password'         => [
                'required',
                'confirmed',
                Password::min(8)->mixedCase()->numbers()->symbols(),
                // Prevent password reuse (last 5)
                function (string $attribute, mixed $value, \Closure $fail) use ($user) {
                    if ($user->wasPasswordUsedRecently($value)) {
                        $fail('This password was used recently. Please choose a different password.');
                    }
                },
            ],
        ]);

        // Save current hash to history before changing
        $user->pushPasswordToHistory();

        $user->forceFill([
            'password'            => Hash::make($request->password),
            'password_changed_at' => now(),
            'remember_token'      => \Illuminate\Support\Str::random(60),
        ])->save();

        // Invalidate all other sessions (force logout on other devices)
        Auth::logoutOtherDevices($request->current_password);

        // Re-login on current device to keep the session alive
        $request->session()->regenerate();

        VerificationActivityLog::create([
            'user_id'              => $user->id,
            'activity_type'        => 'edit',
            'activity_description' => "Password changed for user '{$user->name}'. Other sessions invalidated.",
            'ip_address'           => $request->ip() ?? '0.0.0.0',
            'user_agent'           => $request->userAgent(),
            'timestamp'            => now(),
            'status'               => 'success',
        ]);

        return back()->with('success', 'Password changed successfully. You have been signed out from all other devices.');
    }

    /**
     * Delete the user's own account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
