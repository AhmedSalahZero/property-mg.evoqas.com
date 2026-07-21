<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Customer extends Model
{
    protected $fillable = [
        'company_id',
        'customer_name',
        'business_sector',
        'tenant_nature',
        'is_related_party',
        'source',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active'        => 'boolean',
        'is_related_party' => 'boolean',
        'sort_order'       => 'integer',
    ];

    // ── Sources ──────────────────────────────────────────────────────────────

    const SOURCE_MANUAL   = 'manual';
    const SOURCE_IMPORTED = 'imported';

    // ── Relationships ────────────────────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    // Fix for audit finding M-1 — importFromSalesData() used to live here,
    // pulling distinct customer_name/business_sector pairs from the
    // sales_data table. Sales Analysis (and its sales_data table) was
    // permanently dropped in the April 2026 cleanup session, so this method
    // has queried a table that no longer exists ever since — dormant
    // dead code that nothing in the app calls, but would throw a hard SQL
    // error ("table doesn't exist") if anything ever did. Removed rather
    // than left as a trap for a future developer.
}