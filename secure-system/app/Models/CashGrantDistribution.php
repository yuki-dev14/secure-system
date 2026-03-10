<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CashGrantDistribution extends Model
{
    use HasFactory;

    protected $table = 'cash_grant_distributions';

    protected $fillable = [
        'beneficiary_id',
        'payout_amount',
        'payout_period',
        'payout_month',
        'payout_year',
        'distributed_at',
        'distributed_by_user_id',
        'approved_by_user_id',
        'payment_method',
        'transaction_reference_number',
        'received_signature_path',
        'remarks',
    ];

    protected $casts = [
        'payout_amount'  => 'decimal:2',
        'payout_month'   => 'integer',
        'payout_year'    => 'integer',
        'distributed_at' => 'datetime',
    ];

    /* ─── Relationships ───────────────────────────────────── */

    public function beneficiary()
    {
        return $this->belongsTo(Beneficiary::class);
    }

    public function distributedBy()
    {
        return $this->belongsTo(User::class, 'distributed_by_user_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /* ─── Scopes ──────────────────────────────────────────── */

    public function scopeForPeriod($query, int $month, int $year)
    {
        return $query->where('payout_month', $month)->where('payout_year', $year);
    }

    public function scopeForBeneficiary($query, int $beneficiaryId)
    {
        return $query->where('beneficiary_id', $beneficiaryId);
    }
}
