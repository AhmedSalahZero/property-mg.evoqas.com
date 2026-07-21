<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentAnalysis extends Model
{
    protected $fillable = [
        'company_id',
        'created_by',
        'investment_prospect_id',
        'snapshot_label',
        'funding_path',
        'exit_year',
        'discount_rate_pct',
        'corporate_tax_rate_pct',
        'selling_costs_pct',
        'exit_value_method',
        'rent_collection_interval',
        'inflation_rate_pct',
        'scenario_inputs',
        'funding_params',
        'computed_result',
        'npv_base_case',
        'irr_base_case',
        'analyst_recommendation',
        'share_token',
        'share_token_created_at',
    ];

    protected $casts = [
        'scenario_inputs'         => 'array',
        'funding_params'          => 'array',
        'computed_result'         => 'array',
        'exit_year'                => 'integer',
        'discount_rate_pct'        => 'float',
        'corporate_tax_rate_pct'   => 'float',
        'selling_costs_pct'        => 'float',
        'inflation_rate_pct'       => 'float',
        'npv_base_case'            => 'float',
        'irr_base_case'            => 'float',
        'share_token_created_at'   => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(InvestmentProspect::class, 'investment_prospect_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
