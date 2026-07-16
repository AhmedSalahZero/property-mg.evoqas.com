<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    use HasFactory;

    // ── Fillable ─────────────────────────────────────────────────
    protected $fillable = [
        'company_id',
        'user_id',
        'type',
        'message',
        'is_read',
    ];

    // ── Casts ────────────────────────────────────────────────────
    protected $casts = [
        'is_read' => 'boolean',
    ];

    // ── Alert Type Labels ────────────────────────────────────────
    public const TYPE_LABELS = [
        'missed_revenue'  => 'Missed Revenue',
        'late_report'     => 'Late Report',
        'low_margin'      => 'Low Margin',
        'kpi_threshold'   => 'KPI Threshold Breached',
        'contract_expiry' => 'Contract Expiring',
        'task_overdue'    => 'Task Overdue',
        'other'           => 'General Alert',
    ];

    // ── Relationships ────────────────────────────────────────────

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ── Helpers ──────────────────────────────────────────────────

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? 'Alert';
    }

    public function markAsRead(): void
    {
        $this->update(['is_read' => true]);
    }

    // ── Scopes ───────────────────────────────────────────────────

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}