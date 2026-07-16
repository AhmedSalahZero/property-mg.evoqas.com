<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyInstallmentDue extends Model
{
    protected $fillable = [
        'company_id',
        'property_id',
        'plan_id',
        'due_type',
        'due_date',
        'amount',
        'currency',
        'status',
        'paid_date',
        'notes',
        'sort_order',
    ];

    protected $casts = [
        'due_date'   => 'date',
        'paid_date'  => 'date',
        'amount'     => 'decimal:2',
        'sort_order' => 'integer',
    ];

    // ── Status Constants ─────────────────────────────────────────────────────

    const STATUS_PENDING  = 'pending';
    const STATUS_PAID     = 'paid';
    const STATUS_OVERDUE  = 'overdue';

    // ── Due Type Constants ───────────────────────────────────────────────────

    const TYPE_SIGNING     = 'signing';
    const TYPE_RESERVATION = 'reservation';
    const TYPE_INSTALLMENT = 'installment';
    const TYPE_ANNUAL      = 'annual';
    const TYPE_DELIVERY    = 'delivery';
    const TYPE_MAINTENANCE = 'maintenance';
    const TYPE_VARIABLE    = 'variable';

    // ── Relationships ────────────────────────────────────────────────────────

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PropertyInstallmentPlan::class, 'plan_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', self::STATUS_OVERDUE);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function typeLabel(): string
    {
        return match ($this->due_type) {
            'signing'     => 'Contract Signing',
            'reservation' => 'Reservation',
            'installment' => 'Installment',
            'annual'      => 'Annual',
            'delivery'    => 'Delivery',
            'maintenance' => 'Maintenance',
            'variable'    => 'Payment',
            default       => ucfirst($this->due_type),
        };
    }
}