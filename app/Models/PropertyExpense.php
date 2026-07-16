<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PropertyExpense extends Model
{
    protected $fillable = [
        'company_id',
        'property_id',
        'expense_category_id',
        'expense_item_id',
        'expense_date',
        'expense_amount',
        'currency',
        'fx_rate',
        'notes',
        'status',
        'created_by',
    ];

    protected $casts = [
        'expense_date'   => 'date',
        'expense_amount' => 'decimal:2',
        'fx_rate'        => 'decimal:6',
    ];

    const STATUS_UNPAID        = 'unpaid';
    const STATUS_PARTIALLY_PAID = 'partially_paid';
    const STATUS_FULLY_PAID    = 'fully_paid';

    // ── Relationships ────────────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    public function expenseItem(): BelongsTo
    {
        return $this->belongsTo(ExpenseItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PropertyExpensePayment::class)->orderBy('payment_date');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Helpers ──────────────────────────────────────────────────────

    /**
     * Recalculate and persist status based on current payments total.
     */
    public function recalculateStatus(): void
    {
        $paid = $this->payments()->sum('amount');

        if ($paid <= 0) {
            $status = self::STATUS_UNPAID;
        } elseif ($paid >= $this->expense_amount) {
            $status = self::STATUS_FULLY_PAID;
        } else {
            $status = self::STATUS_PARTIALLY_PAID;
        }

        $this->update(['status' => $status]);
    }

    /**
     * Total paid amount across all payments.
     */
    public function totalPaid(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    /**
     * Outstanding balance.
     */
    public function balance(): float
    {
        return max(0, (float) $this->expense_amount - $this->totalPaid());
    }
}