<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvestmentProspect extends Model
{
    protected $fillable = [
        'company_id',
        'created_by',
        'prospect_name',
        'status',
        'nature',
        'country',
        'governorate',
        'province',
        'location',
        'property_category_id',
        'property_type_id',
        'area',
        'unit_of_measurement',
        'purchase_price',
        'currency',
        'expected_monthly_rent',
        'notes',
    ];

    protected $casts = [
        'area'                  => 'decimal:4',
        'purchase_price'        => 'decimal:2',
        'expected_monthly_rent' => 'decimal:2',
    ];

    const STATUS_EVALUATING = 'evaluating';
    const STATUS_PURSUING   = 'pursuing';
    const STATUS_PASSED     = 'passed';
    const STATUS_ACQUIRED   = 'acquired';

    // Same four options as Property::NATURE_* — confirmed decision (July
    // 2026): RAM evaluates acquisitions at both levels, a single unit or a
    // whole building/land/complex made of several units.
    const NATURE_UNIT     = 'unit';
    const NATURE_BUILDING = 'building';
    const NATURE_LAND     = 'land';
    const NATURE_COMPLEX  = 'complex';

    public static function statusLabels(): array
    {
        return [
            self::STATUS_EVALUATING => 'Evaluating',
            self::STATUS_PURSUING   => 'Pursuing',
            self::STATUS_PASSED     => 'Passed',
            self::STATUS_ACQUIRED   => 'Acquired',
        ];
    }

    public static function natureLabels(): array
    {
        return [
            self::NATURE_UNIT     => 'Single Unit',
            self::NATURE_BUILDING => 'Building (multiple units)',
            self::NATURE_LAND     => 'Land',
            self::NATURE_COMPLEX  => 'Complex (multiple units)',
        ];
    }

    public function isMultiUnit(): bool
    {
        return $this->nature !== self::NATURE_UNIT;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function propertyCategory(): BelongsTo
    {
        return $this->belongsTo(PropertyCategory::class);
    }

    public function propertyType(): BelongsTo
    {
        return $this->belongsTo(PropertyType::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function units(): HasMany
    {
        return $this->hasMany(InvestmentProspectUnit::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Total purchase price for the deal — the prospect's own figure for a
     * single Unit, or the SUM of its child units for a Building / Land /
     * Complex. Same "parent carries it directly, multi-unit sums its
     * children" convention as the real Property model. Always in this
     * prospect's own currency (mixed-currency units within one prospect
     * aren't supported — same simplifying assumption Property makes).
     */
    public function totalPurchasePrice(): float
    {
        if (!$this->isMultiUnit()) {
            return (float) ($this->purchase_price ?? 0);
        }
        return (float) $this->units->sum(fn ($u) => (float) $u->purchase_price);
    }

    /**
     * Total expected monthly rent once every unit is fully stabilized —
     * this is the POTENTIAL figure the feasibility engine applies its
     * vacancy/lease-up assumption on top of, exactly like a single unit's
     * expected_monthly_rent.
     */
    public function totalExpectedMonthlyRent(): float
    {
        if (!$this->isMultiUnit()) {
            return (float) ($this->expected_monthly_rent ?? 0);
        }
        return (float) $this->units->sum(fn ($u) => (float) ($u->expected_monthly_rent ?? 0));
    }

    public function unitCount(): int
    {
        return $this->isMultiUnit() ? $this->units->count() : 1;
    }
}
