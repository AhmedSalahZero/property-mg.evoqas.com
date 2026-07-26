<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertySaleDue extends Model
{
    protected $fillable = [
        'company_id',
        'property_sale_id',
        'due_type',
        'due_date',
        'amount',
        'currency',
        'status',
        'collected_date',
        'notes',
        'sort_order',
    ];

    protected $casts = [
        'due_date'       => 'date',
        'collected_date' => 'date',
        'amount'         => 'decimal:2',
        'sort_order'     => 'integer',
    ];

    const TYPE_DOWN_PAYMENT = 'down_payment';
    const TYPE_INSTALLMENT  = 'installment';

    const STATUS_PENDING   = 'pending';
    const STATUS_COLLECTED = 'collected';
    const STATUS_OVERDUE   = 'overdue';

    public function propertySale(): BelongsTo
    {
        return $this->belongsTo(PropertySale::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function typeLabel(): string
    {
        return match ($this->due_type) {
            self::TYPE_DOWN_PAYMENT => 'Down Payment',
            default                  => 'Installment',
        };
    }

    /**
     * Same idempotent "flip pending → overdue past due_date" pattern as
     * RentCollection::autoMarkOverdue() / PropertyInstallmentDue::autoMarkOverdue()
     * — see MarkOverdueRecords, the daily command that calls this.
     */
    public static function autoMarkOverdue(): int
    {
        return static::where('status', self::STATUS_PENDING)
            ->where('due_date', '<', now()->toDateString())
            ->update(['status' => self::STATUS_OVERDUE]);
    }
}
