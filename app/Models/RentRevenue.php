<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentRevenue extends Model
{
    protected $fillable = [
        'rent_contract_id',
        'company_id',
        'revenue_date',
        'period_label',
        'monthly_rent',
        'revenue_amount',
        'currency',
        'year_number',
    ];

    protected $casts = [
        'revenue_date'   => 'date',
        'monthly_rent'   => 'decimal:2',
        'revenue_amount' => 'decimal:2',
        'year_number'    => 'integer',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(RentContract::class, 'rent_contract_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}