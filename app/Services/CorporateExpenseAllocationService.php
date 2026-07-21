<?php

namespace App\Services;

use App\Models\CorporateExpense;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\RentContract;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Corporate Expense Allocation Engine.
 *
 * Spreads a company-level expense across portfolio units using the
 * methodology agreed with the business owner (July 2026 session):
 *
 *   allocation = (unit area / total eligible area) × expense amount
 *
 * Everything is evaluated ACCRUAL-BASIS, "as of" the expense's own date —
 * never "today," never the payment date. This is the whole point of the
 * engine: a unit that was vacant in January and got a tenant in February
 * must be excluded from a January "Occupied Units" allocation and included
 * automatically from February onward, purely because its contract's date
 * range changed relative to each expense's own date — nothing about the
 * expense itself needs to be re-entered or manually adjusted.
 *
 * Occupancy "as of a date" is reconstructed from contract date ranges
 * (start_date / end_date / terminated_date), NOT from the contract's
 * current `status` column — a contract that has since expired or been
 * terminated still correctly counts as occupied for an expense dated while
 * it was actually running, and a contract terminated BEFORE the expense
 * date correctly does not count even if its original end_date would have
 * covered it.
 */
class CorporateExpenseAllocationService
{
    /**
     * Fix for the "100 units × 20 expenses = 2,000 contract queries per
     * import" scaling problem flagged in the July 2026 session — both the
     * property/unit list AND the full contract list for the company are
     * loaded ONCE per HTTP request (this service is resolved fresh per
     * request via the controller's constructor injection, so this cache
     * never leaks across requests) and reused for every expense processed
     * in that request, however many there are. Only the per-date occupancy/
     * delivery FILTERING below still runs once per unit per expense — but
     * that's plain in-memory Collection filtering against data already in
     * PHP, not a database round trip, so it stays cheap even at
     * 100 units × 50 expenses in a single Excel import.
     */
    private ?Collection $propertiesCache = null;
    private ?Collection $contractsByKeyCache = null;
    private ?int $cachedCompanyId = null;

    /**
     * Four allocation scopes, chosen fresh per expense (see CorporateExpense
     * scope constants):
     *   - occupied                   → occupied as of expense_date
     *   - all_include_not_delivered  → every unit, no exceptions
     *   - all_exclude_not_delivered  → occupied + vacant, not-delivered units excluded
     *   - custom                     → exactly the unit keys passed in $customKeys
     *
     * @param  string[]  $customKeys  Only used when $scope = 'custom'. Each
     *         key is "propertyId-unitId" (unitId = '0' for a standalone unit
     *         property), matching the 'key' field on each returned slot.
     */
    public function eligibleUnits(int $companyId, string $scope, Carbon $asOfDate, array $customKeys = []): Collection
    {
        $allSlots = $this->allPortfolioSlots($companyId, $asOfDate);

        return $allSlots->filter(function ($slot) use ($scope, $customKeys) {
            return match ($scope) {
                CorporateExpense::SCOPE_OCCUPIED                  => $slot['status'] === 'occupied',
                CorporateExpense::SCOPE_ALL_INCLUDE_NOT_DELIVERED => true,
                CorporateExpense::SCOPE_ALL_EXCLUDE_NOT_DELIVERED => $slot['status'] !== 'not_delivered',
                CorporateExpense::SCOPE_CUSTOM                    => in_array($slot['key'], $customKeys, true),
                default                                            => false,
            };
        })->values();
    }

    /**
     * Build the full list of leasable slots in the portfolio (standalone
     * units + every child unit of a Building/Land/Complex), each carrying
     * its area and its occupancy/delivery STATUS as of $asOfDate.
     *
     * Same "leasable slot" shape as PropertyDashboardController::buildPortfolio(),
     * but parameterized by date instead of always using "today," and backed
     * by the batch-loaded caches above instead of a query per unit.
     */
    public function allPortfolioSlots(int $companyId, Carbon $asOfDate): Collection
    {
        $this->loadPortfolioOnce($companyId);

        $slots = collect();

        foreach ($this->propertiesCache as $p) {
            if ($p->nature === Property::NATURE_UNIT) {
                $slots->push($this->buildSlot($p, null, $asOfDate));
            } else {
                foreach ($p->units as $u) {
                    $slots->push($this->buildSlot($p, $u, $asOfDate));
                }
            }
        }

        return $slots;
    }

    /**
     * Load every property (+ units + installment plan) and every contract
     * for this company ONCE and keep them on the instance. Both lists are
     * date-independent — occupancy/delivery status is derived from them
     * fresh per $asOfDate in buildSlot()/contractAsOf() below without
     * re-querying the database. Cheap to call repeatedly: a no-op after the
     * first call for a given $companyId.
     */
    private function loadPortfolioOnce(int $companyId): void
    {
        if ($this->cachedCompanyId === $companyId && $this->propertiesCache !== null) {
            return;
        }

        $this->propertiesCache = Property::where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->with([
                'units' => fn ($q) => $q->whereNull('deleted_at'),
                'installmentPlan:id,property_id,delivery_date',
            ])
            ->get();

        // ONE query for every contract the company has ever had, grouped by
        // "propertyId-unitId" slot key — replaces what used to be one
        // RentContract query per unit per expense.
        $this->contractsByKeyCache = RentContract::where('company_id', $companyId)
            ->select(['id', 'property_id', 'property_unit_id', 'start_date', 'end_date', 'terminated_date'])
            ->get()
            ->groupBy(fn ($c) => $c->property_id . '-' . ($c->property_unit_id ?? '0'));

        $this->cachedCompanyId = $companyId;
    }

    private function buildSlot(Property $p, ?PropertyUnit $u, Carbon $asOfDate): array
    {
        $unitId    = $u?->id;
        $ownership = $u?->ownership ?? $p->ownership;
        $key       = $p->id . '-' . ($unitId ?? '0');
        $candidates = $this->contractsByKeyCache->get($key) ?? collect();
        $contract  = $this->contractAsOf($candidates, $asOfDate);
        $status    = $this->slotStatusAsOf($ownership, $p->installmentPlan?->delivery_date, $contract, $asOfDate);

        return [
            'key'         => $key,
            'property_id' => $p->id,
            'unit_id'     => $unitId,
            'label'       => $u ? ($p->property_name . ' — ' . $u->unit_name) : $p->property_name,
            'area'        => (float) ($u ? $u->area : $p->area) ?: 0.0,
            'ownership'   => $ownership,
            'status'      => $status, // occupied | vacant | not_delivered
        ];
    }

    /**
     * Was this unit occupied on $asOfDate? Reconstructed from contract date
     * ranges, not the contract's live `status` column — confirmed business
     * rule (see July 2026 session): a contract that has since expired or
     * been terminated AFTER $asOfDate still counts; one terminated ON OR
     * BEFORE $asOfDate does not, even if its original end_date would have
     * still covered $asOfDate.
     *
     * Operates entirely in-memory against $candidates (this slot's own
     * contracts, already pre-grouped in loadPortfolioOnce()) — no query.
     */
    private function contractAsOf(Collection $candidates, Carbon $asOfDate): ?RentContract
    {
        $date = $asOfDate->toDateString();

        $matching = $candidates->filter(function (RentContract $c) use ($date) {
            $covers = $c->start_date->toDateString() <= $date && $c->end_date->toDateString() >= $date;
            $notTerminatedBefore = is_null($c->terminated_date) || $c->terminated_date->toDateString() > $date;
            return $covers && $notTerminatedBefore;
        });

        if ($matching->isEmpty()) {
            return null;
        }

        // Deterministic pick if more than one contract somehow matches —
        // most recently started wins, id as tiebreaker (same rule used for
        // Keep-or-Sell's M3 fix). A manual comparator instead of chained
        // sortByDesc() calls, since chaining re-sorts from scratch each time.
        return $matching->sort(function (RentContract $a, RentContract $b) {
            $cmp = strcmp($b->start_date->toDateString(), $a->start_date->toDateString());
            return $cmp !== 0 ? $cmp : ($b->id <=> $a->id);
        })->first();
    }

    /**
     * Same three-way classification as PropertyDashboardController::slotStatus()
     * (occupied / not_delivered / vacant), but evaluated against an arbitrary
     * $asOfDate rather than always "today" — this is what lets a not-delivered
     * unit automatically become eligible starting the month its delivery_date
     * has passed, for whichever expense date is being evaluated.
     */
    private function slotStatusAsOf(string $ownership, $deliveryDateStr, ?RentContract $contract, Carbon $asOfDate): string
    {
        if ($contract) {
            return 'occupied';
        }

        if ($ownership === Property::OWNERSHIP_INSTALLMENTS && $deliveryDateStr) {
            try {
                $parsed = Carbon::createFromFormat('m/Y', $deliveryDateStr)->startOfMonth()->endOfMonth();
            } catch (\Exception $e) {
                $parsed = null;
            }

            if ($parsed && $parsed->gt($asOfDate)) {
                return 'not_delivered';
            }
        }

        return 'vacant';
    }

    /**
     * Area-weighted split of $expenseAmount across $units.
     *
     * - Proportional to each unit's area / total eligible area.
     * - Falls back to an EQUAL split across all eligible units if the total
     *   eligible area is zero, or if any eligible unit is missing area data
     *   (a partial area data set can't be trusted for a proportional split).
     * - The LAST unit absorbs the rounding remainder, so the sum of
     *   allocated amounts always equals $expenseAmount exactly.
     *
     * Fix for audit Finding 6 — "last unit" used to mean whatever position
     * a unit happened to land in on $units, which depends on the allocation
     * scope (occupied/all/custom) and isn't sorted by anything stable. Two
     * runs of the "same" allocation (e.g. re-saving an expense unchanged)
     * could therefore hand the few-cent rounding remainder to a different
     * unit each time — the total was always correct, but which specific
     * unit absorbed the odd cents wasn't reproducible. Units are now always
     * sorted by (property_id, unit_id) before allocating, so "last" means
     * the same physical unit every single time for the same eligible set.
     *
     * Returns each input slot merged with 'allocation_pct' and
     * 'allocated_amount'. Returns an empty collection if $units is empty —
     * callers must validate at least one eligible unit before saving.
     */
    public function allocate(float $expenseAmount, Collection $units): Collection
    {
        if ($units->isEmpty()) {
            return collect();
        }

        // Manual comparator (same pattern as contractAsOf() above) rather
        // than Collection::sortBy()'s multi-criteria array form, to avoid
        // any ambiguity over how that form treats a closure as one of the
        // criteria — this is explicit and easy to verify by reading it.
        $units = $units->sort(function (array $a, array $b) {
            $propertyCmp = ((int) ($a['property_id'] ?? 0)) <=> ((int) ($b['property_id'] ?? 0));
            if ($propertyCmp !== 0) {
                return $propertyCmp;
            }
            // unit_id is null for a standalone unit property — treat that
            // as 0 so it sorts consistently against child units (same
            // convention already used for the slot 'key', "propertyId-0").
            return ((int) ($a['unit_id'] ?? 0)) <=> ((int) ($b['unit_id'] ?? 0));
        })->values();

        $totalArea     = $units->sum('area');
        $missingArea   = $units->contains(fn ($u) => (float) ($u['area'] ?? 0) <= 0);
        $useEqualSplit = $totalArea <= 0 || $missingArea;
        $count         = $units->count();

        $rows         = [];
        $runningTotal = 0.0;
        $idx          = 0;

        foreach ($units as $u) {
            $idx++;
            $isLast = $idx === $count;

            if ($isLast) {
                // Absorb rounding remainder so the total always reconciles exactly.
                $amount = round($expenseAmount - $runningTotal, 2);
            } else {
                $share  = $useEqualSplit ? (1 / $count) : ((float) $u['area'] / $totalArea);
                $amount = round($expenseAmount * $share, 2);
            }

            $runningTotal += $amount;

            $rows[] = array_merge($u, [
                'allocation_pct'   => $expenseAmount > 0 ? round($amount / $expenseAmount * 100, 4) : 0.0,
                'allocated_amount' => $amount,
            ]);
        }

        return collect($rows);
    }
}
