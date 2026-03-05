<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Stores pre-aggregated compliance percentages per beneficiary.
 * One row per beneficiary (enforced by UNIQUE constraint in migration).
 */
class ComplianceSummaryCache extends Model
{
    // No auto-increment timestamps — we use our own last_updated_at column
    public $timestamps = false;

    protected $table = 'compliance_summary_cache';

    protected $fillable = [
        'beneficiary_id',
        'education_compliance_percentage',
        'health_compliance_percentage',
        'fds_compliance_percentage',
        'overall_compliance_status',
        'missing_requirements',
        'last_updated_at',
        'cache_validity',
    ];

    protected $casts = [
        'education_compliance_percentage' => 'decimal:2',
        'health_compliance_percentage'    => 'decimal:2',
        'fds_compliance_percentage'       => 'decimal:2',
        'missing_requirements'            => 'array',
        'last_updated_at'                 => 'datetime',
        'cache_validity'                  => 'datetime',
    ];

    /* ─── Relationships ───────────────────────────────────── */

    public function beneficiary()
    {
        return $this->belongsTo(Beneficiary::class);
    }

    /* ─── Helpers ─────────────────────────────────────────── */

    /** True if cache has expired or validity is null */
    public function isExpired(): bool
    {
        if ($this->cache_validity === null) {
            return false; // no TTL set — never expires
        }
        return $this->cache_validity->isPast();
    }

    /** Get overall status as a human-readable label */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->overall_compliance_status) {
            'compliant'     => 'Compliant',
            'partial'       => 'Partial',
            'non_compliant' => 'Non-Compliant',
            default         => ucfirst($this->overall_compliance_status),
        };
    }
}
