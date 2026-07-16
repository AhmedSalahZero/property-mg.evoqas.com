<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatisticaEntry extends Model
{
    protected $table = 'statistica_entries';

    protected $fillable = [
        'series_id',
        'entry_date',
        'value',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'value'      => 'float',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function series(): BelongsTo
    {
        return $this->belongsTo(StatisticaSeries::class, 'series_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForSeries($query, int $seriesId)
    {
        return $query->where('series_id', $seriesId);
    }

    public function scopeInRange($query, string $from, string $to)
    {
        return $query->whereBetween('entry_date', [$from, $to]);
    }

    public function scopeAfter($query, string $date)
    {
        return $query->where('entry_date', '>=', $date);
    }
}