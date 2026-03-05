<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComplianceRecord extends Model
{
    protected $fillable = [
        'family_member_id',
        'compliance_type',
        'compliance_period',
        'school_name',
        'enrollment_status',
        'attendance_percentage',
        'health_checkup_date',
        'vaccination_status',
        'fds_attendance',
        'verified_at',
        'verified_by_user_id',
        'is_compliant',
    ];

    protected $casts = [
        'health_checkup_date' => 'date',
        'verified_at'         => 'datetime',
        'fds_attendance'      => 'boolean',
        'is_compliant'        => 'boolean',
        'attendance_percentage'=> 'decimal:2',
    ];

    public function familyMember()
    {
        return $this->belongsTo(FamilyMember::class);
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }
}
