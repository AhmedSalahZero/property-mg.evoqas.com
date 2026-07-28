<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KeepOrSellAnalysis extends Model
{
    protected $fillable = [
        'company_id',
        'created_by',
        'property_id',
        'property_unit_id',
        'snapshot_label',
        'market_value',
        'selling_costs_pct',
        'net_sale_proceeds',
        'holding_years',
        'evaluation_month',
        'rent_growth_rate_pct',
        'other_opex_pct',
        'corporate_tax_rate_pct',
        'discount_rate_pct',
        'exit_cap_rate_pct',
        'npv_hold',
        'irr_hold',
        'terminal_value',
        'auto_recommendation',
        'auto_flags',
        'annual_cashflows',
        'analyst_recommendation',
        'share_token',
        'share_token_created_at',
    ];

    protected $casts = [
        'auto_flags'       => 'array',
        'annual_cashflows' => 'array',
        'share_token_created_at' => 'datetime',
        'market_value'         => 'float',
        'selling_costs_pct'    => 'float',
        'net_sale_proceeds'    => 'float',
        'holding_years'        => 'integer',
        'rent_growth_rate_pct' => 'float',
        'other_opex_pct'       => 'float',
        'corporate_tax_rate_pct' => 'float',
        'discount_rate_pct'    => 'float',
        'exit_cap_rate_pct'    => 'float',
        'npv_hold'             => 'float',
        'irr_hold'             => 'float',
        'terminal_value'       => 'float',
    ];

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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}