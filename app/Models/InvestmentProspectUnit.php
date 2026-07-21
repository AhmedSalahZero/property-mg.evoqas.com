<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentProspectUnit extends Model
{
    protected $fillable = [
        'investment_prospect_id',
        'company_id',
        'unit_name',
        'slot_type',
        'property_category_id',
        'property_type_id',
        'area',
        'unit_of_measurement',
        'purchase_price',
        'currency',
        'expected_monthly_rent',
        'sort_order',
    ];

    protected $casts = [
        'area'                  => 'decimal:4',
        'purchase_price'        => 'decimal:2',
        'expected_monthly_rent' => 'decimal:2',
        'sort_order'            => 'integer',
    ];

    const SLOT_BUILT_UNIT = 'built_unit';
    const SLOT_LAND_SLOT  = 'land_slot';

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(InvestmentProspect::class, 'investment_prospect_id');
    }

    public function propertyCategory(): BelongsTo
    {
        return $this->belongsTo(PropertyCategory::class);
    }

    public function propertyType(): BelongsTo
    {
        return $this->belongsTo(PropertyType::class);
    }
}
