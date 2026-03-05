<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    public $timestamps = false; // we manage created_at manually

    protected $fillable = [
        'user_id',
        'notification_type',
        'title',
        'message',
        'data',
        'read_at',
        'created_at',
    ];

    protected $casts = [
        'data'       => 'array',
        'read_at'    => 'datetime',
        'created_at' => 'datetime',
    ];

    /* ─── Relationships ───────────────────────────────────── */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /* ─── Scopes ──────────────────────────────────────────── */

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /* ─── Helpers ─────────────────────────────────────────── */

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function markAsRead(): void
    {
        if ($this->read_at === null) {
            $this->update(['read_at' => now()]);
        }
    }

    /** Icon identifier for the frontend based on type */
    public function getIconAttribute(): string
    {
        return match ($this->notification_type) {
            'compliance_alert'           => 'alert',
            'pending_verification'       => 'clock',
            'expiring_period'            => 'calendar',
            'non_compliant_beneficiary'  => 'x-circle',
            default                      => 'bell',
        };
    }

    /** Severity level for colour coding */
    public function getSeverityAttribute(): string
    {
        return match ($this->notification_type) {
            'non_compliant_beneficiary'  => 'high',
            'compliance_alert'           => 'high',
            'pending_verification'       => 'medium',
            'expiring_period'            => 'low',
            default                      => 'low',
        };
    }
}
