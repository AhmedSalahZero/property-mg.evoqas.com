<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorporateExpenseAllocation extends Model
{
    protected $fillable = [
        'company_id',
        'corporate_expense_id',
        'property_id',
        'property_unit_id',
        'unit_label',
        'area',
        'eligibility_status',
        'allocation_pct',
        'allocated_amount',
        'allocated_base_amount',
    ];

    protected $casts = [
        'area'                   => 'decimal:4',
        'allocation_pct'         => 'decimal:4',
        'allocated_amount'       => 'decimal:2',
        'allocated_base_amount'  => 'decimal:2',
    ];

    public function corporateExpense(): BelongsTo
    {
        return $this->belongsTo(CorporateExpense::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function propertyUnit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class);
    }
}
