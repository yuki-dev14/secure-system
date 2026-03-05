<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'office_location',
        'status',
        'last_login_at',
        'password_changed_at',
        'password_history',
        'two_factor_enabled',
        'two_factor_secret',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'password_history',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'   => 'datetime',
            'last_login_at'       => 'datetime',
            'password_changed_at' => 'datetime',
            'password_history'    => 'array',
            'password'            => 'hashed',
            'two_factor_enabled'  => 'boolean',
        ];
    }

    /* ─── Role Helpers ────────────────────────────────────────── */

    public function isAdministrator(): bool   { return $this->role === 'Administrator'; }
    public function isFieldOfficer(): bool    { return $this->role === 'Field Officer'; }
    public function isComplianceVerifier(): bool { return $this->role === 'Compliance Verifier'; }

    public function hasRole(string|array $roles): bool
    {
        return in_array($this->role, (array) $roles);
    }

    /* ─── Permission Helpers ─────────────────────────────────── */

    public static function permissionsFor(string $role): array
    {
        return match ($role) {
            'Administrator' => [
                'user.create', 'user.view', 'user.update', 'user.delete',
                'beneficiary.create', 'beneficiary.view', 'beneficiary.update', 'beneficiary.delete',
                'qr.scan', 'document.submit', 'document.view',
                'compliance.view', 'compliance.approve', 'compliance.reject',
                'payout.authorize', 'reports.view', 'settings.manage',
            ],
            'Field Officer' => [
                'beneficiary.create', 'beneficiary.view', 'beneficiary.update',
                'qr.scan', 'document.submit', 'document.view',
            ],
            'Compliance Verifier' => [
                'beneficiary.view', 'compliance.view',
                'compliance.approve', 'compliance.reject',
                'payout.authorize', 'document.view',
            ],
            default => [],
        };
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, self::permissionsFor($this->role));
    }


    /* ─── Password History (Reuse Prevention) ────────────────── */

    /**
     * Check whether a given plain-text password was used in the last N passwords.
     */
    public function wasPasswordUsedRecently(string $plainPassword, int $historyCount = 5): bool
    {
        $history = $this->password_history ?? [];

        foreach (array_slice($history, 0, $historyCount) as $oldHash) {
            if (Hash::check($plainPassword, $oldHash)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Append the current password hash to history (keep last 5).
     */
    public function pushPasswordToHistory(): void
    {
        $history = $this->password_history ?? [];
        array_unshift($history, $this->getOriginal('password') ?? $this->password);
        $this->password_history = array_slice($history, 0, 5);
    }

    /* ─── Relationships ──────────────────────────────────────── */

    public function activityLogs()
    {
        return $this->hasMany(VerificationActivityLog::class);
    }
}
