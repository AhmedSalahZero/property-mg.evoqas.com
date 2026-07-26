<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PropertySale extends Model
{
    protected $fillable = [
        'company_id',
        'sale_batch_id',
        'property_id',
        'property_unit_id',
        'sale_date',
        'buyer_name',
        'area_at_sale',
        'price_per_sqm',
        'sale_price',
        'currency',
        'selling_costs_pct',
        'net_sale_proceeds',
        'net_sale_proceeds_base_amount',
        'book_value_base_amount_at_sale',
        'realized_gain_loss',
        'base_currency',
        'fx_rate_used',
        'payment_method',
        'payment_terms_notes',
        'rent_contract_id',
        'warnings',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'sale_date'                      => 'date',
        'area_at_sale'                   => 'decimal:4',
        'price_per_sqm'                  => 'decimal:2',
        'sale_price'                     => 'decimal:2',
        'selling_costs_pct'              => 'decimal:2',
        'net_sale_proceeds'              => 'decimal:2',
        'net_sale_proceeds_base_amount'  => 'decimal:2',
        'book_value_base_amount_at_sale' => 'decimal:2',
        'realized_gain_loss'             => 'decimal:2',
        'fx_rate_used'                   => 'decimal:6',
    ];

    const PAYMENT_CASH         = 'cash';
    const PAYMENT_INSTALLMENTS = 'installments';

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

    public function dues(): HasMany
    {
        return $this->hasMany(PropertySaleDue::class);
    }

    public function rentContract(): BelongsTo
    {
        return $this->belongsTo(RentContract::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}
