<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FixedAssetSetting extends Model
{
    protected $fillable = [
        'company_id',
        'asset_name',
        'asset_type',
        'is_employee_asset',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active'         => 'boolean',
        'is_employee_asset' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
