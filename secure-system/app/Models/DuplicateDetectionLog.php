<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DuplicateDetectionLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'primary_beneficiary_id',
        'duplicate_beneficiary_id',
        'duplicate_type',
        'detection_date',
        'detected_by_system_or_user',
        'action_taken',
        'merged_or_flagged',
        'resolved_at',
        'resolver_user_id',
        // ── New columns added in 2026_03_03 migration ──
        'confidence_score',
        'detection_details',
        'override_reason',
        'override_by_user_id',
        'override_at',
        'recommendation',
        'status',
    ];

    protected $casts = [
        'detection_date'   => 'datetime',
        'resolved_at'      => 'datetime',
        'override_at'      => 'datetime',
        'detection_details'=> 'array',
        'confidence_score' => 'integer',
    ];

    /* ─── Relationships ───────────── */

    public function primaryBeneficiary()
    {
        return $this->belongsTo(Beneficiary::class, 'primary_beneficiary_id');
    }

    public function duplicateBeneficiary()
    {
        return $this->belongsTo(Beneficiary::class, 'duplicate_beneficiary_id');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolver_user_id');
    }

    public function overrideBy()
    {
        return $this->belongsTo(User::class, 'override_by_user_id');
    }

    /* ─── Scopes ──────────────────── */

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeHighConfidence($query, int $threshold = 80)
    {
        return $query->where('confidence_score', '>=', $threshold);
    }

    /* ─── Helpers ─────────────────── */

    public function getSeverityAttribute(): string
    {
        return match (true) {
            $this->confidence_score >= 90 => 'high',
            $this->confidence_score >= 65 => 'medium',
            default                        => 'low',
        };
    }

    public function isBlockRecommended(): bool
    {
        return $this->recommendation === 'block';
    }
}
