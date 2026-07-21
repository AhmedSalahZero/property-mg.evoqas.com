<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PropertyUnit extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'property_id',
        'slot_type',
        'unit_name',
        'unit_code',
        'ownership',
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
        'acquisition_cost_base_amount',
        'book_value_base_amount',
        'base_currency',
        'fx_rate_used',
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
        'acquisition_cost_base_amount'=> 'decimal:2',
        'book_value_base_amount'      => 'decimal:2',
        'fx_rate_used'                => 'decimal:6',
    ];

    // ── Slot type constants ──────────────────────────────────────────────────
    const SLOT_BUILT_UNIT = 'built_unit';
    const SLOT_LAND_SLOT  = 'land_slot';

    // ── Relationships ────────────────────────────────────────────────────────

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
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

    public function marketValues(): HasMany
    {
        return $this->hasMany(PropertyMarketValue::class, 'property_unit_id')->orderByDesc('value_date');
    }

    public function rentContracts(): HasMany
    {
        return $this->hasMany(RentContract::class, 'property_unit_id');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function isLandSlot(): bool
    {
        return $this->slot_type === self::SLOT_LAND_SLOT;
    }

    public function latestMarketValue(): ?PropertyMarketValue
    {
        return $this->marketValues()->first();
    }
}