<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\RentContract;
use App\Models\RentRevenue;
use App\Models\RentCollection;
use App\Models\PropertyInstallmentDue;
use App\Models\PropertyExpense;
use App\Models\CorporateExpense;
use App\Services\CurrencyConversionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesCompany;

class PropertyDashboardController extends Controller
{
    use AuthorizesCompany;

    // ═══════════════════════════════════════════════════════════════════
    // DASHBOARD PAGE
    // ═══════════════════════════════════════════════════════════════════
    public function index(Company $company)
    {
        $this->authorizeCompany($company);

        return Inertia::render('Properties/Dashboard', [
            'company' => $company,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // MAIN DATA ENDPOINT — all tabs in one call
    // ═══════════════════════════════════════════════════════════════════
    public function data(Request $request, Company $company)
    {
        $this->authorizeCompany($company);

        $request->validate([
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
            'currency'  => 'nullable|string|max:10',
        ]);

        $dateFrom = $request->date_from ?? Carbon::now()->startOfYear()->toDateString();
        $dateTo   = $request->date_to   ?? Carbon::now()->toDateString();
        $baseCurrency = strtoupper($company->currency ?: 'EGP');

        // A specific currency picked from the dropdown → raw, single-currency
        // view for that currency only. Otherwise → main functional currency,
        // every currency converted at today's LATEST rate and summed — see
        // CurrencyConversionService::convertGroupedAmounts().
        $requested = $request->input('currency') ? strtoupper($request->input('currency')) : null;
        $viewCurrency = ($requested && $requested !== $baseCurrency) ? $requested : null;

        $fx = app(CurrencyConversionService::class);

        return response()->json([
            'base_currency'   => $baseCurrency,
            'view_currency'   => $viewCurrency ?? $baseCurrency,
            'is_functional_view' => $viewCurrency === null,
            'available_currencies' => $fx->usedCurrencies($company->id, $baseCurrency),
            'portfolio'    => $this->buildPortfolio($company->id, $baseCurrency),
            'contracts'    => $this->buildContracts($company->id, $dateFrom, $dateTo),
            'revenues'     => $this->buildRevenues($company->id, $dateFrom, $dateTo, $baseCurrency, $viewCurrency),
            'collections'  => $this->buildCollections($company->id, $dateFrom, $dateTo, $baseCurrency, $viewCurrency),
            'installments' => $this->buildInstallments($company->id, $dateFrom, $dateTo, $baseCurrency, $viewCurrency),
            'expenses'     => $this->buildExpenses($company->id, $dateFrom, $dateTo, $baseCurrency, $viewCurrency),
            'profitability'=> $this->buildProfitability($company->id, $dateFrom, $dateTo, $baseCurrency, $viewCurrency),
            'insights'     => $this->buildInsights($company->id, $dateFrom, $dateTo, $baseCurrency),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // TAB 1 — PORTFOLIO OVERVIEW
    // ═══════════════════════════════════════════════════════════════════
    private function buildPortfolio(int $companyId, string $baseCurrency): array
    {
        // ── All properties ────────────────────────────────────────────
        $properties = Property::where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->with([
                'units' => fn($q) => $q->whereNull('deleted_at'),
                'marketValues' => fn($q) => $q->orderByDesc('value_date'),
                'units.marketValues' => fn($q) => $q->orderByDesc('value_date'),
                // Fix for audit H1 — delivery_date lives on the installment
                // plan (one per PROPERTY, never per child unit — see
                // property_installment_plans' unique('property_id')), not on
                // properties/property_units directly. Without this eager
                // load, $p->delivery_date is always null and every
                // not-yet-delivered installment property silently shows as
                // plain "vacant" instead of "not_delivered".
                'installmentPlan:id,property_id,delivery_date',
            ])
            ->get();

        // ── Running contracts to determine occupancy ──────────────────
        $runningContracts = RentContract::where('company_id', $companyId)
            ->where('status', 'running')
            ->with('customer:id,customer_name,tenant_nature')
            ->get();

        $occupiedPropertyIds = $runningContracts->pluck('property_id')->unique();
        $occupiedUnitIds     = $runningContracts->pluck('property_unit_id')->filter()->unique();

        // ── Counts by nature ──────────────────────────────────────────
        $byNature = $properties->groupBy('nature')->map->count();

        // ── Financial totals ──────────────────────────────────────────
        // Fix for audit H2 — this now goes through the same shared
        // perPropertyFinancials() helper the Profitability tab uses, so the
        // two tabs can no longer silently disagree on unrealized gain.
        $portfolioUnconvertedCurrencies = [];
        $financials = $this->perPropertyFinancials($companyId, $properties, $baseCurrency, $portfolioUnconvertedCurrencies);
        $totalAcquisitionCost = round($financials->sum('acquisition_cost'), 2);
        $totalBookValue       = round($financials->sum('book_value'), 2);
        $totalMarketValue     = round($financials->sum('market_value'), 2);

        // ── Occupancy breakdown ───────────────────────────────────────
        // A "leasable slot" = standalone unit OR a child unit inside building/complex/land
        $leasableSlots = collect();

        foreach ($properties as $p) {
            if ($p->nature === 'unit') {
                $contract = $runningContracts->where('property_id', $p->id)->where('property_unit_id', null)->first();
                $leasableSlots->push([
                    'property_id'   => $p->id,
                    'unit_id'       => null,
                    'name'          => $p->property_name,
                    'code'          => $p->property_code,
                    'nature'        => $p->nature,
                    'ownership'     => $p->ownership,
                    'governorate'   => $p->governorate,
                    'area'          => $p->area,
                    'status'        => $this->slotStatus($p->ownership, $p->installmentPlan?->delivery_date, $contract),
                    'tenant'        => $contract?->customer?->customer_name,
                    'contract_end'  => $contract?->end_date,
                ]);
            } else {
                foreach ($p->units as $u) {
                    $contract = $runningContracts->where('property_unit_id', $u->id)->first();
                    $leasableSlots->push([
                        'property_id'   => $p->id,
                        'unit_id'       => $u->id,
                        'name'          => $p->property_name . ' — ' . $u->unit_name,
                        'code'          => $u->unit_code,
                        'nature'        => $p->nature,
                        'ownership'     => $u->ownership ?? $p->ownership,
                        'governorate'   => $p->governorate,
                        'area'          => $u->area,
                        // installmentPlan is one-per-PROPERTY (never per
                        // child unit — see the migration's unique('property_id')),
                        // so every unit under this Building/Land/Complex
                        // shares the parent's single delivery_date.
                        'status'        => $this->slotStatus($u->ownership ?? $p->ownership, $p->installmentPlan?->delivery_date, $contract),
                        'tenant'        => $contract?->customer?->customer_name,
                        'contract_end'  => $contract?->end_date,
                    ]);
                }
            }
        }

        $statusCounts = $leasableSlots->groupBy('status')->map->count();

        // ── Area totals ───────────────────────────────────────────────
        $totalArea     = $leasableSlots->sum('area');
        $occupiedArea  = $leasableSlots->where('status', 'occupied')->sum('area');

        return [
            'total_properties'     => $properties->count(),
            'by_nature'            => $byNature,
            'total_leasable'       => $leasableSlots->count(),
            'status_counts'        => $statusCounts,
            'total_area'           => round($totalArea, 2),
            'occupied_area'        => round($occupiedArea, 2),
            'occupancy_rate'       => $leasableSlots->count() > 0
                                        ? round($leasableSlots->where('status', 'occupied')->count() / $leasableSlots->count() * 100, 1)
                                        : 0,
            'total_acquisition_cost' => round($totalAcquisitionCost, 2),
            'total_book_value'       => round($totalBookValue, 2),
            'total_market_value'     => round($totalMarketValue, 2),
            // Fix for audit finding H-3 — acquisition/book/market value
            // totals are now converted into the company's base currency
            // (see perPropertyFinancials()), using the latest exchange
            // rate on file for each property/unit's own currency — the
            // same "live functional view" already used for Revenue/
            // Collections/Expenses elsewhere on this dashboard. Any
            // currency with no rate on file is excluded from the totals
            // (never guessed at) and listed in unconverted_currencies
            // below, same as every other FX-aware figure in this app.
            'currency'               => $baseCurrency,
            'unconverted_currencies' => $portfolioUnconvertedCurrencies,
            'unrealized_gain'        => round($totalMarketValue - $totalBookValue, 2),
            'roi_if_sold'            => $totalAcquisitionCost > 0
                                          ? round(($totalMarketValue - $totalBookValue) / $totalAcquisitionCost * 100, 1)
                                          : null,
            'slots'                  => $leasableSlots->values(),
        ];
    }

    /**
     * Compute a currency-aware total from a plain [currency => amount] map
     * (e.g. from `->groupBy('currency')->pluck(...)`).
     *
     * - Functional view ($viewCurrency null): every currency converted at
     *   the LATEST rate on file and summed — always reflects "what is this
     *   worth today," per the confirmed business rule, not the rate that
     *   applied when each transaction happened.
     * - Single-currency view: returns just that currency's own raw amount,
     *   unconverted (0 if that currency has no rows in this data set).
     *
     * Returns ['total' => float, 'unconverted_currencies' => string[]].
     */
    private function liveCurrencyTotal(array $amountsByCurrency, int $companyId, string $baseCurrency, ?string $viewCurrency): array
    {
        if ($viewCurrency !== null) {
            return ['total' => (float) ($amountsByCurrency[$viewCurrency] ?? 0), 'unconverted_currencies' => []];
        }

        $fx = app(CurrencyConversionService::class);
        $converted = $fx->convertGroupedAmounts($companyId, $baseCurrency, $amountsByCurrency);

        return ['total' => $converted['total'], 'unconverted_currencies' => $converted['unconverted_currencies']];
    }

    /**
     * Shared source of truth for per-property acquisition cost / book value /
     * latest market value — fix for audit H2. Building/Land/Complex parent
     * records carry no financials of their own; all of it lives on their
     * child `property_units` rows. Previously the Portfolio tab aggregated
     * child units correctly while the Profitability tab read the parent
     * row's (empty) columns directly — the two tabs disagreed on the same
     * number. Both tabs now go through this one method, keyed by the
     * PARENT property id in every case (a standalone Unit's own id, or a
     * Building/Land/Complex's id with its units' figures summed into it).
     *
     * Fix for audit finding H-3, refined by Findings 1/3/4 (July 2026
     * cross-audit) — acquisition_cost/book_value/market_value used to be
     * summed as-is regardless of each property/unit's own `currency`
     * field, silently mixing currencies at 1:1 into the portfolio totals.
     * H-3 fixed that by converting live on every request using the latest
     * FX rate on file — which worked, but meant this method duplicated the
     * exact same conversion logic that Keep-or-Sell's unitData() also had
     * to do independently (two implementations that could silently drift
     * apart), and meant the Portfolio tab's totals could shift from one
     * page load to the next purely because someone updated an FX rate,
     * with nothing on screen explaining why.
     *
     * Both problems share one root cause: `properties`/`property_units`/
     * `property_market_values` never got the base_amount/base_currency/
     * fx_rate_used columns every other money-bearing table already has.
     * That's now fixed (see migration
     * 2026_07_15_000001_add_base_currency_columns_to_property_valuation_tables
     * and PropertyController::propertyValuationConversion()/
     * marketValueConversion(), which compute and store these once at write
     * time — acquisition_cost/book_value at the rate in effect on
     * acquisition_date, market_value at the rate in effect on its own
     * value_date). This method now simply reads those stored columns
     * instead of recomputing the conversion itself, which is both the
     * single source of truth Keep-or-Sell's unitData() also reads from,
     * and stable — the Portfolio tab's totals no longer move on their own.
     *
     * @param  \Illuminate\Support\Collection|null  $properties  Pass an
     *         already-loaded collection (with 'units', 'marketValues',
     *         'units.marketValues' eager-loaded) to avoid a second query;
     *         otherwise this loads it fresh.
     * @param  string|null  $baseCurrency  Defaults to the company's own
     *         currency if not passed.
     * @param  string[]|null  $unconvertedCurrencies  Pass a variable by
     *         reference to collect which currencies (if any) still have a
     *         null base_amount — i.e. no FX rate was on file at the time
     *         the record was saved. Run `php artisan property:backfill-
     *         valuation-fx` after adding the missing rate to fill these in.
     */
    private function perPropertyFinancials(int $companyId, ?\Illuminate\Support\Collection $properties = null, ?string $baseCurrency = null, ?array &$unconvertedCurrencies = null): \Illuminate\Support\Collection
    {
        $unconvertedCurrencies ??= [];

        $properties ??= Property::where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->with([
                'units' => fn($q) => $q->whereNull('deleted_at'),
                'marketValues' => fn($q) => $q->orderByDesc('value_date'),
                'units.marketValues' => fn($q) => $q->orderByDesc('value_date'),
            ])
            ->get();

        $baseCurrency = strtoupper($baseCurrency ?: (Company::where('id', $companyId)->value('currency') ?: 'EGP'));

        // Reads a row's stored base-currency figure. Flags the row's
        // currency as "unconverted" only when it genuinely couldn't be
        // converted (foreign currency + no rate on file at save time) —
        // not simply because the amount itself is zero/null.
        $read = function (?string $baseAmount, ?string $rawAmount, ?string $currency) use ($baseCurrency, &$unconvertedCurrencies): float {
            if ($rawAmount === null || (float) $rawAmount == 0.0) {
                return 0.0;
            }
            if ($baseAmount !== null) {
                return (float) $baseAmount;
            }
            $currency = strtoupper($currency ?: $baseCurrency);
            if ($currency !== $baseCurrency) {
                $unconvertedCurrencies[] = $currency;
            }
            return 0.0; // excluded, not guessed at — same rule as the rest of the app
        };

        return $properties->map(function ($p) use ($read) {
            if ($p->nature === 'unit') {
                $mv = $p->marketValues->first();
                return [
                    'id'               => $p->id,
                    'property_name'    => $p->property_name,
                    'acquisition_cost' => $read($p->acquisition_cost_base_amount, $p->acquisition_cost, $p->currency),
                    'book_value'       => $read($p->book_value_base_amount, $p->book_value, $p->currency),
                    'market_value'     => $mv ? $read($mv->base_amount, $mv->market_value, $p->currency) : 0.0,
                ];
            }

            $acq = 0.0; $book = 0.0; $mv = 0.0;
            foreach ($p->units as $u) {
                $acq  += $read($u->acquisition_cost_base_amount, $u->acquisition_cost, $u->currency);
                $book += $read($u->book_value_base_amount, $u->book_value, $u->currency);
                $latestUnitMv = $u->marketValues->first();
                $mv += $latestUnitMv ? $read($latestUnitMv->base_amount, $latestUnitMv->market_value, $u->currency) : 0.0;
            }

            return [
                'id'               => $p->id,
                'property_name'    => $p->property_name,
                'acquisition_cost' => $acq,
                'book_value'       => $book,
                'market_value'     => $mv,
            ];
        })->keyBy('id');
    }

    private function slotStatus(string $ownership, $deliveryDate, $contract): string
    {
        if ($contract) return 'occupied';

        if ($ownership === 'installments' && $deliveryDate) {
            // delivery_date is stored as "MM/YYYY" (varchar(7)), not a real
            // date column — Carbon::parse() on a bare "MM/YYYY" string does
            // NOT reliably give the intended day, so parse it explicitly
            // (fix H1, per the original audit's own recommendation) and
            // treat the delivery month as complete only at its end.
            try {
                $parsed = Carbon::createFromFormat('m/Y', $deliveryDate)->startOfMonth()->endOfMonth();
            } catch (\Exception $e) {
                $parsed = null;
            }

            if ($parsed && $parsed->isFuture()) {
                return 'not_delivered';
            }
        }

        return 'vacant';
    }

    // ═══════════════════════════════════════════════════════════════════
    // TAB 2 — CONTRACT ANALYSIS
    // ═══════════════════════════════════════════════════════════════════
    private function buildContracts(int $companyId, string $dateFrom, string $dateTo): array
    {
        $today = Carbon::today();

        // Fix for audit finding F-1 — this used to pull EVERY contract the
        // company has ever had — running, expired, AND terminated — with
        // three eager-loaded relations each, even though expired/terminated
        // contracts are only ever used below for a .count(). A company with
        // years of renewal history can accumulate a large multiple of its
        // actual active-contract count in expired/terminated rows; loading
        // all of that (plus its relations) just to throw away everything
        // except a count is pure waste that grows every year. Only 'running'
        // contracts — naturally bounded by the number of leasable units in
        // the portfolio, not by elapsed time — are still loaded with their
        // relations, since every other metric in this tab (renewal radar,
        // tenant breakdowns, top tenants, annual increase exposure) only
        // ever looks at running contracts anyway.
        $running = RentContract::where('company_id', $companyId)
            ->where('status', 'running')
            ->with([
                'customer:id,customer_name,tenant_nature',
                'propertyUnit:id,unit_name',
                'property:id,property_name,nature',
            ])
            ->get();

        $statusCounts    = RentContract::where('company_id', $companyId)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');
        $expiredCount    = (int) ($statusCounts['expired'] ?? 0);
        $terminatedCount = (int) ($statusCounts['terminated'] ?? 0);

        // ── Expiring soon (running contracts) ────────────────────────
        $expiring30  = $running->filter(fn($c) => Carbon::parse($c->end_date)->isFuture() && $today->diffInDays(Carbon::parse($c->end_date)) <= 30)->count();
        $expiring60  = $running->filter(fn($c) => Carbon::parse($c->end_date)->isFuture() && $today->diffInDays(Carbon::parse($c->end_date)) <= 60)->count();
        $expiring90  = $running->filter(fn($c) => Carbon::parse($c->end_date)->isFuture() && $today->diffInDays(Carbon::parse($c->end_date)) <= 90)->count();
        $expiring180 = $running->filter(fn($c) => Carbon::parse($c->end_date)->isFuture() && $today->diffInDays(Carbon::parse($c->end_date)) <= 180)->count();

        // ── Expiring contracts detail list ────────────────────────────
        $expiringList = $running->filter(fn($c) => Carbon::parse($c->end_date)->gte($today) && Carbon::parse($c->end_date)->lte($today->copy()->addDays(180)))
            ->sortBy('end_date')
            ->map(fn($c) => [
                'id'           => $c->id,
                'tenant'       => $c->customer?->customer_name,
                'property'     => $c->property?->property_name,
                'unit'         => $c->propertyUnit?->unit_name,
                'end_date'     => $c->end_date,
                'days_left'    => (int) $today->diffInDays(Carbon::parse($c->end_date)),
                'monthly_rent' => (float) $c->monthly_rent_amount,
                'currency'     => $c->contract_currency,
            ])->values();

        // ── Revenue by tenant nature ──────────────────────────────────
        $byTenantNature = $running->groupBy('tenant_nature')->map(fn($g) => [
            'count'        => $g->count(),
            'monthly_rent' => round($g->sum('monthly_rent_amount'), 2),
        ]);

        // ── Revenue by revenue type ───────────────────────────────────
        $byRevenueType = $running->groupBy('revenue_type')->map(fn($g) => [
            'count'        => $g->count(),
            'monthly_rent' => round($g->sum('monthly_rent_amount'), 2),
        ]);

        // ── Top 5 tenants by contracted rent ─────────────────────────
        $topTenants = $running->groupBy(fn($c) => $c->customer?->customer_name ?? 'Unknown')
            ->map(fn($g, $name) => [
                'name'         => $name,
                'contracts'    => $g->count(),
                'monthly_rent' => round($g->sum('monthly_rent_amount'), 2),
            ])
            ->sortByDesc('monthly_rent')
            ->take(5)
            ->values();

        // ── Annual increase exposure ──────────────────────────────────
        // Fix for audit M5 — previously averaged the legacy
        // annual_increase_rate field directly, which (per the logic
        // reference, §4 Legacy-Annual-Rate-Extraction) is only
        // schedule[0].rate — the FIRST year's rate. A contract with an
        // escalating multi-year schedule (e.g. 5% / 7% / 10%) was
        // understating its true long-run escalation here. Now averages
        // across the full schedule for contracts that have one, falling
        // back to the legacy single rate for contracts that don't.
        $contractAvgRates = $running->map(function ($c) {
            $schedule = collect($c->annual_increase_schedule ?? [])
                ->filter(fn ($row) => isset($row['rate']))
                ->pluck('rate')
                ->map(fn ($r) => (float) $r);

            return $schedule->isNotEmpty() ? $schedule->avg() : (float) $c->annual_increase_rate;
        });

        $withIncrease    = $contractAvgRates->filter(fn ($r) => $r > 0)->count();
        $avgIncreaseRate = $contractAvgRates->count() > 0
            ? round($contractAvgRates->avg(), 2)
            : 0;

        // ── Monthly contracted rent trend (running contracts by start month) ─
        $monthlyTrend = RentRevenue::where('company_id', $companyId)
            ->whereBetween('revenue_date', [$dateFrom, $dateTo])
            ->selectRaw("DATE_FORMAT(revenue_date, '%Y-%m') as period, DATE_FORMAT(revenue_date, '%Y%m')+0 as sort_key, SUM(base_amount) as value")
            ->groupBy('period', 'sort_key')
            ->orderBy('sort_key')
            ->get()
            ->map(fn($r) => ['period' => $r->period, 'value' => (float) $r->value])
            ->values();

        return [
            'running_count'    => $running->count(),
            'expired_count'    => $expiredCount,
            'terminated_count' => $terminatedCount,
            'expiring_30'      => $expiring30,
            'expiring_60'      => $expiring60,
            'expiring_90'      => $expiring90,
            'expiring_180'     => $expiring180,
            'expiring_list'    => $expiringList,
            'by_tenant_nature' => $byTenantNature,
            'by_revenue_type'  => $byRevenueType,
            'top_tenants'      => $topTenants,
            'with_increase'    => $withIncrease,
            'avg_increase_rate'=> $avgIncreaseRate,
            'total_monthly_rent' => round($running->sum('monthly_rent_amount'), 2),
            'monthly_trend'    => $monthlyTrend,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════
    // TAB 3 — REVENUE ANALYSIS
    // ═══════════════════════════════════════════════════════════════════
    private function buildRevenues(int $companyId, string $dateFrom, string $dateTo, string $baseCurrency, ?string $viewCurrency = null): array
    {
        // Single-currency view: filter to that currency and use the RAW
        // amount everywhere in this tab (it's already in that currency, no
        // conversion needed or wanted). Functional view: no filter, and the
        // headline total below is computed live from the latest rates;
        // NOTE the sub-breakdowns further down (monthly trend, by type/
        // nature/property) still use the stored base_amount — the rate that
        // applied when each row was generated — not a live re-conversion.
        // That's a disclosed, deliberate scope boundary: the headline total
        // always reflects "today," the detail charts are a very recent but
        // not necessarily up-to-the-second snapshot.
        $aggCol = $viewCurrency ? 'revenue_amount' : 'base_amount';

        $baseQuery = RentRevenue::where('rent_revenues.company_id', $companyId)
            ->whereBetween('rent_revenues.revenue_date', [$dateFrom, $dateTo]);

        if ($viewCurrency) {
            $baseQuery->where('rent_revenues.currency', $viewCurrency);
        }

        // ── Headline total — always live, always today's rate ──────────
        $byCurrency = RentRevenue::where('rent_revenues.company_id', $companyId)
            ->whereBetween('rent_revenues.revenue_date', [$dateFrom, $dateTo])
            ->selectRaw('currency, SUM(revenue_amount) as amount')
            ->groupBy('currency')
            ->pluck('amount', 'currency')
            ->toArray();

        $liveTotal = $this->liveCurrencyTotal($byCurrency, $companyId, $baseCurrency, $viewCurrency);
        $totalRevenue = $liveTotal['total'];
        $unconvertedCurrencies = $liveTotal['unconverted_currencies'];

        // ── Monthly trend ─────────────────────────────────────────────
        $monthlyTrend = (clone $baseQuery)
            ->selectRaw("DATE_FORMAT(rent_revenues.revenue_date, '%Y-%m') as period, DATE_FORMAT(rent_revenues.revenue_date, '%Y%m')+0 as sort_key, SUM(rent_revenues.{$aggCol}) as value")
            ->groupBy('period', 'sort_key')
            ->orderBy('sort_key')
            ->get()
            ->map(fn($r) => ['period' => $r->period, 'value' => (float) $r->value])
            ->values();

        // ── Forward 12 months ─────────────────────────────────────────
        $futureFrom = Carbon::today()->startOfMonth()->toDateString();
        $futureTo   = Carbon::today()->addMonths(11)->endOfMonth()->toDateString();

        $forwardQuery = RentRevenue::where('rent_revenues.company_id', $companyId)
            ->whereBetween('rent_revenues.revenue_date', [$futureFrom, $futureTo]);
        if ($viewCurrency) {
            $forwardQuery->where('rent_revenues.currency', $viewCurrency);
        }

        $forwardRevenue = $forwardQuery
            ->selectRaw("DATE_FORMAT(rent_revenues.revenue_date, '%Y-%m') as period, DATE_FORMAT(rent_revenues.revenue_date, '%Y%m')+0 as sort_key, SUM(rent_revenues.{$aggCol}) as value")
            ->groupBy('period', 'sort_key')
            ->orderBy('sort_key')
            ->get()
            ->map(fn($r) => ['period' => $r->period, 'value' => (float) $r->value])
            ->values();

        // ── By revenue type (join contracts) ─────────────────────────
        $byRevenueType = (clone $baseQuery)
            ->join('rent_contracts', 'rent_revenues.rent_contract_id', '=', 'rent_contracts.id')
            ->selectRaw("rent_contracts.revenue_type, SUM(rent_revenues.{$aggCol}) as value")
            ->groupBy('rent_contracts.revenue_type')
            ->get()
            ->map(fn($r) => ['label' => $r->revenue_type, 'value' => (float) $r->value])
            ->values();

        // ── By tenant nature ──────────────────────────────────────────
        $byTenantNature = (clone $baseQuery)
            ->join('rent_contracts', 'rent_revenues.rent_contract_id', '=', 'rent_contracts.id')
            ->selectRaw("rent_contracts.tenant_nature, SUM(rent_revenues.{$aggCol}) as value")
            ->groupBy('rent_contracts.tenant_nature')
            ->get()
            ->map(fn($r) => ['label' => $r->tenant_nature, 'value' => (float) $r->value])
            ->values();

        // ── By property nature ────────────────────────────────────────
        $byPropertyNature = (clone $baseQuery)
            ->join('rent_contracts', 'rent_revenues.rent_contract_id', '=', 'rent_contracts.id')
            ->join('properties', 'rent_contracts.property_id', '=', 'properties.id')
            ->selectRaw("properties.nature, SUM(rent_revenues.{$aggCol}) as value")
            ->groupBy('properties.nature')
            ->get()
            ->map(fn($r) => ['label' => $r->nature, 'value' => (float) $r->value])
            ->values();

        // ── Top properties by revenue ─────────────────────────────────
        $topProperties = (clone $baseQuery)
            ->join('rent_contracts', 'rent_revenues.rent_contract_id', '=', 'rent_contracts.id')
            ->join('properties', 'rent_contracts.property_id', '=', 'properties.id')
            ->selectRaw("properties.property_name, SUM(rent_revenues.{$aggCol}) as value")
            ->groupBy('properties.id', 'properties.property_name')
            ->orderByDesc('value')
            ->limit(10)
            ->get()
            ->map(fn($r) => ['label' => $r->property_name, 'value' => (float) $r->value])
            ->values();

        return [
            'total_revenue'     => round($totalRevenue, 2),
            'currency'          => $viewCurrency ?? $baseCurrency,
            'is_functional_view'=> $viewCurrency === null,
            'unconverted_currencies' => $unconvertedCurrencies,
            'monthly_trend'     => $monthlyTrend,
            'forward_12_months' => $forwardRevenue,
            'by_revenue_type'   => $byRevenueType,
            'by_tenant_nature'  => $byTenantNature,
            'by_property_nature'=> $byPropertyNature,
            'top_properties'    => $topProperties,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════
    // TAB 4 — COLLECTION ANALYSIS
    // ═══════════════════════════════════════════════════════════════════
    private function buildCollections(int $companyId, string $dateFrom, string $dateTo, string $baseCurrency, ?string $viewCurrency = null): array
    {
        $today = Carbon::today();
        $sumCol = $viewCurrency ? 'collection_amount' : 'base_amount';

        // Fix for audit finding F-1 — this method used to load every single
        // rent_collections row the company has ever had (no date bound at
        // all — RentCollection::where('company_id', $companyId)->get()) into
        // PHP and then filter/group/sum it with Collection methods for three
        // different purposes (in-period totals, a forward 6-month window,
        // and all-time overdue aging). That cost scales with the company's
        // total lifetime row count, not with the size of whatever period is
        // actually being viewed, so the dashboard gets slower every month a
        // company stays on the system even with zero growth in active users.
        // Every aggregate below is now computed by a query scoped to only
        // the date range/status it actually needs, with the grouping and
        // summing done in SQL instead of in PHP.
        $baseQuery = fn () => RentCollection::where('company_id', $companyId)
            ->when($viewCurrency, fn($q) => $q->where('currency', $viewCurrency));

        // ── In-period totals + monthly trend, grouped by month/status in SQL ──
        $inPeriodRows = $baseQuery()
            ->whereBetween('collection_date', [$dateFrom, $dateTo])
            ->selectRaw("DATE_FORMAT(collection_date, '%Y-%m') as period, status, SUM({$sumCol}) as amount")
            ->groupBy('period', 'status')
            ->get();

        $totalCollected = round((float) $inPeriodRows->where('status', 'collected')->sum('amount'), 2);
        $totalPending   = round((float) $inPeriodRows->where('status', 'pending')->sum('amount'), 2);
        $totalOverdue   = round((float) $inPeriodRows->where('status', 'overdue')->sum('amount'), 2);

        // Headline total — always live, always today's rate.
        $byCurrency = RentCollection::where('company_id', $companyId)
            ->whereBetween('collection_date', [$dateFrom, $dateTo])
            ->selectRaw('currency, SUM(collection_amount) as amount')
            ->groupBy('currency')
            ->pluck('amount', 'currency')
            ->toArray();
        $liveTotal = $this->liveCurrencyTotal($byCurrency, $companyId, $baseCurrency, $viewCurrency);
        $totalDue = round($liveTotal['total'], 2);
        $unconvertedCurrencies = $liveTotal['unconverted_currencies'];
        $collectionRate = $totalDue > 0 ? round($totalCollected / $totalDue * 100, 1) : 0;

        // ── Forward 6 months — its own bounded query, not the in-period pull ──
        $futureFrom = $today->copy()->startOfMonth()->toDateString();
        $futureTo   = $today->copy()->addMonths(5)->endOfMonth()->toDateString();

        $forwardRows = $baseQuery()
            ->whereBetween('collection_date', [$futureFrom, $futureTo])
            ->selectRaw("DATE_FORMAT(collection_date, '%Y-%m') as period, status, SUM({$sumCol}) as amount")
            ->groupBy('period', 'status')
            ->get();

        $forwardCollections = $forwardRows
            ->groupBy('period')
            ->map(fn($g, $period) => [
                'period'    => $period,
                'pending'   => round((float) $g->where('status', 'pending')->sum('amount'), 2),
                'collected' => round((float) $g->where('status', 'collected')->sum('amount'), 2),
                'total'     => round((float) $g->sum('amount'), 2),
            ])
            ->sortKeys()
            ->values();

        // ── Overdue aging — pure SQL bucket sums scoped to status='overdue'
        // only (a healthy portfolio's overdue rows are a small fraction of
        // its lifetime rows). DATEDIFF does the day-bucketing in MySQL, so
        // no rows are ever loaded into PHP just to be bucketed. Days overdue
        // = today minus collection_date; DATEDIFF(today, past_date) is
        // always called in that order so it stays positive. ──────────────
        $agingRow = $baseQuery()
            ->where('status', 'overdue')
            ->selectRaw("
                SUM(CASE WHEN DATEDIFF(?, collection_date) <= 30 THEN {$sumCol} ELSE 0 END) as b0_30,
                SUM(CASE WHEN DATEDIFF(?, collection_date) > 30 AND DATEDIFF(?, collection_date) <= 60 THEN {$sumCol} ELSE 0 END) as b31_60,
                SUM(CASE WHEN DATEDIFF(?, collection_date) > 60 AND DATEDIFF(?, collection_date) <= 90 THEN {$sumCol} ELSE 0 END) as b61_90,
                SUM(CASE WHEN DATEDIFF(?, collection_date) > 90 THEN {$sumCol} ELSE 0 END) as b90_plus
            ", array_fill(0, 6, $today->toDateString()))
            ->first();

        $aging = [
            '0_30'    => round((float) ($agingRow->b0_30 ?? 0), 2),
            '31_60'   => round((float) ($agingRow->b31_60 ?? 0), 2),
            '61_90'   => round((float) ($agingRow->b61_90 ?? 0), 2),
            '90_plus' => round((float) ($agingRow->b90_plus ?? 0), 2),
        ];

        // ── Outstanding by tenant ─────────────────────────────────────
        $outstandingByTenantQuery = RentCollection::where('rent_collections.company_id', $companyId)
            ->whereIn('rent_collections.status', ['pending', 'overdue'])
            ->join('rent_contracts', 'rent_collections.rent_contract_id', '=', 'rent_contracts.id')
            ->join('customers', 'rent_contracts.customer_id', '=', 'customers.id');
        if ($viewCurrency) {
            $outstandingByTenantQuery->where('rent_collections.currency', $viewCurrency);
        }
        $outstandingByTenant = $outstandingByTenantQuery
            ->selectRaw("customers.customer_name, SUM(rent_collections.{$sumCol}) as outstanding")
            ->groupBy('customers.id', 'customers.customer_name')
            ->orderByDesc('outstanding')
            ->limit(10)
            ->get()
            ->map(fn($r) => ['tenant' => $r->customer_name, 'outstanding' => (float) $r->outstanding])
            ->values();

        // ── Monthly trend (in period) — reuses the $inPeriodRows already
        // fetched above (grouped by month+status in SQL) instead of a
        // second pull of raw rows. ─────────────────────────────────────
        $monthlyTrend = $inPeriodRows
            ->groupBy('period')
            ->map(fn($g, $period) => [
                'period'    => $period,
                'collected' => round((float) $g->where('status', 'collected')->sum('amount'), 2),
                'pending'   => round((float) $g->where('status', 'pending')->sum('amount'), 2),
                'overdue'   => round((float) $g->where('status', 'overdue')->sum('amount'), 2),
            ])
            ->sortKeys()
            ->values();

        return [
            'total_due'            => $totalDue,
            'currency'             => $viewCurrency ?? $baseCurrency,
            'is_functional_view'   => $viewCurrency === null,
            'unconverted_currencies' => $unconvertedCurrencies,
            'total_collected'      => $totalCollected,
            'total_pending'        => $totalPending,
            'total_overdue'        => $totalOverdue,
            'collection_rate'      => $collectionRate,
            'forward_6_months'     => $forwardCollections,
            'aging'                => $aging,
            'outstanding_by_tenant'=> $outstandingByTenant,
            'monthly_trend'        => $monthlyTrend,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════
    // TAB 5 — DUE INSTALLMENTS
    // ═══════════════════════════════════════════════════════════════════
    private function buildInstallments(int $companyId, string $dateFrom, string $dateTo, string $baseCurrency, ?string $viewCurrency = null): array
    {
        $today = Carbon::today();
        $sumCol = $viewCurrency ? 'amount' : 'base_amount';

        // Fix for audit finding F-1 — same issue and fix approach as
        // buildCollections() above. total_paid/pending/overdue and the
        // by-type/by-property breakdowns are legitimately all-time figures
        // (an installment plan's completion % is meaningless scoped to an
        // arbitrary reporting period — see the Installment-Completion-
        // Percentage logic reference), so date-bounding them isn't the
        // right fix. Doing the grouping/summing in SQL instead of pulling
        // every due row ever generated into PHP is.
        $baseQuery = fn () => \App\Models\PropertyInstallmentDue::where('company_id', $companyId)
            ->when($viewCurrency, fn($q) => $q->where('currency', $viewCurrency));

        $byStatus = $baseQuery()
            ->selectRaw("status, SUM({$sumCol}) as amount")
            ->groupBy('status')
            ->pluck('amount', 'status');

        $totalPaid    = round((float) ($byStatus['paid'] ?? 0), 2);
        $totalPending = round((float) ($byStatus['pending'] ?? 0), 2);
        $totalOverdue = round((float) ($byStatus['overdue'] ?? 0), 2);

        // Headline total — always live, always today's rate.
        $byCurrency = \App\Models\PropertyInstallmentDue::where('company_id', $companyId)
            ->selectRaw('currency, SUM(amount) as total_amount')
            ->groupBy('currency')
            ->pluck('total_amount', 'currency')
            ->toArray();
        $liveTotal = $this->liveCurrencyTotal($byCurrency, $companyId, $baseCurrency, $viewCurrency);
        $totalAmount = round($liveTotal['total'], 2);
        $unconvertedCurrencies = $liveTotal['unconverted_currencies'];

        // ── Forward 6 months — its own bounded query, excludes paid rows ──
        $futureFrom = $today->copy()->startOfMonth()->toDateString();
        $futureTo   = $today->copy()->addMonths(5)->endOfMonth()->toDateString();

        $forward = $baseQuery()
            ->whereBetween('due_date', [$futureFrom, $futureTo])
            ->where('status', '!=', 'paid')
            ->selectRaw("DATE_FORMAT(due_date, '%Y-%m') as period, SUM({$sumCol}) as amount, COUNT(*) as cnt")
            ->groupBy('period')
            ->get()
            ->map(fn($r) => [
                'period' => $r->period,
                'amount' => round((float) $r->amount, 2),
                'count'  => (int) $r->cnt,
            ])
            ->sortBy('period')
            ->values();

        // ── By type — grouped in SQL instead of a PHP groupBy over every row ──
        $byType = $baseQuery()
            ->selectRaw("
                due_type,
                SUM({$sumCol}) as total,
                SUM(CASE WHEN status = 'paid' THEN {$sumCol} ELSE 0 END) as paid,
                SUM(CASE WHEN status = 'pending' THEN {$sumCol} ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'overdue' THEN {$sumCol} ELSE 0 END) as overdue
            ")
            ->groupBy('due_type')
            ->get()
            ->map(fn($r) => [
                'type'    => $r->due_type,
                'total'   => round((float) $r->total, 2),
                'paid'    => round((float) $r->paid, 2),
                'pending' => round((float) $r->pending, 2),
                'overdue' => round((float) $r->overdue, 2),
            ])
            ->values();

        // ── Overdue aging — pure SQL bucket sums scoped to status='overdue'
        // only, never a full-table pull. Days overdue = today minus
        // due_date; DATEDIFF(today, past_date) always called in that order
        // so it stays positive. ─────────────────────────────────────────
        $agingRow = $baseQuery()
            ->where('status', 'overdue')
            ->selectRaw("
                SUM(CASE WHEN DATEDIFF(?, due_date) <= 30 THEN {$sumCol} ELSE 0 END) as b0_30,
                SUM(CASE WHEN DATEDIFF(?, due_date) > 30 AND DATEDIFF(?, due_date) <= 60 THEN {$sumCol} ELSE 0 END) as b31_60,
                SUM(CASE WHEN DATEDIFF(?, due_date) > 60 AND DATEDIFF(?, due_date) <= 90 THEN {$sumCol} ELSE 0 END) as b61_90,
                SUM(CASE WHEN DATEDIFF(?, due_date) > 90 THEN {$sumCol} ELSE 0 END) as b90_plus
            ", array_fill(0, 6, $today->toDateString()))
            ->first();

        $aging = [
            '0_30'    => round((float) ($agingRow->b0_30 ?? 0), 2),
            '31_60'   => round((float) ($agingRow->b31_60 ?? 0), 2),
            '61_90'   => round((float) ($agingRow->b61_90 ?? 0), 2),
            '90_plus' => round((float) ($agingRow->b90_plus ?? 0), 2),
        ];

        // ── Per property summary ──────────────────────────────────────
        $byPropertyQuery = \App\Models\PropertyInstallmentDue::where('property_installment_dues.company_id', $companyId)
            ->join('properties', 'property_installment_dues.property_id', '=', 'properties.id');
        if ($viewCurrency) {
            $byPropertyQuery->where('property_installment_dues.currency', $viewCurrency);
        }
        $byProperty = $byPropertyQuery
            ->selectRaw("properties.property_name, SUM(property_installment_dues.{$sumCol}) as total, SUM(CASE WHEN property_installment_dues.status = \"paid\" THEN property_installment_dues.{$sumCol} ELSE 0 END) as paid_amount, SUM(CASE WHEN property_installment_dues.status = \"overdue\" THEN property_installment_dues.{$sumCol} ELSE 0 END) as overdue_amount")
            ->groupBy('properties.id', 'properties.property_name')
            ->get()
            ->map(fn($r) => [
                'property'       => $r->property_name,
                'total'          => (float) $r->total,
                'paid'           => (float) $r->paid_amount,
                'overdue'        => (float) $r->overdue_amount,
                'outstanding'    => round((float)$r->total - (float)$r->paid_amount, 2),
                'completion_pct' => $r->total > 0 ? round((float)$r->paid_amount / (float)$r->total * 100, 1) : 0,
            ])
            ->values();

        return [
            'total_amount'  => $totalAmount,
            'currency'      => $viewCurrency ?? $baseCurrency,
            'is_functional_view' => $viewCurrency === null,
            'unconverted_currencies' => $unconvertedCurrencies,
            'total_paid'    => $totalPaid,
            'total_pending' => $totalPending,
            'total_overdue' => $totalOverdue,
            'paid_pct'      => $totalAmount > 0 ? round($totalPaid / $totalAmount * 100, 1) : 0,
            'forward_6_months' => $forward,
            'by_type'       => $byType,
            'aging'         => $aging,
            'by_property'   => $byProperty,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════
    // TAB 6 — EXPENSE ANALYSIS
    // ═══════════════════════════════════════════════════════════════════
    private function buildExpenses(int $companyId, string $dateFrom, string $dateTo, string $baseCurrency, ?string $viewCurrency = null): array
    {
        $sumCol = $viewCurrency ? 'expense_amount' : 'base_amount';
        $paymentSumCol = $viewCurrency ? 'amount' : 'base_amount';

        // ── Direct (Property) Expenses ──────────────────────────────────
        $expensesQuery = PropertyExpense::where('company_id', $companyId)
            ->whereBetween('expense_date', [$dateFrom, $dateTo])
            ->with(['expenseCategory:id,category_name', 'expenseItem:id,item_name', 'payments']);
        if ($viewCurrency) {
            $expensesQuery->where('currency', $viewCurrency);
        }
        $expenses = $expensesQuery->get();

        $byCurrency = PropertyExpense::where('company_id', $companyId)
            ->whereBetween('expense_date', [$dateFrom, $dateTo])
            ->selectRaw('currency, SUM(expense_amount) as total_amount')
            ->groupBy('currency')
            ->pluck('total_amount', 'currency')
            ->toArray();
        $liveTotal = $this->liveCurrencyTotal($byCurrency, $companyId, $baseCurrency, $viewCurrency);
        $directCommitted = round($liveTotal['total'], 2);
        $unconvertedCurrencies = $liveTotal['unconverted_currencies'];
        $directPaid = round($expenses->sum(fn($e) => $e->payments->sum($paymentSumCol)), 2);

        // ── Corporate Expenses (this company's SHARE of allocated costs) ──
        // Wired in per the July 2026 session — Corporate Expenses previously
        // had no presence anywhere on the Dashboard. Committed/paid totals
        // here use the expense's own committed/paid amounts directly (this
        // is a company-wide accrual view, unlike the per-property Rent vs
        // Expenses report which apportions by allocation_pct because THAT
        // report is scoped to one property). By-category and by-property
        // below DO apportion by allocation_pct, since "by property" only
        // makes sense once a company-wide cost is broken back down per unit.
        $corporateQuery = CorporateExpense::where('company_id', $companyId)
            ->whereBetween('expense_date', [$dateFrom, $dateTo])
            ->with(['expenseCategory:id,category_name', 'expenseItem:id,item_name', 'payments']);
        if ($viewCurrency) {
            $corporateQuery->where('currency', $viewCurrency);
        }
        $corporateExpenses = $corporateQuery->get();

        $corpByCurrency = CorporateExpense::where('company_id', $companyId)
            ->whereBetween('expense_date', [$dateFrom, $dateTo])
            ->selectRaw('currency, SUM(expense_amount) as total_amount')
            ->groupBy('currency')
            ->pluck('total_amount', 'currency')
            ->toArray();
        $corpLiveTotal = $this->liveCurrencyTotal($corpByCurrency, $companyId, $baseCurrency, $viewCurrency);
        $corporateCommitted = round($corpLiveTotal['total'], 2);
        $unconvertedCurrencies = array_values(array_unique(array_merge($unconvertedCurrencies, $corpLiveTotal['unconverted_currencies'])));
        $corporatePaid = round($corporateExpenses->sum(fn($e) => $e->payments->sum($paymentSumCol)), 2);

        // ── Blended totals ───────────────────────────────────────────────
        $totalCommitted   = round($directCommitted + $corporateCommitted, 2);
        $totalPaid        = round($directPaid + $corporatePaid, 2);
        $totalOutstanding = round($totalCommitted - $totalPaid, 2);

        // ── By category — blended, category is meaningful regardless of source ──
        $directByCategory = $expenses->groupBy(fn($e) => $e->expenseCategory?->category_name ?? 'Uncategorized')
            ->map(fn($g) => ['value' => (float) $g->sum($sumCol), 'count' => $g->count()]);
        $corpByCategory = $corporateExpenses->groupBy(fn($e) => $e->expenseCategory?->category_name ?? 'Uncategorized')
            ->map(fn($g) => ['value' => (float) $g->sum($sumCol), 'count' => $g->count()]);
        $byCategory = $directByCategory->keys()->merge($corpByCategory->keys())->unique()
            ->map(function ($cat) use ($directByCategory, $corpByCategory) {
                $d = $directByCategory->get($cat, ['value' => 0, 'count' => 0]);
                $c = $corpByCategory->get($cat, ['value' => 0, 'count' => 0]);
                return ['label' => $cat, 'value' => round($d['value'] + $c['value'], 2), 'count' => $d['count'] + $c['count']];
            })
            ->sortByDesc('value')
            ->values();

        // ── By property — Direct is a direct join; Corporate is apportioned
        // by each unit's stored allocation_pct snapshot ─────────────────
        $byPropertyQuery = PropertyExpense::where('property_expenses.company_id', $companyId)
            ->whereBetween('property_expenses.expense_date', [$dateFrom, $dateTo])
            ->join('properties', 'property_expenses.property_id', '=', 'properties.id');
        if ($viewCurrency) {
            $byPropertyQuery->where('property_expenses.currency', $viewCurrency);
        }
        $directByProperty = $byPropertyQuery
            ->selectRaw("properties.property_name, SUM(property_expenses.{$sumCol}) as value")
            ->groupBy('properties.id', 'properties.property_name')
            ->get()
            ->pluck('value', 'property_name');

        $corpByPropertyQuery = DB::table('corporate_expense_allocations as cea')
            ->join('corporate_expenses as ce', 'ce.id', '=', 'cea.corporate_expense_id')
            ->join('properties as p', 'p.id', '=', 'cea.property_id')
            ->where('ce.company_id', $companyId)
            ->whereBetween('ce.expense_date', [$dateFrom, $dateTo]);
        if ($viewCurrency) {
            $corpByPropertyQuery->where('ce.currency', $viewCurrency);
        }
        $corpSumExpr = "ce.{$sumCol} * cea.allocation_pct / 100";
        $corpByProperty = $corpByPropertyQuery
            ->selectRaw("p.property_name, SUM({$corpSumExpr}) as value")
            ->groupBy('p.id', 'p.property_name')
            ->get()
            ->pluck('value', 'property_name');

        $byProperty = $directByProperty->keys()->merge($corpByProperty->keys())->unique()
            ->map(fn ($name) => [
                'label' => $name,
                'value' => round((float) ($directByProperty[$name] ?? 0) + (float) ($corpByProperty[$name] ?? 0), 2),
            ])
            ->sortByDesc('value')
            ->values();

        // ── Monthly trend — blended ──────────────────────────────────────
        $monthlyTrendQuery = PropertyExpense::where('company_id', $companyId)
            ->whereBetween('expense_date', [$dateFrom, $dateTo]);
        if ($viewCurrency) {
            $monthlyTrendQuery->where('currency', $viewCurrency);
        }
        $directTrend = $monthlyTrendQuery
            ->selectRaw("DATE_FORMAT(expense_date, '%Y-%m') as period, SUM({$sumCol}) as value")
            ->groupBy('period')
            ->get()
            ->pluck('value', 'period');

        $corpMonthlyTrendQuery = CorporateExpense::where('company_id', $companyId)
            ->whereBetween('expense_date', [$dateFrom, $dateTo]);
        if ($viewCurrency) {
            $corpMonthlyTrendQuery->where('currency', $viewCurrency);
        }
        $corpTrend = $corpMonthlyTrendQuery
            ->selectRaw("DATE_FORMAT(expense_date, '%Y-%m') as period, SUM({$sumCol}) as value")
            ->groupBy('period')
            ->get()
            ->pluck('value', 'period');

        $monthlyTrend = $directTrend->keys()->merge($corpTrend->keys())->unique()->sort()->values()
            ->map(fn ($period) => [
                'period' => $period,
                'value'  => round((float) ($directTrend[$period] ?? 0) + (float) ($corpTrend[$period] ?? 0), 2),
            ])
            ->values();

        // ── Status breakdown — Direct only. PropertyExpense and
        // CorporateExpense share the same three-tier status enum, but this
        // chart is left scoped to Direct expenses for now — a deliberate
        // scope decision, not an oversight, since blending two different
        // models' status rows would need the chart itself to carry a
        // source label to stay meaningful. Revisit if this becomes a gap.
        $byStatus = $expenses->groupBy('status')->map(fn($g, $s) => [
            'status' => $s,
            'count'  => $g->count(),
            'amount' => round($g->sum($sumCol), 2),
        ])->values();

        return [
            'total_committed'  => $totalCommitted,
            'currency'         => $viewCurrency ?? $baseCurrency,
            'is_functional_view' => $viewCurrency === null,
            'unconverted_currencies' => $unconvertedCurrencies,
            'total_paid'       => $totalPaid,
            'total_outstanding'=> $totalOutstanding,
            'payment_rate'     => $totalCommitted > 0 ? round($totalPaid / $totalCommitted * 100, 1) : 0,
            // Direct vs Corporate split — same distinction as the
            // Rent vs Expenses report, at the portfolio level instead of
            // per-property.
            'by_source'        => [
                ['label' => 'Direct',                  'committed' => $directCommitted,    'paid' => $directPaid],
                ['label' => 'Corporate (Allocated)',    'committed' => $corporateCommitted, 'paid' => $corporatePaid],
            ],
            'by_category'      => $byCategory,
            'by_property'      => $byProperty,
            // Fix for audit finding H-2 — the "By Property" breakdown just
            // above apportions each Corporate Expense across units by its
            // stored allocation_pct snapshot, while 'by_source' above sums
            // the FULL, un-apportioned Corporate commitment as one
            // company-wide accrual figure. Both numbers are individually
            // correct, but they will only foot to the same total if every
            // eligible unit for every corporate expense is included in
            // 'by_property' — a reader comparing the two side by side
            // without that context could easily read the difference as an
            // error. This note exists so the frontend can surface it
            // directly next to the breakdown instead of leaving it to a
            // code comment only the developer ever sees.
            'by_property_note' => 'Corporate expenses in this breakdown are split across units by their allocation share; the Corporate total above is the full, un-apportioned company-wide commitment. The two won\'t necessarily add up to the same figure unless every eligible unit is included here.',
            'monthly_trend'    => $monthlyTrend,
            'by_status'        => $byStatus,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════
    // TAB 7 — PROFITABILITY
    // ═══════════════════════════════════════════════════════════════════
    private function buildProfitability(int $companyId, string $dateFrom, string $dateTo, string $baseCurrency, ?string $viewCurrency = null): array
    {
        $revSumCol = $viewCurrency ? 'revenue_amount' : 'base_amount';
        $expSumCol = $viewCurrency ? 'expense_amount' : 'base_amount';

        // ── Total revenue in period — headline is always live/latest-rate ──
        $revByCurrency = RentRevenue::where('company_id', $companyId)
            ->whereBetween('revenue_date', [$dateFrom, $dateTo])
            ->selectRaw('currency, SUM(revenue_amount) as amount')
            ->groupBy('currency')->pluck('amount', 'currency')->toArray();
        $revLive = $this->liveCurrencyTotal($revByCurrency, $companyId, $baseCurrency, $viewCurrency);
        $totalRevenue = $revLive['total'];

        // ── Total expenses in period (Direct) ─────────────────────────
        $expByCurrency = PropertyExpense::where('company_id', $companyId)
            ->whereBetween('expense_date', [$dateFrom, $dateTo])
            ->selectRaw('currency, SUM(expense_amount) as amount')
            ->groupBy('currency')->pluck('amount', 'currency')->toArray();
        $expLive = $this->liveCurrencyTotal($expByCurrency, $companyId, $baseCurrency, $viewCurrency);
        $directExpenses = $expLive['total'];

        // ── Corporate Expenses (this company's allocated costs) — wired in
        // per the July 2026 session, same as Dashboard Tab 6. Committed
        // (accrual) basis, matching how Direct expenses are already treated
        // here — NOI is "revenue earned minus costs incurred," not cash-basis.
        $corpExpByCurrency = CorporateExpense::where('company_id', $companyId)
            ->whereBetween('expense_date', [$dateFrom, $dateTo])
            ->selectRaw('currency, SUM(expense_amount) as amount')
            ->groupBy('currency')->pluck('amount', 'currency')->toArray();
        $corpExpLive = $this->liveCurrencyTotal($corpExpByCurrency, $companyId, $baseCurrency, $viewCurrency);
        $corporateExpenses = $corpExpLive['total'];

        $totalExpenses = round($directExpenses + $corporateExpenses, 2);

        $unconvertedCurrencies = array_values(array_unique(array_merge(
            $revLive['unconverted_currencies'], $expLive['unconverted_currencies'], $corpExpLive['unconverted_currencies']
        )));

        $noi           = round($totalRevenue - $totalExpenses, 2);
        $noiMargin     = $totalRevenue > 0 ? round($noi / $totalRevenue * 100, 1) : 0;

        // ── Per property P&L ──────────────────────────────────────────
        // NOTE: when a single currency is selected, this restricts to rows in
        // that currency, so a property with revenue in USD and expenses in
        // EGP would show only its USD side in the USD view (its EGP expenses
        // aren't part of "the USD picture") — consistent with how Cash
        // Forecast's single-currency view works.
        $revenuePropQuery = RentRevenue::where('rent_revenues.company_id', $companyId)
            ->whereBetween('rent_revenues.revenue_date', [$dateFrom, $dateTo])
            ->join('rent_contracts', 'rent_revenues.rent_contract_id', '=', 'rent_contracts.id')
            ->join('properties', 'rent_contracts.property_id', '=', 'properties.id');
        if ($viewCurrency) {
            $revenuePropQuery->where('rent_revenues.currency', $viewCurrency);
        }
        $revenueByProperty = $revenuePropQuery
            ->selectRaw("properties.id, properties.property_name, SUM(rent_revenues.{$revSumCol}) as revenue")
            ->groupBy('properties.id', 'properties.property_name')
            ->get()
            ->keyBy('id');

        $expensePropQuery = PropertyExpense::where('company_id', $companyId)
            ->whereBetween('expense_date', [$dateFrom, $dateTo]);
        if ($viewCurrency) {
            $expensePropQuery->where('currency', $viewCurrency);
        }
        $expenseByProperty = $expensePropQuery
            ->selectRaw("property_id, SUM({$expSumCol}) as expenses")
            ->groupBy('property_id')
            ->get()
            ->keyBy('property_id');

        // Corporate Expenses apportioned by each unit's stored allocation_pct
        // snapshot — same apportionment used in the Rent vs Expenses report
        // and Dashboard Tab 6's "by property" breakdown.
        $corpExpensePropQuery = DB::table('corporate_expense_allocations as cea')
            ->join('corporate_expenses as ce', 'ce.id', '=', 'cea.corporate_expense_id')
            ->where('ce.company_id', $companyId)
            ->whereBetween('ce.expense_date', [$dateFrom, $dateTo]);
        if ($viewCurrency) {
            $corpExpensePropQuery->where('ce.currency', $viewCurrency);
        }
        $corpExpSumExpr = "ce.{$expSumCol} * cea.allocation_pct / 100";
        $corpExpenseByProperty = $corpExpensePropQuery
            ->selectRaw("cea.property_id, SUM({$corpExpSumExpr}) as expenses")
            ->groupBy('cea.property_id')
            ->get()
            ->keyBy('property_id');

        $allPropertyIds = $revenueByProperty->keys()->merge($expenseByProperty->keys())->merge($corpExpenseByProperty->keys())->unique();

        // Fix for audit H2 — acquisition_cost/book_value now come from the
        // shared perPropertyFinancials() helper, which correctly sums child
        // property_units for Building/Land/Complex parents instead of
        // reading the parent row's own (typically empty) columns. This is
        // the same helper the Portfolio tab uses, so the two tabs can no
        // longer disagree on the same property's numbers.
        $valuationUnconvertedCurrencies = [];
        $financials = $this->perPropertyFinancials($companyId, null, $baseCurrency, $valuationUnconvertedCurrencies);

        $perProperty = $allPropertyIds->map(function ($pid) use ($revenueByProperty, $expenseByProperty, $corpExpenseByProperty, $financials) {
            $rev  = (float) ($revenueByProperty[$pid]->revenue ?? 0);
            $directExp = (float) ($expenseByProperty[$pid]->expenses ?? 0);
            $corpExp   = (float) ($corpExpenseByProperty[$pid]->expenses ?? 0);
            $exp  = round($directExp + $corpExp, 2);
            $noi  = round($rev - $exp, 2);
            $fin  = $financials[$pid] ?? null;
            $acqCost = (float) ($fin['acquisition_cost'] ?? 0);

            return [
                'property'        => $revenueByProperty[$pid]->property_name ?? ($fin['property_name'] ?? 'Unknown'),
                'revenue'         => $rev,
                'expenses'        => $exp,
                'direct_expenses'   => round($directExp, 2),
                'corporate_expenses'=> round($corpExp, 2),
                'noi'             => $noi,
                'noi_margin'      => $rev > 0 ? round($noi / $rev * 100, 1) : 0,
                'acquisition_cost'=> $acqCost,
                'roi_pct'         => $acqCost > 0 ? round($noi / $acqCost * 100, 2) : null,
            ];
        })->sortByDesc('noi')->values();

        // ── Monthly NOI trend ─────────────────────────────────────────
        $monthlyRevenueQuery = RentRevenue::where('company_id', $companyId)
            ->whereBetween('revenue_date', [$dateFrom, $dateTo]);
        if ($viewCurrency) $monthlyRevenueQuery->where('currency', $viewCurrency);
        $monthlyRevenue = $monthlyRevenueQuery
            ->selectRaw("DATE_FORMAT(revenue_date, '%Y-%m') as period, DATE_FORMAT(revenue_date, '%Y%m')+0 as sort_key, SUM({$revSumCol}) as value")
            ->groupBy('period', 'sort_key')
            ->orderBy('sort_key')
            ->get()
            ->keyBy('period');

        $monthlyExpensesQuery = PropertyExpense::where('company_id', $companyId)
            ->whereBetween('expense_date', [$dateFrom, $dateTo]);
        if ($viewCurrency) $monthlyExpensesQuery->where('currency', $viewCurrency);
        $monthlyExpenses = $monthlyExpensesQuery
            ->selectRaw("DATE_FORMAT(expense_date, '%Y-%m') as period, SUM({$expSumCol}) as value")
            ->groupBy('period')
            ->get()
            ->keyBy('period');

        $monthlyCorpExpensesQuery = CorporateExpense::where('company_id', $companyId)
            ->whereBetween('expense_date', [$dateFrom, $dateTo]);
        if ($viewCurrency) $monthlyCorpExpensesQuery->where('currency', $viewCurrency);
        $monthlyCorpExpenses = $monthlyCorpExpensesQuery
            ->selectRaw("DATE_FORMAT(expense_date, '%Y-%m') as period, SUM({$expSumCol}) as value")
            ->groupBy('period')
            ->get()
            ->keyBy('period');

        $allPeriods = $monthlyRevenue->keys()->merge($monthlyExpenses->keys())->merge($monthlyCorpExpenses->keys())->unique()->sort()->values();

        $monthlyNoi = $allPeriods->map(function ($period) use ($monthlyRevenue, $monthlyExpenses, $monthlyCorpExpenses) {
            $rev = (float) ($monthlyRevenue[$period]->value ?? 0);
            $exp = (float) ($monthlyExpenses[$period]->value ?? 0) + (float) ($monthlyCorpExpenses[$period]->value ?? 0);
            return [
                'period'   => $period,
                'revenue'  => $rev,
                'expenses' => round($exp, 2),
                'noi'      => round($rev - $exp, 2),
            ];
        })->values();

        // ── Market value gain ─────────────────────────────────────────
        // Fix for audit H2 — previously grouped property_market_values by
        // property_id only, which silently dropped every unit-level market
        // value row (Building/Land/Complex market values are recorded
        // against property_unit_id, not property_id — see
        // property_market_values' nullable property_id/property_unit_id
        // pair). Combined with summing Property::book_value directly (also
        // empty for those same parents), this tab's unrealized gain could
        // differ substantially from the Portfolio tab's. Both now go
        // through perPropertyFinancials(), the same helper used above and
        // in buildPortfolio().
        $totalMarketValue  = round($financials->sum('market_value'), 2);
        $totalBookValue    = round($financials->sum('book_value'), 2);
        $unrealizedGain    = round($totalMarketValue - $totalBookValue, 2);

        return [
            'total_revenue'    => round($totalRevenue, 2),
            'total_expenses'   => round($totalExpenses, 2),
            'direct_expenses'    => round($directExpenses, 2),
            'corporate_expenses' => round($corporateExpenses, 2),
            // NOTE: total_revenue/total_expenses/noi/per_property respect
            // the currency picker (fix C4). total_market_value/
            // total_book_value/unrealized_gain below are ALSO now converted
            // (fix H-3) — via perPropertyFinancials(), using the latest
            // exchange rate on file for each property/unit's own currency.
            // Any currency with no rate on file is excluded (never guessed
            // at) and appears in unconverted_currencies below.
            'currency'         => $viewCurrency ?? $baseCurrency,
            'is_functional_view' => $viewCurrency === null,
            'unconverted_currencies' => array_values(array_unique(array_merge($unconvertedCurrencies, $valuationUnconvertedCurrencies))),
            'noi'              => $noi,
            'noi_margin'       => $noiMargin,
            'per_property'     => $perProperty,
            'monthly_noi'      => $monthlyNoi,
            'total_market_value' => $totalMarketValue,
            'total_book_value'   => $totalBookValue,
            'unrealized_gain'    => $unrealizedGain,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════
    // AUTO INSIGHTS
    // ═══════════════════════════════════════════════════════════════════
    private function buildInsights(int $companyId, string $dateFrom, string $dateTo, string $baseCurrency): array
    {
        $insights = [];
        $today    = Carbon::today();

        // ── Missing FX rates ───────────────────────────────────────────
        // Fix for audit C4: rows in a foreign currency with no matching
        // currency_rates entry get base_amount = null and are silently
        // excluded from every SUM() in this dashboard (rather than wrongly
        // treated as 1:1 EGP). Surface that here so it's never silent.
        $missingFx = RentRevenue::where('company_id', $companyId)->whereNull('base_amount')->count()
            + RentCollection::where('company_id', $companyId)->whereNull('base_amount')->count()
            + PropertyExpense::where('company_id', $companyId)->whereNull('base_amount')->count()
            + PropertyInstallmentDue::where('company_id', $companyId)->whereNull('base_amount')->count();

        if ($missingFx > 0) {
            $insights[] = [
                'type'  => 'warning',
                'icon'  => '💱',
                'title' => 'Missing Exchange Rates',
                'body'  => "{$missingFx} record(s) are in a foreign currency with no matching exchange rate on file, so they are currently excluded from every total on this dashboard. Add the missing rate(s) under Company Settings → Exchange Rates.",
            ];
        }

        // ── Contracts expiring within 60 days ─────────────────────────
        $expiring60 = RentContract::where('company_id', $companyId)
            ->where('status', 'running')
            ->whereBetween('end_date', [$today->toDateString(), $today->copy()->addDays(60)->toDateString()])
            ->with('customer:id,customer_name')
            ->count();

        if ($expiring60 > 0) {
            $insights[] = [
                'type'  => 'warning',
                'icon'  => '⏰',
                'title' => 'Contracts Expiring Soon',
                'body'  => "{$expiring60} contract(s) expire within the next 60 days. Review and initiate renewal negotiations before it's too late.",
            ];
        }

        // ── Overdue collections ───────────────────────────────────────
        $overdueCollections = RentCollection::where('company_id', $companyId)
            ->where('status', 'overdue')
            ->sum('base_amount');

        if ($overdueCollections > 0) {
            $insights[] = [
                'type'  => 'danger',
                'icon'  => '🚨',
                'title' => 'Overdue Collections',
                'body'  => 'Total overdue rent collections: ' . number_format($overdueCollections, 0) . " {$baseCurrency}. Follow up with tenants immediately.",
            ];
        }

        // ── Overdue installments ──────────────────────────────────────
        $overdueInstallments = \App\Models\PropertyInstallmentDue::where('company_id', $companyId)
            ->where('status', 'overdue')
            ->sum('base_amount');

        if ($overdueInstallments > 0) {
            $insights[] = [
                'type'  => 'danger',
                'icon'  => '💸',
                'title' => 'Overdue Installment Payments',
                'body'  => 'Outstanding overdue installments: ' . number_format($overdueInstallments, 0) . " {$baseCurrency}. Contact developer/seller for status.",
            ];
        }

        // ── Vacancy alert ─────────────────────────────────────────────
        $vacantCount = $this->countVacantSlots($companyId);
        if ($vacantCount > 0) {
            $insights[] = [
                'type'  => 'warning',
                'icon'  => '🏠',
                'title' => 'Vacant Units',
                'body'  => "{$vacantCount} unit(s) currently have no active lease contract. Vacancy reduces portfolio yield.",
            ];
        }

        // ── Revenue trend ─────────────────────────────────────────────
        $monthlyRows = RentRevenue::where('company_id', $companyId)
            ->whereBetween('revenue_date', [$dateFrom, $dateTo])
            ->selectRaw("DATE_FORMAT(revenue_date, '%Y-%m') as period, DATE_FORMAT(revenue_date, '%Y%m')+0 as sort_key, SUM(base_amount) as value")
            ->groupBy('period', 'sort_key')
            ->orderBy('sort_key')
            ->get();

        if ($monthlyRows->count() >= 2) {
            $last = (float) $monthlyRows->last()->value;
            $prev = (float) $monthlyRows->slice(-2, 1)->first()->value;
            $mom  = $prev > 0 ? round(($last - $prev) / $prev * 100, 1) : 0;

            if ($mom <= -15) {
                $insights[] = [
                    'type'  => 'danger',
                    'icon'  => '📉',
                    'title' => 'Revenue Drop Detected',
                    'body'  => "Last month revenue dropped {$mom}% vs prior month. Check for contract terminations or missed schedule generation.",
                ];
            } elseif ($mom >= 15) {
                $insights[] = [
                    'type'  => 'positive',
                    'icon'  => '📈',
                    'title' => 'Revenue Growth',
                    'body'  => "Last month revenue grew {$mom}% vs prior month — strong performance.",
                ];
            }
        }

        // ── High expense ratio ────────────────────────────────────────
        $totalRevenue  = (float) RentRevenue::where('company_id', $companyId)->whereBetween('revenue_date', [$dateFrom, $dateTo])->sum('base_amount');
        $totalExpenses = (float) PropertyExpense::where('company_id', $companyId)->whereBetween('expense_date', [$dateFrom, $dateTo])->sum('base_amount');

        if ($totalRevenue > 0) {
            $expenseRatio = round($totalExpenses / $totalRevenue * 100, 1);
            if ($expenseRatio > 40) {
                $insights[] = [
                    'type'  => 'warning',
                    'icon'  => '⚠️',
                    'title' => 'High Expense Ratio',
                    'body'  => "Property expenses are {$expenseRatio}% of rental revenue. Review cost categories for optimization opportunities.",
                ];
            }
        }

        // ── Positive: good collection rate ───────────────────────────
        $collected = (float) RentCollection::where('company_id', $companyId)
            ->whereBetween('collection_date', [$dateFrom, $dateTo])
            ->where('status', 'collected')
            ->sum('base_amount');
        $totalDue = (float) RentCollection::where('company_id', $companyId)
            ->whereBetween('collection_date', [$dateFrom, $dateTo])
            ->sum('base_amount');

        if ($totalDue > 0 && ($collected / $totalDue) >= 0.95) {
            $insights[] = [
                'type'  => 'positive',
                'icon'  => '✅',
                'title' => 'Excellent Collection Rate',
                'body'  => round($collected / $totalDue * 100, 1) . '% of rent due in this period has been collected — outstanding portfolio performance.',
            ];
        }

        return $insights;
    }

    private function countVacantSlots(int $companyId): int
    {
        // Fix for audit M4 — this used to count anything without a running
        // contract as "vacant," with no not_delivered carve-out at all —
        // even coarser than the Portfolio tab's own (then-broken) attempt.
        // It now reuses the exact same slotStatus() method (and the same
        // installmentPlan.delivery_date eager-load, fix H1) as the
        // Portfolio tab, so this insight's vacancy count can no longer
        // disagree with what the Portfolio tab itself shows.
        $properties = Property::where('company_id', $companyId)->whereNull('deleted_at')
            ->with([
                'units' => fn($q) => $q->whereNull('deleted_at'),
                'installmentPlan:id,property_id,delivery_date',
            ])->get();

        $runningContracts = RentContract::where('company_id', $companyId)->where('status', 'running')->get();

        $vacant = 0;
        foreach ($properties as $p) {
            if ($p->nature === 'unit') {
                $contract = $runningContracts->where('property_id', $p->id)->where('property_unit_id', null)->first();
                if ($this->slotStatus($p->ownership, $p->installmentPlan?->delivery_date, $contract) === 'vacant') {
                    $vacant++;
                }
            } else {
                foreach ($p->units as $u) {
                    $contract = $runningContracts->where('property_unit_id', $u->id)->first();
                    // installmentPlan is one-per-PROPERTY (see H1 fix above),
                    // so every child unit shares the parent's delivery_date.
                    if ($this->slotStatus($u->ownership ?? $p->ownership, $p->installmentPlan?->delivery_date, $contract) === 'vacant') {
                        $vacant++;
                    }
                }
            }
        }
        return $vacant;
    }
}