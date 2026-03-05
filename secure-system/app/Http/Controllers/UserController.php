<?php

namespace App\Http\Controllers;

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

class UserController extends Controller
{
    /**
     * List all system users. Admin only.
     */
    public function index(): Response
    {
        $users = User::select('id', 'name', 'email', 'role', 'office_location', 'status', 'last_login_at', 'created_at')
            ->orderBy('name')
            ->paginate(20);

        return Inertia::render('Users/Index', [
            'users' => $users,
        ]);
    }

    /**
     * Show the create user form. Admin only.
     */
    public function create(): Response
    {
        return Inertia::render('Users/Create');
    }

    /**
     * Store a newly created user. Admin only.
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
                Password::min(8)->mixedCase()->numbers()->symbols(),
            ],
            'role'            => ['required', 'string', 'in:Administrator,Field Officer,Compliance Verifier'],
            'office_location' => ['required', 'string', 'max:255'],
        ]);

        $plainPassword = $request->password;

        $user = User::create([
            'name'            => $request->name,
            'email'           => $request->email,
            'password'        => Hash::make($plainPassword),
            'role'            => $request->role,
            'office_location' => $request->office_location,
            'status'          => 'active',
        ]);

        event(new Registered($user));

        // Send welcome email with credentials
        try {
            $user->notify(new NewUserWelcomeNotification($plainPassword));
        } catch (\Exception $e) {
            // Safe to swallow — mail driver is 'log' in dev
        }

        // Audit log
        VerificationActivityLog::create([
            'user_id'              => Auth::id(),
            'activity_type'        => 'edit',
            'activity_description' => "Admin created new user '{$user->name}' with role '{$user->role}' at '{$user->office_location}'.",
            'ip_address'           => $request->ip() ?? '0.0.0.0',
            'user_agent'           => $request->userAgent(),
            'timestamp'            => now(),
            'status'               => 'success',
        ]);

        return redirect()->route('users.index')
            ->with('success', "User '{$user->name}' created successfully. Welcome email sent.");
    }

    /**
     * Show a single user. Admin only.
     */
    public function show(User $user): Response
    {
        return Inertia::render('Users/Show', [
            'user' => $user->only('id', 'name', 'email', 'role', 'office_location', 'status', 'last_login_at', 'created_at'),
        ]);
    }

    /**
     * Show edit form. Admin only.
     */
    public function edit(User $user): Response
    {
        return Inertia::render('Users/Edit', [
            'user' => $user->only('id', 'name', 'email', 'role', 'office_location', 'status'),
        ]);
    }

    /**
     * Update a user. Admin only.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'email'           => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'role'            => ['required', 'string', 'in:Administrator,Field Officer,Compliance Verifier'],
            'office_location' => ['required', 'string', 'max:255'],
            'status'          => ['required', 'in:active,inactive'],
        ]);

        $user->update($request->only('name', 'email', 'role', 'office_location', 'status'));

        VerificationActivityLog::create([
            'user_id'              => Auth::id(),
            'activity_type'        => 'edit',
            'activity_description' => "Admin updated user '{$user->name}' (ID: {$user->id}).",
            'ip_address'           => $request->ip() ?? '0.0.0.0',
            'user_agent'           => $request->userAgent(),
            'timestamp'            => now(),
            'status'               => 'success',
        ]);

        return redirect()->route('users.index')
            ->with('success', "User '{$user->name}' updated successfully.");
    }

    /**
     * Deactivate (soft-delete) a user. Admin only.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        // Prevent self-deletion
        if ($user->id === Auth::id()) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $user->update(['status' => 'inactive']);

        VerificationActivityLog::create([
            'user_id'              => Auth::id(),
            'activity_type'        => 'delete',
            'activity_description' => "Admin deactivated user '{$user->name}' (ID: {$user->id}).",
            'ip_address'           => $request->ip() ?? '0.0.0.0',
            'user_agent'           => $request->userAgent(),
            'timestamp'            => now(),
            'status'               => 'success',
        ]);

        return redirect()->route('users.index')
            ->with('success', "User '{$user->name}' has been deactivated.");
    }
}
