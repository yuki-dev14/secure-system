<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VerificationActivityLog;
use App\Notifications\NewUserWelcomeNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     * Only accessible by Administrators.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request (Admin only).
     * Creates a new system user with role and office location.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'email'           => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password'        => [
                'required',
                'confirmed',
                Password::min(8)
                    ->mixedCase()   // upper + lower
                    ->numbers()     // at least one number
                    ->symbols(),    // at least one special char
            ],
            'role'            => ['required', 'string', 'in:Administrator,Field Officer,Compliance Verifier'],
            'office_location' => ['required', 'string', 'max:255'],
        ]);

        $user = User::create([
            'name'            => $request->name,
            'email'           => $request->email,
            'password'        => Hash::make($request->password),
            'role'            => $request->role,
            'office_location' => $request->office_location,
            'status'          => 'active',
        ]);

        event(new Registered($user));

        // Send welcome notification to the new user
        try {
            $user->notify(new NewUserWelcomeNotification($request->password));
        } catch (\Exception $e) {
            // Mail logging used — safe to swallow in dev
        }

        // Log registration in audit trail
        VerificationActivityLog::create([
            'user_id'              => Auth::id() ?? $user->id,
            'activity_type'        => 'edit',
            'activity_description' => "New user '{$user->name}' ({$user->role}) registered by Admin. Office: {$user->office_location}",
            'ip_address'           => $request->ip() ?? '0.0.0.0',
            'user_agent'           => $request->userAgent(),
            'timestamp'            => now(),
            'status'               => 'success',
        ]);

        // If called while an admin is already logged in, DO NOT log the admin out
        if (! Auth::check()) {
            Auth::login($user);
        }

        return redirect(route('dashboard', absolute: false))
            ->with('success', "User '{$user->name}' has been registered successfully.");
    }
}
