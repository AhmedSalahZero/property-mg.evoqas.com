<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCostRate extends Model
{
    protected $fillable = [
        'user_id',
        'company_id',
        'daily_rate',
        'hourly_rate',
        'currency',
    ];

    protected $casts = [
        'daily_rate'  => 'decimal:2',
        'hourly_rate' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function Company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}