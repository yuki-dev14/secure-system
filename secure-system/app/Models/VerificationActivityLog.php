<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerificationActivityLog extends Model
{
    const UPDATED_AT = null; // immutable — no updated_at

    protected $fillable = [
        'user_id',
        'beneficiary_id',
        'activity_type',
        'activity_category',
        'activity_description',
        'ip_address',
        'user_agent',
        'timestamp',
        'status',
        'remarks',
        'old_values',
        'new_values',
        'request_data',
        'response_status',
        'execution_time',
        'resource_type',
        'resource_id',
        'severity',
        'is_acknowledged',
        'acknowledged_at',
        'acknowledged_by',
    ];

    protected $casts = [
        'timestamp'       => 'datetime',
        'acknowledged_at' => 'datetime',
        'old_values'      => 'array',
        'new_values'      => 'array',
        'request_data'    => 'array',
        'is_acknowledged' => 'boolean',
        'execution_time'  => 'float',
        'response_status' => 'integer',
    ];

    // ─── Relationships ───────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function beneficiary()
    {
        return $this->belongsTo(Beneficiary::class);
    }

    public function acknowledgedBy()
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    // ─── Scopes ──────────────────────────────────────────────────

    public function scopeByType($query, $type)
    {
        return $query->where('activity_type', $type);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('activity_category', $category);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeSecurity($query)
    {
        return $query->where('activity_category', 'security');
    }

    public function scopeUnacknowledged($query)
    {
        return $query->where('is_acknowledged', false);
    }

    public function scopeInDateRange($query, $from, $to)
    {
        if ($from) {
            $query->whereDate('timestamp', '>=', $from);
        }
        if ($to) {
            $query->whereDate('timestamp', '<=', $to);
        }
        return $query;
    }

    // ─── Computed ────────────────────────────────────────────────

    public function getChangedFieldsAttribute(): array
    {
        if (!$this->old_values || !$this->new_values) {
            return [];
        }
        $changed = [];
        foreach ($this->new_values as $key => $newVal) {
            $oldVal = $this->old_values[$key] ?? '__MISSING__';
            if ($oldVal !== $newVal) {
                $changed[$key] = ['old' => $oldVal, 'new' => $newVal];
            }
        }
        return $changed;
    }

    public function getSeverityColorAttribute(): string
    {
        return match($this->severity) {
            'critical' => '#dc2626',
            'high'     => '#ea580c',
            'medium'   => '#d97706',
            'low'      => '#65a30d',
            default    => '#94a3b8',
        };
    }
}
