<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PropertyInstallmentPlan extends Model
{
    protected $fillable = [
        'company_id',
        'property_id',
        'installment_type',
        'currency',
        'delivery_date',
        'ready_to_use_date',
        'signing_amount',
        'signing_date',
        'reservation_amount',
        'reservation_date',
        'installment_rows',
        'has_annual',
        'annual_start_date',
        'annual_amount',
        'annual_count',
        'has_delivery',
        'delivery_start_date',
        'delivery_amount',
        'delivery_count',
        'delivery_interval',
        'has_maintenance',
        'maintenance_start_date',
        'maintenance_amount',
        'maintenance_count',
        'maintenance_interval',
        'created_by',
    ];

    protected $casts = [
        'installment_rows'    => 'array',
        'has_annual'          => 'boolean',
        'has_delivery'        => 'boolean',
        'has_maintenance'     => 'boolean',
        'signing_amount'      => 'decimal:2',
        'reservation_amount'  => 'decimal:2',
        'annual_amount'       => 'decimal:2',
        'delivery_amount'     => 'decimal:2',
        'maintenance_amount'  => 'decimal:2',
        'annual_count'        => 'integer',
        'delivery_count'      => 'integer',
        'maintenance_count'   => 'integer',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function dues(): HasMany
    {
        return $this->hasMany(PropertyInstallmentDue::class, 'plan_id')->orderBy('due_date')->orderBy('sort_order');
    }

    // ── Schedule Generation ──────────────────────────────────────────────────

    /**
     * Delete all existing dues for this plan and regenerate from plan data.
     * For variable type, dues are saved directly by the controller — skip generation.
     */
    public function generateDues(): void
    {
        $this->dues()->delete();

        if ($this->installment_type === 'variable') {
            return; // variable dues come from controller directly
        }

        $currency = $this->currency ?? 'EGP';
        $rows     = [];
        $sort     = 0;

        // ── Signing ──────────────────────────────────────────────────────────
        if (!empty($this->signing_amount) && (float)$this->signing_amount > 0 && !empty($this->signing_date)) {
            $rows[] = $this->buildRow('signing', $this->signing_date, (float)$this->signing_amount, $currency, $sort++);
        }

        // ── Reservation ──────────────────────────────────────────────────────
        if (!empty($this->reservation_amount) && (float)$this->reservation_amount > 0 && !empty($this->reservation_date)) {
            $rows[] = $this->buildRow('reservation', $this->reservation_date, (float)$this->reservation_amount, $currency, $sort++);
        }

        // ── Installment rows (repeater) ───────────────────────────────────────
        foreach ($this->installment_rows ?? [] as $row) {
            $amount = (float)($row['amount'] ?? 0);
            $count  = (int)($row['count'] ?? 0);
            if ($amount <= 0 || $count <= 0 || empty($row['start_date'])) continue;

            $months  = $this->intervalMonths($row['interval'] ?? 'monthly');
            $current = $this->parseMonthYear($row['start_date']);

            for ($i = 0; $i < $count; $i++) {
                $rows[] = $this->buildRow('installment', $current->format('m/Y'), $amount, $currency, $sort++);
                $current->addMonths($months);
            }
        }

        // ── Annual ────────────────────────────────────────────────────────────
        if ($this->has_annual
            && !empty($this->annual_amount) && (float)$this->annual_amount > 0
            && !empty($this->annual_start_date)
            && (int)$this->annual_count > 0
        ) {
            $current = $this->parseMonthYear($this->annual_start_date);
            for ($i = 0; $i < (int)$this->annual_count; $i++) {
                $rows[] = $this->buildRow('annual', $current->format('m/Y'), (float)$this->annual_amount, $currency, $sort++);
                $current->addYear();
            }
        }

        // ── Delivery ──────────────────────────────────────────────────────────
        if ($this->has_delivery
            && !empty($this->delivery_amount) && (float)$this->delivery_amount > 0
            && !empty($this->delivery_start_date)
            && (int)$this->delivery_count > 0
        ) {
            $months  = $this->intervalMonths($this->delivery_interval ?? 'monthly');
            $current = $this->parseMonthYear($this->delivery_start_date);
            for ($i = 0; $i < (int)$this->delivery_count; $i++) {
                $rows[] = $this->buildRow('delivery', $current->format('m/Y'), (float)$this->delivery_amount, $currency, $sort++);
                $current->addMonths($months);
            }
        }

        // ── Maintenance ───────────────────────────────────────────────────────
        if ($this->has_maintenance
            && !empty($this->maintenance_amount) && (float)$this->maintenance_amount > 0
            && !empty($this->maintenance_start_date)
            && (int)$this->maintenance_count > 0
        ) {
            $months  = $this->intervalMonths($this->maintenance_interval ?? 'monthly');
            $current = $this->parseMonthYear($this->maintenance_start_date);
            for ($i = 0; $i < (int)$this->maintenance_count; $i++) {
                $rows[] = $this->buildRow('maintenance', $current->format('m/Y'), (float)$this->maintenance_amount, $currency, $sort++);
                $current->addMonths($months);
            }
        }

        // Bulk insert
        if (!empty($rows)) {
            PropertyInstallmentDue::insert($rows);
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function buildRow(string $type, string $monthYear, float $amount, string $currency, int $sort): array
    {
        $date = $this->parseMonthYear($monthYear);
        return [
            'company_id'  => $this->company_id,
            'property_id' => $this->property_id,
            'plan_id'     => $this->id,
            'due_type'    => $type,
            'due_date'    => $date->startOfMonth()->toDateString(),
            'amount'      => $amount,
            'currency'    => $currency,
            'status'      => 'pending',
            'paid_date'   => null,
            'notes'       => null,
            'sort_order'  => $sort,
            'created_at'  => now(),
            'updated_at'  => now(),
        ];
    }

    private function parseMonthYear(string $value): Carbon
    {
        // Accepts MM/YYYY or YYYY-MM
        if (str_contains($value, '/')) {
            [$m, $y] = explode('/', $value);
            return Carbon::createFromDate((int)$y, (int)$m, 1);
        }
        return Carbon::parse($value . '-01');
    }

    private function intervalMonths(string $interval): int
    {
        return match ($interval) {
            'quarterly'     => 3,
            'semi_annually' => 6,
            default         => 1, // monthly
        };
    }
}