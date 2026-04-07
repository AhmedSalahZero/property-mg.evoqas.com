<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Property extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'nature',
        'property_name',
        'property_code',
        'ownership',
        'country',
        'governorate',
        'province',
        'location',
        'property_category_id',
        'property_type_id',
        'area',
        'unit_of_measurement',
        'acquisition_cost',
        'currency',
        'acquisition_date',
        'book_value',
        'accumulated_depreciation',
        'monthly_depreciation',
        'depreciation_duration_months',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'area'                        => 'decimal:4',
        'acquisition_cost'            => 'decimal:2',
        'book_value'                  => 'decimal:2',
        'accumulated_depreciation'    => 'decimal:2',
        'monthly_depreciation'        => 'decimal:2',
        'depreciation_duration_months'=> 'integer',
        'is_active'                   => 'boolean',
        'sort_order'                  => 'integer',
    ];

    // ── Nature constants ─────────────────────────────────────────────────────
    const NATURE_UNIT     = 'unit';
    const NATURE_BUILDING = 'building';
    const NATURE_LAND     = 'land';
    const NATURE_COMPLEX  = 'complex';

    // ── Ownership constants ──────────────────────────────────────────────────
    const OWNERSHIP_FULLY_OWNED  = 'fully_owned';
    const OWNERSHIP_INSTALLMENTS = 'installments';
    const OWNERSHIP_USUFRUCT     = 'usufruct';
    const OWNERSHIP_MANAGED      = 'managed';

    // ── Ownership labels ─────────────────────────────────────────────────────
    public static function ownershipLabels(): array
    {
        return [
            self::OWNERSHIP_FULLY_OWNED  => 'Fully Owned',
            self::OWNERSHIP_INSTALLMENTS => 'Owned with Installments',
            self::OWNERSHIP_USUFRUCT     => 'Usufruct (Right of Use)',
            self::OWNERSHIP_MANAGED      => 'Managed',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────────────

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

    public function units(): HasMany
    {
        return $this->hasMany(PropertyUnit::class)->orderBy('sort_order')->orderBy('id');
    }

    public function marketValues(): HasMany
    {
        return $this->hasMany(PropertyMarketValue::class)->orderByDesc('value_date');
    }

    public function installmentPlan(): HasOne
    {
        return $this->hasOne(PropertyInstallmentPlan::class);
    }

    public function rentContracts(): HasMany
    {
        return $this->hasMany(RentContract::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Returns the latest market value entry or null.
     */
    public function latestMarketValue(): ?PropertyMarketValue
    {
        return $this->marketValues()->first();
    }

    /**
     * True if this nature has child units (building / land / complex).
     */
    public function hasUnits(): bool
    {
        return in_array($this->nature, [
            self::NATURE_BUILDING,
            self::NATURE_LAND,
            self::NATURE_COMPLEX,
        ]);
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfNature($query, string $nature)
    {
        return $query->where('nature', $nature);
    }
}