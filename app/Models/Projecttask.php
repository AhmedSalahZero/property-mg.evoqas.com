<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectTask extends Model
{
    protected $fillable = [
        'project_id',
        'created_by',
        'depends_on_task_id',
        'name',
        'description',
        'status',
        'priority',
        'order',
        'estimated_days',
        'start_date',
        'due_date',
        'progress_pct',
    ];

    protected $casts = [
        'start_date' => 'date',
        'due_date'   => 'date',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function dependsOn(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'depends_on_task_id');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_task_assignees')
                    ->withTimestamps();
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ProjectTaskLog::class)->orderByDesc('log_date');
    }

    public function getTotalHoursAttribute(): float
    {
        return (float) $this->logs()->sum('hours');
    }
}