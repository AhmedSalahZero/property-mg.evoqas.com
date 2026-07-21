<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CorporateExpense extends Model
{
    protected $fillable = [
        'company_id',
        'expense_category_id',
        'expense_item_id',
        'expense_date',
        'expense_amount',
        'currency',
        'base_amount',
        'base_currency',
        'fx_rate_used',
        'fx_rate',
        'allocation_scope',
        'notes',
        'status',
        'created_by',
    ];

    protected $casts = [
        'expense_date'   => 'date',
        'expense_amount' => 'decimal:2',
        'base_amount'    => 'decimal:2',
        'fx_rate_used'   => 'decimal:6',
        'fx_rate'        => 'decimal:6',
    ];

    const SCOPE_OCCUPIED                  = 'occupied';
    const SCOPE_ALL_INCLUDE_NOT_DELIVERED = 'all_include_not_delivered';
    const SCOPE_ALL_EXCLUDE_NOT_DELIVERED = 'all_exclude_not_delivered';
    const SCOPE_CUSTOM                    = 'custom';

    const STATUS_UNPAID         = 'unpaid';
    const STATUS_PARTIALLY_PAID = 'partially_paid';
    const STATUS_FULLY_PAID     = 'fully_paid';

    public static function scopeLabels(): array
    {
        return [
            self::SCOPE_OCCUPIED                  => 'Occupied Units',
            self::SCOPE_ALL_INCLUDE_NOT_DELIVERED => 'All Units (Include Not-Delivered)',
            self::SCOPE_ALL_EXCLUDE_NOT_DELIVERED => 'All Units (Exclude Not-Delivered)',
            self::SCOPE_CUSTOM                    => 'Custom Selection',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
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
        return $this->hasMany(CorporateExpensePayment::class)->orderBy('payment_date');
    }

    /**
     * Same forecasted payment schedule repeater as PropertyExpense — see
     * that model's docblock on paymentSchedule().
     */
    public function paymentSchedule(): HasMany
    {
        return $this->hasMany(CorporateExpensePaymentSchedule::class)->orderBy('forecasted_date');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CorporateExpenseAllocation::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Helpers ──────────────────────────────────────────────────────

    /**
     * Same three-tier status logic as PropertyExpense — see logic
     * reference §10 (Expense-Payment-Status-Auto-Calculation).
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

    public function totalPaid(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function balance(): float
    {
        return max(0, (float) $this->expense_amount - $this->totalPaid());
    }
}
