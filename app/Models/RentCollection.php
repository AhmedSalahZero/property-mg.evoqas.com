<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentCollection extends Model
{
    protected $fillable = [
        'rent_contract_id',
        'company_id',
        'collection_date',
        'period_from',
        'period_to',
        'monthly_rent_basis',
        'collection_amount',
        'currency',
        'status',
        'collected_date',
        'notes',
    ];

    protected $casts = [
        'collection_date'    => 'date',
        'period_from'        => 'date',
        'period_to'          => 'date',
        'collected_date'     => 'date',
        'monthly_rent_basis' => 'decimal:2',
        'collection_amount'  => 'decimal:2',
    ];

    const STATUS_PENDING   = 'pending';
    const STATUS_COLLECTED = 'collected';
    const STATUS_OVERDUE   = 'overdue';

    public function contract(): BelongsTo
    {
        return $this->belongsTo(RentContract::class, 'rent_contract_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}