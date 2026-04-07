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

    /**
     * Pull distinct customer_name + business_sector pairs from sales_data
     * for a given company and upsert into customers table.
     * Returns count of newly inserted records.
     */
    public static function importFromSalesData(int $companyId): int
    {
        $rows = \Illuminate\Support\Facades\DB::table('sales_data')
            ->where('company_id', $companyId)
            ->whereNotNull('customer_name')
            ->where('customer_name', '!=', '')
            ->selectRaw('TRIM(customer_name) as customer_name, TRIM(business_sector) as business_sector')
            ->distinct()
            ->get();

        $inserted = 0;

        foreach ($rows as $row) {
            $exists = static::where('company_id', $companyId)
                ->where('customer_name', $row->customer_name)
                ->exists();

            if (! $exists) {
                static::create([
                    'company_id'      => $companyId,
                    'customer_name'   => $row->customer_name,
                    'business_sector' => $row->business_sector ?: null,
                    'tenant_nature'   => null,
                    'is_related_party'=> false,
                    'source'          => self::SOURCE_IMPORTED,
                    'is_active'       => true,
                    'sort_order'      => static::where('company_id', $companyId)->max('sort_order') + 1,
                ]);
                $inserted++;
            }
        }

        return $inserted;
    }
}