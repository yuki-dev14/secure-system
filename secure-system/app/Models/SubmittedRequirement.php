<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SubmittedRequirement extends Model
{
    use HasFactory;

    protected $fillable = [
        'beneficiary_id',
        'requirement_type',
        'file_path',
        'file_name',
        'file_size',
        'file_type',
        'submitted_at',
        'submitted_by_user_id',
        'approval_status',
        'approval_date',
        'approved_by_user_id',
        'rejection_reason',
        'expiration_date',
    ];

    protected $casts = [
        'submitted_at'    => 'datetime',
        'approval_date'   => 'datetime',
        'expiration_date' => 'date',
        'file_size'       => 'integer',
    ];

    /* ─── Relationships ─────────────────────────────────────── */

    public function beneficiary()
    {
        return $this->belongsTo(Beneficiary::class);
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /* ─── Scopes ─────────────────────────────────────────────── */

    public function scopePending($query)
    {
        return $query->where('approval_status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('approval_status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('approval_status', 'rejected');
    }

    public function scopeExpired($query)
    {
        return $query->whereNotNull('expiration_date')
                     ->where('expiration_date', '<', now()->toDateString());
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->whereNotNull('expiration_date')
                     ->whereBetween('expiration_date', [
                         now()->toDateString(),
                         now()->addDays($days)->toDateString(),
                     ]);
    }

    /* ─── Helpers ─────────────────────────────────────────────── */

    public function isExpired(): bool
    {
        return $this->expiration_date && $this->expiration_date->isPast();
    }

    public function daysUntilExpiration(): ?int
    {
        if (! $this->expiration_date) {
            return null;
        }
        return max(0, (int) now()->diffInDays($this->expiration_date, false));
    }

    public function fileSizeFormatted(): string
    {
        $size = $this->file_size;
        if ($size < 1024)       return "{$size} B";
        if ($size < 1048576)    return round($size / 1024, 1) . ' KB';
        return round($size / 1048576, 1) . ' MB';
    }
}
