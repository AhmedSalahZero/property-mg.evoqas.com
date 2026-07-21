<?php

namespace App\Models;

use Carbon\Carbon;
use App\Services\CurrencyConversionService;
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
        'has_management_fees',
        'management_fee_expense_rate',
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
        'insurance_currency',
        'annual_increase_rate',
        'annual_increase_schedule',
        'renewed_from_contract_id',
        'status',
        'terminated_date',
        'termination_notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date'         => 'date',
        'end_date'           => 'date',
        'terminated_date'    => 'date',
        'monthly_rent_amount'        => 'decimal:2',
        'variable_revenue_pct'       => 'decimal:2',
        'min_monthly_rent'           => 'decimal:2',
        'management_fee_rate'        => 'decimal:2',
        'has_management_fees'        => 'boolean',
        'management_fee_expense_rate'=> 'decimal:2',
        'insurance_amount'           => 'decimal:2',
        'annual_increase_rate'       => 'decimal:2',
        'annual_increase_schedule'   => 'array',
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

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
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
     * Which "contract year" a given date falls into, using the exact same
     * year-boundary rule rentBasisForDate() uses to decide when an increase
     * applies (start_date + 1 year + 1 day, then +1 year per subsequent
     * boundary) — fix for audit M1. Previously, rent_revenues.year_number
     * was computed separately as a plain calendar-month count
     * (floor(diffInMonths / 12) + 1), which could disagree with the actual
     * increase boundary by one row whenever start_date wasn't the 1st of a
     * month — labeling a row "Year 2" while it was still charging the
     * Year‑1 rent, or vice versa. Both now derive from this single method,
     * so they can no longer drift apart.
     */
    private function contractYearNumber(Carbon $date): int
    {
        $yearNumber = 1;
        $boundary = $this->start_date->copy()->addYear()->addDay();
        while ($date->gte($boundary)) {
            $yearNumber++;
            $boundary->addYear();
        }
        return $yearNumber;
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
        $schedule = collect($this->annual_increase_schedule ?? [])
            ->filter(fn ($row) => isset($row['year']) && isset($row['rate']))
            ->map(fn ($row) => [
                'year' => (int) $row['year'],
                'rate' => (float) $row['rate'],
            ])
            ->sortBy('year')
            ->values();

        if ($schedule->isEmpty()) {
            $rate = (float) $this->annual_increase_rate / 100;
            if ($rate <= 0) {
                return $basis;
            }

            $increaseStart = $this->start_date->copy()->addYear()->addDay();
            if ($date->lt($increaseStart)) {
                return $basis;
            }

            $yearsApplied = 0;
            $boundary = $increaseStart->copy();
            while ($date->gte($boundary)) {
                $yearsApplied++;
                $boundary->addYear();
            }

            return round($basis * pow(1 + $rate, $yearsApplied), 2);
        }

        $current = $basis;
        $boundary = $this->start_date->copy()->addYear()->addDay();
        foreach ($schedule as $row) {
            if ($date->lt($boundary)) {
                break;
            }

            $current = round($current * (1 + ($row['rate'] / 100)), 2);
            $boundary->addYear();
        }

        return $current;
    }

    /**
     * Generate rent_revenues and reconcile rent_collections for this contract.
     *
     * rent_revenues carries no status/history (it's a pure accounting schedule),
     * so it is always safe to delete and fully regenerate.
     *
     * rent_collections DOES carry history (status, collected_date, notes) once a
     * tenant has actually paid, or the row has aged into 'overdue'. This method
     * therefore never blindly deletes collections — see reconcileCollections()
     * below, which is the fix for audit finding C1 (editing a contract used to
     * wipe every collection's status back to 'pending', destroying the record
     * of what had already been collected).
     */
    /**
     * Fix for audit finding F-2 — this used to run generateRevenues() and
     * reconcileCollections() with no transaction wrapping either of them.
     * Each of those methods is itself a delete-then-bulk-insert (or a
     * get/update/insert/delete reconciliation loop), so a failure partway
     * through — a bad date, a DB timeout, a lock wait — could leave a
     * contract saved with a partial or missing revenue/collection schedule:
     * a contract that "exists" but silently has wrong or no financial rows
     * behind it. Wrapping the whole operation in one transaction means it
     * either fully succeeds or fully rolls back — the contract row itself
     * is created inside the same transaction by the caller (see
     * RentContractController::store()/update()), so a failure here now
     * rolls back the contract too, instead of leaving an orphaned one.
     */
    public function generateSchedules(): void
    {
        \DB::transaction(function () {
            $this->generateRevenues();
            $this->reconcileCollections();
        });
    }

    /**
     * Revenues carry no payment history, so a full delete + rebuild is safe.
     */
    private function generateRevenues(): void
    {
        $this->revenues()->delete();

        $end          = $this->end_date->copy();
        $monthCursor  = $this->start_date->copy()->startOfMonth();
        $revenues     = [];
        $fx           = app(CurrencyConversionService::class);
        $baseCurrency = $this->company?->currency ?: 'EGP';

        while ($monthCursor->lte($end)) {
            // Use first day of month to represent the month; the increase
            // boundary itself is checked against this same date inside
            // rentBasisForDate()/contractYearNumber() (both use the exact
            // anniversary-based rule, start_date + N years + 1 day).
            $checkDate = $monthCursor->copy();

            $monthlyRent   = $this->rentBasisForDate($checkDate);
            $revenueAmount = $this->revenueAmount($monthlyRent);

            // Fix for audit M1 — year_number now comes from the same
            // boundary rule that determines the rent increase itself, so
            // the two can never disagree on which "contract year" a row is in.
            $yearNumber = $this->contractYearNumber($checkDate);

            // Fix for audit C4 — convert to the company's base currency at the
            // FX rate in effect for this revenue month, so a foreign-currency
            // contract never gets silently added to EGP totals at 1:1.
            $converted = $fx->convert($this->company_id, $baseCurrency, $revenueAmount, $this->contract_currency, $monthCursor);

            $revenues[] = [
                'rent_contract_id' => $this->id,
                'company_id'       => $this->company_id,
                'revenue_date'     => $monthCursor->format('Y-m-d'),
                'period_label'     => $monthCursor->format('m/Y'),
                'monthly_rent'     => $monthlyRent,
                'revenue_amount'   => $revenueAmount,
                'currency'         => $this->contract_currency,
                'base_amount'      => $converted['base_amount'],
                'base_currency'    => $converted['base_currency'],
                'fx_rate_used'     => $converted['fx_rate_used'],
                'year_number'      => $yearNumber,
                'created_at'       => now(),
                'updated_at'       => now(),
            ];

            $monthCursor->addMonth();
        }

        if (!empty($revenues)) {
            \DB::table('rent_revenues')->insert($revenues);
        }
    }

    /**
     * Reconcile rent_collections against the desired schedule instead of
     * destructively regenerating it.
     *
     * Matching key: collection_date (the natural, stable identity of a
     * generated collection row for a given contract).
     *
     *   - If a matching row already exists and is NOT still 'pending' (i.e. it
     *     has been marked 'collected', or has aged into 'overdue') — it is left
     *     completely untouched. That status is a historical fact and must
     *     survive edits to the contract, even if the contract's rent amount,
     *     interval, or dates changed afterward.
     *   - If a matching row exists and is still 'pending' — its amount/period
     *     fields are refreshed (e.g. the rent basis changed), since nothing
     *     real has happened against it yet.
     *   - If no matching row exists — a new 'pending' row is created.
     *   - Any row that is still 'pending' but no longer appears in the new
     *     schedule (e.g. the contract was shortened, or the collection
     *     interval changed) is removed. Rows that are 'collected' or
     *     'overdue' are NEVER removed this way, even if their date falls
     *     outside the new schedule — that would delete real history.
     *
     * Known limitation: if the collection interval itself changes mid-contract
     * (e.g. monthly → quarterly), the new schedule's dates mostly won't line up
     * with old ones, so old pending rows get cleared and replaced under the new
     * interval as expected — but any already-collected rows under the old
     * interval remain in the table alongside the new schedule, since they are
     * historical facts. Review the collection list after such a change to
     * confirm there's no unintended overlap for the transition period.
     */
    private function reconcileCollections(): void
    {
        $end          = $this->end_date->copy();
        $interval     = (int) $this->collection_interval_months;
        $currency     = $this->collection_currency ?: $this->contract_currency;
        $fx           = app(CurrencyConversionService::class);
        $baseCurrency = $this->company?->currency ?: 'EGP';

        // ── Build the desired schedule in memory, keyed by collection_date ──
        $desired = [];
        $collectionCursor = $this->start_date->copy();

        while ($collectionCursor->lte($end)) {
            $periodFrom = $collectionCursor->copy();
            $periodTo   = $collectionCursor->copy()->addMonths($interval)->subDay();

            if ($periodTo->gt($end)) {
                $periodTo = $end->copy();
            }

            // Sum the monthly rents covered by this collection interval
            $totalCollection = 0;
            $mc = $periodFrom->copy()->startOfMonth();
            $monthCount = 0;

            while ($mc->lte($periodTo)) {
                // NOTE: revenueAmount() is intentional here, not a bug — for a
                // management_fee contract, the company only ever collects its
                // commission (the full rent is settled directly between tenant
                // and owner), so collections must use the same fee-adjusted
                // amount as revenues. Confirmed with the business owner.
                $totalCollection += $this->revenueAmount($this->rentBasisForDate($mc));
                $mc->addMonth();
                $monthCount++;
            }

            $avgBasis = $monthCount > 0 ? round($totalCollection / $monthCount, 2) : 0;
            $amount   = round($totalCollection, 2);

            // Fix for audit C4 — convert to the company's base currency using
            // the collection_currency (not contract_currency — see audit C6)
            // and the FX rate in effect on this collection's due date.
            $converted = $fx->convert($this->company_id, $baseCurrency, $amount, $currency, $periodFrom);

            $desired[$periodFrom->format('Y-m-d')] = [
                'period_from'        => $periodFrom->format('Y-m-d'),
                'period_to'          => $periodTo->format('Y-m-d'),
                'monthly_rent_basis' => $avgBasis,
                'collection_amount'  => $amount,
                'base_amount'        => $converted['base_amount'],
                'base_currency'      => $converted['base_currency'],
                'fx_rate_used'       => $converted['fx_rate_used'],
            ];

            $collectionCursor->addMonths($interval);
        }

        // ── Reconcile against existing rows ─────────────────────────────────
        $existing = $this->collections()->get()->keyBy(fn ($c) => $c->collection_date->format('Y-m-d'));

        $keepIds  = [];
        $toInsert = [];

        foreach ($desired as $dateKey => $row) {
            $match = $existing->get($dateKey);

            if ($match && $match->status !== RentCollection::STATUS_PENDING) {
                // Already collected, or aged into overdue — historical fact, don't touch.
                $keepIds[] = $match->id;
                continue;
            }

            if ($match) {
                // Still pending — safe to refresh the computed amounts.
                $match->update([
                    'period_from'        => $row['period_from'],
                    'period_to'          => $row['period_to'],
                    'monthly_rent_basis' => $row['monthly_rent_basis'],
                    'collection_amount'  => $row['collection_amount'],
                    'currency'           => $currency,
                    'base_amount'        => $row['base_amount'],
                    'base_currency'      => $row['base_currency'],
                    'fx_rate_used'       => $row['fx_rate_used'],
                ]);
                $keepIds[] = $match->id;
                continue;
            }

            $toInsert[] = [
                'rent_contract_id'   => $this->id,
                'company_id'         => $this->company_id,
                'collection_date'    => $dateKey,
                'period_from'        => $row['period_from'],
                'period_to'          => $row['period_to'],
                'monthly_rent_basis' => $row['monthly_rent_basis'],
                'collection_amount'  => $row['collection_amount'],
                'currency'           => $currency,
                'base_amount'        => $row['base_amount'],
                'base_currency'      => $row['base_currency'],
                'fx_rate_used'       => $row['fx_rate_used'],
                'status'             => RentCollection::STATUS_PENDING,
                'created_at'         => now(),
                'updated_at'         => now(),
            ];
        }

        if (!empty($toInsert)) {
            \DB::table('rent_collections')->insert($toInsert);
        }

        // Remove obsolete rows — but ONLY ones still pending. Anything collected
        // or overdue is history and must survive even if it fell outside the
        // newly generated schedule (see "Known limitation" above).
        $this->collections()
            ->whereNotIn('id', $keepIds)
            ->where('status', RentCollection::STATUS_PENDING)
            ->delete();
    }

    /**
     * Auto-expire contracts whose end_date has passed.
     * Called daily by the App\Console\Commands\MarkOverdueRecords scheduled command.
     */
    public static function autoExpire(): int
    {
        return static::where('status', self::STATUS_RUNNING)
            ->where('end_date', '<', now()->toDateString())
            ->update(['status' => self::STATUS_EXPIRED]);
    }
}