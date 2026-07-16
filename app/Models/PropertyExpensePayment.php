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
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount'       => 'decimal:2',
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