<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyCategory extends Model
{
    protected $fillable = [
        'company_id',
        'category_name',
        'is_system',
        'sort_order',
    ];

    protected $casts = [
        'is_system'  => 'boolean',
    ];

    // ── The 5 default categories seeded for every new company ─────────
    public const SYSTEM_DEFAULTS = [
        'Administrative',
        'Commercial',
        'Industrial',
        'Medical',
        'Residential',
    ];

    // ── Relationships ─────────────────────────────────────────────────
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function types(): HasMany
    {
        return $this->hasMany(PropertyType::class)->orderBy('sort_order')->orderBy('id');
    }

    // ── Seed defaults for a company if none exist yet ─────────────────
    public static function seedDefaults(int $companyId): void
    {
        $exists = static::where('company_id', $companyId)->exists();
        if ($exists) return;

        foreach (self::SYSTEM_DEFAULTS as $index => $name) {
            static::create([
                'company_id'    => $companyId,
                'category_name' => $name,
                'is_system'     => true,
                'sort_order'    => $index,
            ]);
        }
    }
}