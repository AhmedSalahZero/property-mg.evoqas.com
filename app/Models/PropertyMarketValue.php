<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyMarketValue extends Model
{
    protected $fillable = [
        'company_id',
        'property_id',
        'property_unit_id',
        'market_value',
        'value_date',
        'notes',
    ];

    protected $casts = [
        'market_value'     => 'decimal:2',
        'property_id'      => 'integer',
        'property_unit_id' => 'integer',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function propertyUnit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class);
    }
}