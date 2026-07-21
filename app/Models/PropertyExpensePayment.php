<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyExpensePayment extends Model
{
    protected $fillable = [
        'company_id',
        'property_expense_id',
        'payment_date',
        'amount',
        'base_amount',
        'base_currency',
        'fx_rate_used',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount'       => 'decimal:2',
        'base_amount'  => 'decimal:2',
        'fx_rate_used' => 'decimal:6',
    ];

    // ── Relationships ────────────────────────────────────────────────

    public function expense(): BelongsTo
    {
        return $this->belongsTo(PropertyExpense::class, 'property_expense_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}