<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fix for audit finding H-4 — persists the Cash Forecast page's manually
 * entered rows (Salaries, New Hirings, Other Collections, Other Payments)
 * that previously lived only in Vue's in-memory state and were lost on
 * every page refresh. One row per company; each section is stored as a
 * JSON blob shaped exactly like the frontend's own state, so the frontend
 * can load/save it with no reshaping in either direction.
 */
class CashForecastManualInput extends Model
{
    protected $fillable = [
        'company_id',
        'salaries',
        'new_hirings',
        'other_collections',
        'other_payments',
        'updated_by',
    ];

    protected $casts = [
        'salaries'          => 'array',
        'new_hirings'       => 'array',
        'other_collections' => 'array',
        'other_payments'    => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
