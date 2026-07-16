<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyKpiConfig extends Model
{
    protected $fillable = [
        'company_id',
        'kpi_definition_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function definition()
    {
        return $this->belongsTo(KpiDefinition::class, 'kpi_definition_id');
    }
}