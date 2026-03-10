<?php

namespace App\Models;

use App\Traits\DataChangeTracker;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FamilyMember extends Model
{
    use HasFactory, DataChangeTracker;

    // No updated_at managed separately; migration has no timestamps() call
    // The migration actually has no timestamps, so we disable them.
    public $timestamps = false;

    protected $fillable = [
        'beneficiary_id',
        'full_name',
        'birthdate',
        'gender',
        'relationship_to_head',
        'birth_certificate_no',
        'school_enrollment_status',
        'health_center_registered',
    ];

    protected $casts = [
        'birthdate'               => 'date',
        'health_center_registered'=> 'boolean',
    ];

    /* ─── Computed attributes ─────────────────────────────── */

    /** Age in full years */
    public function getAgeAttribute(): int
    {
        return Carbon::parse($this->birthdate)->age;
    }

    /** True when age falls in school-age range 5–21 */
    public function getIsSchoolAgeAttribute(): bool
    {
        $age = $this->age;
        return $age >= 5 && $age <= 21;
    }

    /** True when age is 0–5 (early childhood health monitoring) */
    public function getNeedsHealthMonitoringAttribute(): bool
    {
        return $this->age <= 5;
    }

    /* ─── Relationships ───────────────────────────────────── */

    public function beneficiary()
    {
        return $this->belongsTo(Beneficiary::class);
    }

    public function complianceRecords()
    {
        return $this->hasMany(ComplianceRecord::class);
    }
}
