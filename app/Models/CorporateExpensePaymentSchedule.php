<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorporateExpensePaymentSchedule extends Model
{
    protected $fillable = [
        'company_id',
        'corporate_expense_id',
        'percentage',
        'amount',
        'forecasted_date',
        'payment_term',
        'sort_order',
    ];

    protected $casts = [
        'forecasted_date' => 'date',
        'percentage'       => 'decimal:2',
        'amount'           => 'decimal:2',
    ];

    public function corporateExpense(): BelongsTo
    {
        return $this->belongsTo(CorporateExpense::class);
    }
}
