<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatisticaSeries extends Model
{
    protected $table = 'statistica_series';

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'category',
        'unit',
        'frequency',
        'color',
        'description',
        'source',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'sort_order'  => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(StatisticaEntry::class, 'series_id')->orderBy('entry_date');
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function getLatestEntryAttribute(): ?StatisticaEntry
    {
        return $this->entries()->latest('entry_date')->first();
    }

    public function getEntryCountAttribute(): int
    {
        return $this->entries()->count();
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForOrg($query, int $orgId)
    {
        return $query->where('organization_id', $orgId);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}