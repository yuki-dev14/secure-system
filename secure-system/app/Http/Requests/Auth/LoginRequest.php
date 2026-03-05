<?php

namespace App\Http\Requests\Auth;

use App\Models\VerificationActivityLog;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     * Rate-limited to 5 attempts per minute per email+IP key.
     * All attempts (success & fail) are logged.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $authenticated = Auth::attempt(
            $this->only('email', 'password'),
            $this->boolean('remember')
        );

        if (! $authenticated) {
            RateLimiter::hit($this->throttleKey());

            // Log failed attempt
            $this->logLoginAttempt('failed', 'Invalid credentials provided.');

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        // Update last_login_at
        $user = Auth::user();
        $user->forceFill(['last_login_at' => now()])->save();

        // Log successful login
        $this->logLoginAttempt('success', 'User logged in successfully.', $user->id);
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')) . '|' . $this->ip());
    }

    private function logLoginAttempt(string $status, string $description, ?int $userId = null): void
    {
        VerificationActivityLog::create([
            'user_id'              => $userId,
            'activity_type'        => 'verify',
            'activity_description' => $description . ' Email: ' . $this->email,
            'ip_address'           => $this->ip() ?? '0.0.0.0',
            'user_agent'           => $this->userAgent(),
            'timestamp'            => now(),
            'status'               => $status,
        ]);
    }
}
