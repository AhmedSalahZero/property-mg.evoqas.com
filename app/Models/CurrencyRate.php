<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurrencyRate extends Model
{
    protected $fillable = [
        'company_id',
        'currency',
        'rate_date',
        'rate',
        'source',
        'created_by',
    ];

    protected $casts = [
        'rate_date' => 'date',
        'rate'      => 'decimal:6',
    ];

    const SOURCE_MANUAL             = 'manual';
    const SOURCE_EXCEL_IMPORT        = 'excel_import';
    const SOURCE_STATISTICA_IMPORT   = 'statistica_import';
    const SOURCE_CONTRACT_ENTRY      = 'contract_entry';

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
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
