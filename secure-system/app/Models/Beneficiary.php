<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Beneficiary extends Model
{
    use HasFactory;

    protected $fillable = [
        'bin',
        'verification_token',
        'family_head_name',
        'family_head_birthdate',
        'gender',
        'civil_status',
        'contact_number',
        'email',
        'barangay',
        'municipality',
        'province',
        'zip_code',
        'annual_income',
        'household_size',
        'token_status',
        'is_active',
        'registered_by_user_id',
    ];

    protected $casts = [
        'family_head_birthdate' => 'date',
        'annual_income'         => 'decimal:2',
        'household_size'        => 'integer',
        'is_active'             => 'boolean',
    ];

    /* ─── Relationships ───────────────────────────────────── */

    public function registeredBy()
    {
        return $this->belongsTo(User::class, 'registered_by_user_id');
    }

    public function familyMembers()
    {
        return $this->hasMany(FamilyMember::class);
    }

    public function qrCodes()
    {
        // QrCode model will be added when the QR module is built
        return $this->hasMany(\App\Models\QrCode::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(VerificationActivityLog::class);
    }

    public function duplicateLogsAsPrimary()
    {
        return $this->hasMany(DuplicateDetectionLog::class, 'primary_beneficiary_id');
    }

    public function duplicateLogsAsDuplicate()
    {
        return $this->hasMany(DuplicateDetectionLog::class, 'duplicate_beneficiary_id');
    }

    public function submittedRequirements()
    {
        return $this->hasMany(\App\Models\SubmittedRequirement::class);
    }

    public function complianceSummary()
    {
        return $this->hasOne(\App\Models\ComplianceSummaryCache::class);
    }

    /* ─── Scopes ──────────────────────────────────────────── */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('family_head_name', 'ilike', "%{$term}%")
              ->orWhere('bin', 'ilike', "%{$term}%")
              ->orWhere('contact_number', 'like', "%{$term}%");
        });
    }
}
