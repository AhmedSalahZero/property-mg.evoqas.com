<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorporateExpensePayment extends Model
{
    protected $fillable = [
        'company_id',
        'corporate_expense_id',
        'payment_date',
        'amount',
        'base_amount',
        'base_currency',
        'fx_rate_used',
        'notes',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount'       => 'decimal:2',
        'base_amount'  => 'decimal:2',
        'fx_rate_used' => 'decimal:6',
    ];

    public function corporateExpense(): BelongsTo
    {
        return $this->belongsTo(CorporateExpense::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
