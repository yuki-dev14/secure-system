<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class QrCode extends Model
{
    use HasFactory;

    protected $table = 'qr_codes';

    protected $fillable = [
        'beneficiary_id',
        'verification_token',
        'qr_image_path',
        'qr_image_url',
        'generated_at',
        'expires_at',
        'is_valid',
        'regenerated_at',
        'regenerated_reason',
        'metadata',
    ];

    protected $casts = [
        'generated_at'   => 'datetime',
        'expires_at'     => 'datetime',
        'regenerated_at' => 'datetime',
        'is_valid'       => 'boolean',
        'metadata'       => 'array',
    ];

    /* ─── Relationships ─────────────────────────────────── */

    public function beneficiary()
    {
        return $this->belongsTo(Beneficiary::class);
    }

    /* ─── Scopes ─────────────────────────────────────────── */

    public function scopeValid($query)
    {
        return $query->where('is_valid', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_valid', true)
                     ->where(function ($q) {
                         $q->whereNull('expires_at')
                           ->orWhere('expires_at', '>', now());
                     });
    }

    public function scopeExpired($query)
    {
        return $query->where('is_valid', true)
                     ->where('expires_at', '<=', now());
    }
}
