<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentContract extends Model
{
    protected $fillable = [
        'company_id',
        'property_id',
        'property_unit_id',
        'revenue_type',
        'management_fee_rate',
        'tenant_nature',
        'customer_id',
        'start_date',
        'end_date',
        'contract_currency',
        'monthly_rent_amount',
        'variable_revenue_pct',
        'min_monthly_rent',
        'collection_currency',
        'collection_interval_months',
        'insurance_months',
        'insurance_amount',
        'annual_increase_rate',
        'renewed_from_contract_id',
        'status',
        'terminated_date',
        'termination_notes',
        'created_by',
    ];

    protected $casts = [
        'start_date'         => 'date',
        'end_date'           => 'date',
        'terminated_date'    => 'date',
        'monthly_rent_amount'        => 'decimal:2',
        'variable_revenue_pct'       => 'decimal:2',
        'min_monthly_rent'           => 'decimal:2',
        'management_fee_rate'        => 'decimal:2',
        'insurance_amount'           => 'decimal:2',
        'annual_increase_rate'       => 'decimal:2',
        'collection_interval_months' => 'integer',
        'insurance_months'           => 'integer',
    ];

    // ── Constants ────────────────────────────────────────────────────────────

    const REVENUE_DIRECT_RENT     = 'direct_rent';
    const REVENUE_MANAGEMENT_FEE  = 'management_fee';

    const STATUS_RUNNING    = 'running';
    const STATUS_EXPIRED    = 'expired';
    const STATUS_TERMINATED = 'terminated';

    const COLLECTION_INTERVALS = [1, 2, 3, 4, 6, 12];

    // ── Relationships ────────────────────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function propertyUnit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function renewedFrom(): BelongsTo
    {
        return $this->belongsTo(RentContract::class, 'renewed_from_contract_id');
    }

    public function revenues(): HasMany
    {
        return $this->hasMany(RentRevenue::class)->orderBy('revenue_date');
    }

    public function collections(): HasMany
    {
        return $this->hasMany(RentCollection::class)->orderBy('collection_date');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeRunning($query)
    {
        return $query->where('status', self::STATUS_RUNNING);
    }

    public function scopeExpired($query)
    {
        return $query->where('status', self::STATUS_EXPIRED);
    }

    public function scopeTerminated($query)
    {
        return $query->where('status', self::STATUS_TERMINATED);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * The rent basis amount used for all calculations.
     * If min_monthly_rent is set, it overrides monthly_rent_amount.
     */
    public function rentBasis(): float
    {
        if (!empty($this->min_monthly_rent) && $this->min_monthly_rent > 0) {
            return (float) $this->min_monthly_rent;
        }
        return (float) $this->monthly_rent_amount;
    }

    /**
     * Given a month's base rent, return the revenue amount based on revenue type.
     */
    public function revenueAmount(float $monthlyRent): float
    {
        if ($this->revenue_type === self::REVENUE_MANAGEMENT_FEE && $this->management_fee_rate > 0) {
            return round($monthlyRent * ($this->management_fee_rate / 100), 2);
        }
        return $monthlyRent;
    }

    /**
     * Calculate the rent basis for a given month date,
     * applying annual increase compounded from start_date.
     * Increase applies from the day AFTER the anniversary of start_date.
     * e.g. start = 15/02/2026 → increase applies from 16/02/2027
     */
    public function rentBasisForDate(Carbon $date): float
    {
        $basis = $this->rentBasis();
        $rate  = (float) $this->annual_increase_rate / 100;

        if ($rate <= 0) {
            return $basis;
        }

        // Anniversary is start_date + 1 year; increase applies from that day onward
        // First increase: start_date + 1 year (not +1 day — the increase applies AT the anniversary)
        // Per agreement: 15/02/2026 → increase from 16/02/2027
        $increaseStart = $this->start_date->copy()->addYear()->addDay();

        if ($date->lt($increaseStart)) {
            return $basis;
        }

        // How many full years of increase have been applied
        // Each subsequent year: increaseStart + (n-1) years
        $yearsApplied = 0;
        $boundary = $increaseStart->copy();
        while ($date->gte($boundary)) {
            $yearsApplied++;
            $boundary->addYear();
        }

        return round($basis * pow(1 + $rate, $yearsApplied), 2);
    }

    /**
     * Generate and save rent_revenues (one per month) and rent_collections.
     * Deletes existing rows for this contract first.
     */
    public function generateSchedules(): void
    {
        // Clear existing
        $this->revenues()->delete();
        $this->collections()->delete();

        $current   = $this->start_date->copy()->startOfMonth();
        // If start_date is not the 1st, first month is still the start month
        $current   = $this->start_date->copy();
        $end       = $this->end_date->copy();
        $interval  = (int) $this->collection_interval_months;

        // ── Build monthly revenues ────────────────────────────────────────────
        $monthCursor = $this->start_date->copy()->startOfMonth();
        // Always start from the actual start_date's month
        $revenues = [];

        while ($monthCursor->lte($end)) {
            // Use first day of month to represent the month, but for increase
            // calculation use the actual date (15th stays 15th for increase boundary)
            $checkDate = $monthCursor->copy();

            $monthlyRent   = $this->rentBasisForDate($checkDate);
            $revenueAmount = $this->revenueAmount($monthlyRent);

            // Year number: which contract year is this month in (1-based)
            $yearNumber = (int) floor($this->start_date->diffInMonths($monthCursor) / 12) + 1;

            $revenues[] = [
                'rent_contract_id' => $this->id,
                'company_id'       => $this->company_id,
                'revenue_date'     => $monthCursor->format('Y-m-d'),
                'period_label'     => $monthCursor->format('m/Y'),
                'monthly_rent'     => $monthlyRent,
                'revenue_amount'   => $revenueAmount,
                'currency'         => $this->contract_currency,
                'year_number'      => $yearNumber,
                'created_at'       => now(),
                'updated_at'       => now(),
            ];

            $monthCursor->addMonth();
        }

        \DB::table('rent_revenues')->insert($revenues);

        // ── Build collections ─────────────────────────────────────────────────
        $collectionCursor = $this->start_date->copy();
        $collections      = [];

        while ($collectionCursor->lte($end)) {
            $periodFrom = $collectionCursor->copy();
            $periodTo   = $collectionCursor->copy()->addMonths($interval)->subDay();

            // Clamp period_to to contract end
            if ($periodTo->gt($end)) {
                $periodTo = $end->copy();
            }

            // Sum the monthly rents covered by this collection interval
            // Iterate month by month within the interval
            $totalCollection = 0;
            $mc = $periodFrom->copy()->startOfMonth();
            $monthCount = 0;

            while ($mc->lte($periodTo)) {
                $totalCollection += $this->revenueAmount($this->rentBasisForDate($mc));
                $mc->addMonth();
                $monthCount++;
            }

            // Avg monthly basis for reference
            $avgBasis = $monthCount > 0 ? round($totalCollection / $monthCount, 2) : 0;

            $collections[] = [
                'rent_contract_id'    => $this->id,
                'company_id'          => $this->company_id,
                'collection_date'     => $periodFrom->format('Y-m-d'),
                'period_from'         => $periodFrom->format('Y-m-d'),
                'period_to'           => $periodTo->format('Y-m-d'),
                'monthly_rent_basis'  => $avgBasis,
                'collection_amount'   => round($totalCollection, 2),
                'currency'            => $this->contract_currency,
                'status'              => 'pending',
                'created_at'          => now(),
                'updated_at'          => now(),
            ];

            $collectionCursor->addMonths($interval);
        }

        \DB::table('rent_collections')->insert($collections);
    }

    /**
     * Auto-expire contracts whose end_date has passed.
     * Call from a scheduled command.
     */
    public static function autoExpire(): int
    {
        return static::where('status', self::STATUS_RUNNING)
            ->where('end_date', '<', now()->toDateString())
            ->update(['status' => self::STATUS_EXPIRED]);
    }
}