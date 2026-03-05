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
        'activity_description',
        'ip_address',
        'user_agent',
        'timestamp',
        'status',
        'remarks',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
