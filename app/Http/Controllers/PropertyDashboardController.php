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
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PropertyDashboardController extends Controller
{
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
        ]);

        $dateFrom = $request->date_from ?? Carbon::now()->startOfYear()->toDateString();
        $dateTo   = $request->date_to   ?? Carbon::now()->toDateString();

        return response()->json([
            'portfolio'    => $this->buildPortfolio($company->id),
            'contracts'    => $this->buildContracts($company->id, $dateFrom, $dateTo),
            'revenues'     => $this->buildRevenues($company->id, $dateFrom, $dateTo),
            'collections'  => $this->buildCollections($company->id, $dateFrom, $dateTo),
            'installments' => $this->buildInstallments($company->id, $dateFrom, $dateTo),
            'expenses'     => $this->buildExpenses($company->id, $dateFrom, $dateTo),
            'profitability'=> $this->buildProfitability($company->id, $dateFrom, $dateTo),
            'insights'     => $this->buildInsights($company->id, $dateFrom, $dateTo),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // TAB 1 — PORTFOLIO OVERVIEW
    // ═══════════════════════════════════════════════════════════════════
    private function buildPortfolio(int $companyId): array
    {
        // ── All properties ────────────────────────────────────────────
        $properties = Property::where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->with([
                'units' => fn($q) => $q->whereNull('deleted_at'),
                'marketValues' => fn($q) => $q->orderByDesc('value_date'),
                'units.marketValues' => fn($q) => $q->orderByDesc('value_date'),
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
        $totalAcquisitionCost = 0;
        $totalBookValue       = 0;
        $totalMarketValue     = 0;

        // For standalone units: financials on the property itself
        foreach ($properties->where('nature', 'unit') as $p) {
            $totalAcquisitionCost += (float) $p->acquisition_cost;
            $totalBookValue       += (float) $p->book_value;
            $mv = $p->marketValues->first();
            $totalMarketValue += $mv ? (float) $mv->market_value : 0;
        }

        // For building/land/complex: financials on child units
        foreach ($properties->whereIn('nature', ['building', 'land', 'complex']) as $p) {
            foreach ($p->units as $u) {
                $totalAcquisitionCost += (float) $u->acquisition_cost;
                $totalBookValue       += (float) $u->book_value;
                $mv = $u->marketValues->first();
                $totalMarketValue += $mv ? (float) $mv->market_value : 0;
            }
        }

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
                    'status'        => $this->slotStatus($p->ownership, $p->delivery_date, $contract),
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
                        'status'        => $this->slotStatus($u->ownership ?? $p->ownership, $u->delivery_date ?? $p->delivery_date, $contract),
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
            'unrealized_gain'        => round($totalMarketValue - $totalBookValue, 2),
            'roi_if_sold'            => $totalAcquisitionCost > 0
                                          ? round(($totalMarketValue - $totalBookValue) / $totalAcquisitionCost * 100, 1)
                                          : null,
            'slots'                  => $leasableSlots->values(),
        ];
    }

    private function slotStatus(string $ownership, $deliveryDate, $contract): string
    {
        if ($contract) return 'occupied';
        if ($ownership === 'installments' && $deliveryDate && Carbon::parse($deliveryDate)->isFuture()) {
            return 'not_delivered';
        }
        return 'vacant';
    }

    // ═══════════════════════════════════════════════════════════════════
    // TAB 2 — CONTRACT ANALYSIS
    // ═══════════════════════════════════════════════════════════════════
    private function buildContracts(int $companyId, string $dateFrom, string $dateTo): array
    {
        $today = Carbon::today();

        $contracts = RentContract::where('company_id', $companyId)
            ->with([
                'customer:id,customer_name,tenant_nature',
                'propertyUnit:id,unit_name',
                'property:id,property_name,nature',
            ])
            ->get();

        $running    = $contracts->where('status', 'running');
        $expired    = $contracts->where('status', 'expired');
        $terminated = $contracts->where('status', 'terminated');

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
        $withIncrease    = $running->where('annual_increase_rate', '>', 0)->count();
        $avgIncreaseRate = $running->count() > 0
            ? round($running->avg('annual_increase_rate'), 2)
            : 0;

        // ── Monthly contracted rent trend (running contracts by start month) ─
        $monthlyTrend = RentRevenue::where('company_id', $companyId)
            ->whereBetween('revenue_date', [$dateFrom, $dateTo])
            ->selectRaw("DATE_FORMAT(revenue_date, '%Y-%m') as period, DATE_FORMAT(revenue_date, '%Y%m')+0 as sort_key, SUM(revenue_amount) as value")
            ->groupBy('period', 'sort_key')
            ->orderBy('sort_key')
            ->get()
            ->map(fn($r) => ['period' => $r->period, 'value' => (float) $r->value])
            ->values();

        return [
            'running_count'    => $running->count(),
            'expired_count'    => $expired->count(),
            'terminated_count' => $terminated->count(),
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
    private function buildRevenues(int $companyId, string $dateFrom, string $dateTo): array
    {
        // ── Total in period ───────────────────────────────────────────
        $baseQuery = RentRevenue::where('rent_revenues.company_id', $companyId)
            ->whereBetween('rent_revenues.revenue_date', [$dateFrom, $dateTo]);

        $totalRevenue = (clone $baseQuery)->sum('revenue_amount');

        // ── Monthly trend ─────────────────────────────────────────────
        $monthlyTrend = (clone $baseQuery)
            ->selectRaw("DATE_FORMAT(rent_revenues.revenue_date, '%Y-%m') as period, DATE_FORMAT(rent_revenues.revenue_date, '%Y%m')+0 as sort_key, SUM(rent_revenues.revenue_amount) as value")
            ->groupBy('period', 'sort_key')
            ->orderBy('sort_key')
            ->get()
            ->map(fn($r) => ['period' => $r->period, 'value' => (float) $r->value])
            ->values();

        // ── Forward 12 months ─────────────────────────────────────────
        $futureFrom = Carbon::today()->startOfMonth()->toDateString();
        $futureTo   = Carbon::today()->addMonths(11)->endOfMonth()->toDateString();

        $forwardRevenue = RentRevenue::where('rent_revenues.company_id', $companyId)
            ->whereBetween('rent_revenues.revenue_date', [$futureFrom, $futureTo])
            ->selectRaw("DATE_FORMAT(rent_revenues.revenue_date, '%Y-%m') as period, DATE_FORMAT(rent_revenues.revenue_date, '%Y%m')+0 as sort_key, SUM(rent_revenues.revenue_amount) as value")
            ->groupBy('period', 'sort_key')
            ->orderBy('sort_key')
            ->get()
            ->map(fn($r) => ['period' => $r->period, 'value' => (float) $r->value])
            ->values();

        // ── By revenue type (join contracts) ─────────────────────────
        $byRevenueType = (clone $baseQuery)
            ->join('rent_contracts', 'rent_revenues.rent_contract_id', '=', 'rent_contracts.id')
            ->selectRaw('rent_contracts.revenue_type, SUM(rent_revenues.revenue_amount) as value')
            ->groupBy('rent_contracts.revenue_type')
            ->get()
            ->map(fn($r) => ['label' => $r->revenue_type, 'value' => (float) $r->value])
            ->values();

        // ── By tenant nature ──────────────────────────────────────────
        $byTenantNature = (clone $baseQuery)
            ->join('rent_contracts', 'rent_revenues.rent_contract_id', '=', 'rent_contracts.id')
            ->selectRaw('rent_contracts.tenant_nature, SUM(rent_revenues.revenue_amount) as value')
            ->groupBy('rent_contracts.tenant_nature')
            ->get()
            ->map(fn($r) => ['label' => $r->tenant_nature, 'value' => (float) $r->value])
            ->values();

        // ── By property nature ────────────────────────────────────────
        $byPropertyNature = (clone $baseQuery)
            ->join('rent_contracts', 'rent_revenues.rent_contract_id', '=', 'rent_contracts.id')
            ->join('properties', 'rent_contracts.property_id', '=', 'properties.id')
            ->selectRaw('properties.nature, SUM(rent_revenues.revenue_amount) as value')
            ->groupBy('properties.nature')
            ->get()
            ->map(fn($r) => ['label' => $r->nature, 'value' => (float) $r->value])
            ->values();

        // ── Top properties by revenue ─────────────────────────────────
        $topProperties = (clone $baseQuery)
            ->join('rent_contracts', 'rent_revenues.rent_contract_id', '=', 'rent_contracts.id')
            ->join('properties', 'rent_contracts.property_id', '=', 'properties.id')
            ->selectRaw('properties.property_name, SUM(rent_revenues.revenue_amount) as value')
            ->groupBy('properties.id', 'properties.property_name')
            ->orderByDesc('value')
            ->limit(10)
            ->get()
            ->map(fn($r) => ['label' => $r->property_name, 'value' => (float) $r->value])
            ->values();

        return [
            'total_revenue'     => round($totalRevenue, 2),
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
    private function buildCollections(int $companyId, string $dateFrom, string $dateTo): array
    {
        $today = Carbon::today();

        $allCollections = RentCollection::where('company_id', $companyId)->get();

        // ── In-period collections ─────────────────────────────────────
        $inPeriod = $allCollections->filter(fn($c) =>
            $c->collection_date >= $dateFrom && $c->collection_date <= $dateTo
        );

        $totalDue       = round($inPeriod->sum('collection_amount'), 2);
        $totalCollected = round($inPeriod->where('status', 'collected')->sum('collection_amount'), 2);
        $totalPending   = round($inPeriod->where('status', 'pending')->sum('collection_amount'), 2);
        $totalOverdue   = round($inPeriod->where('status', 'overdue')->sum('collection_amount'), 2);
        $collectionRate = $totalDue > 0 ? round($totalCollected / $totalDue * 100, 1) : 0;

        // ── Forward 6 months ──────────────────────────────────────────
        $futureFrom = $today->copy()->startOfMonth()->toDateString();
        $futureTo   = $today->copy()->addMonths(5)->endOfMonth()->toDateString();

        $forwardCollections = $allCollections
            ->filter(fn($c) => $c->collection_date >= $futureFrom && $c->collection_date <= $futureTo)
            ->groupBy(fn($c) => Carbon::parse($c->collection_date)->format('Y-m'))
            ->map(fn($g, $period) => [
                'period'    => $period,
                'pending'   => round($g->where('status', 'pending')->sum('collection_amount'), 2),
                'collected' => round($g->where('status', 'collected')->sum('collection_amount'), 2),
                'total'     => round($g->sum('collection_amount'), 2),
            ])
            ->sortKeys()
            ->values();

        // ── Overdue aging ─────────────────────────────────────────────
        // Days overdue = today minus collection_date (past date → positive number).
        // Always call diffInDays FROM the past date TO today, not the reverse.
        $overdueItems = $allCollections->where('status', 'overdue');
        $aging = [
            '0_30'    => round($overdueItems->filter(fn($c) => Carbon::parse($c->collection_date)->diffInDays($today) <= 30)->sum('collection_amount'), 2),
            '31_60'   => round($overdueItems->filter(fn($c) => Carbon::parse($c->collection_date)->diffInDays($today) > 30 && Carbon::parse($c->collection_date)->diffInDays($today) <= 60)->sum('collection_amount'), 2),
            '61_90'   => round($overdueItems->filter(fn($c) => Carbon::parse($c->collection_date)->diffInDays($today) > 60 && Carbon::parse($c->collection_date)->diffInDays($today) <= 90)->sum('collection_amount'), 2),
            '90_plus' => round($overdueItems->filter(fn($c) => Carbon::parse($c->collection_date)->diffInDays($today) > 90)->sum('collection_amount'), 2),
        ];

        // ── Outstanding by tenant ─────────────────────────────────────
        $outstandingByTenant = RentCollection::where('rent_collections.company_id', $companyId)
            ->whereIn('rent_collections.status', ['pending', 'overdue'])
            ->join('rent_contracts', 'rent_collections.rent_contract_id', '=', 'rent_contracts.id')
            ->join('customers', 'rent_contracts.customer_id', '=', 'customers.id')
            ->selectRaw('customers.customer_name, SUM(rent_collections.collection_amount) as outstanding')
            ->groupBy('customers.id', 'customers.customer_name')
            ->orderByDesc('outstanding')
            ->limit(10)
            ->get()
            ->map(fn($r) => ['tenant' => $r->customer_name, 'outstanding' => (float) $r->outstanding])
            ->values();

        // ── Monthly trend (in period) ─────────────────────────────────
        $monthlyTrend = $inPeriod
            ->groupBy(fn($c) => Carbon::parse($c->collection_date)->format('Y-m'))
            ->map(fn($g, $period) => [
                'period'    => $period,
                'collected' => round($g->where('status', 'collected')->sum('collection_amount'), 2),
                'pending'   => round($g->where('status', 'pending')->sum('collection_amount'), 2),
                'overdue'   => round($g->where('status', 'overdue')->sum('collection_amount'), 2),
            ])
            ->sortKeys()
            ->values();

        return [
            'total_due'            => $totalDue,
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
    private function buildInstallments(int $companyId, string $dateFrom, string $dateTo): array
    {
        $today = Carbon::today();
        $allDues = \App\Models\PropertyInstallmentDue::where('company_id', $companyId)->get();

        $totalAmount  = round($allDues->sum('amount'), 2);
        $totalPaid    = round($allDues->where('status', 'paid')->sum('amount'), 2);
        $totalPending = round($allDues->where('status', 'pending')->sum('amount'), 2);
        $totalOverdue = round($allDues->where('status', 'overdue')->sum('amount'), 2);

        // ── Forward 6 months ──────────────────────────────────────────
        $futureFrom = $today->copy()->startOfMonth()->toDateString();
        $futureTo   = $today->copy()->addMonths(5)->endOfMonth()->toDateString();

        $forward = $allDues
            ->filter(fn($d) => $d->due_date >= $futureFrom && $d->due_date <= $futureTo && $d->status !== 'paid')
            ->groupBy(fn($d) => Carbon::parse($d->due_date)->format('Y-m'))
            ->map(fn($g, $period) => [
                'period' => $period,
                'amount' => round($g->sum('amount'), 2),
                'count'  => $g->count(),
            ])
            ->sortKeys()
            ->values();

        // ── By type ───────────────────────────────────────────────────
        $byType = $allDues->groupBy('due_type')->map(fn($g, $type) => [
            'type'    => $type,
            'total'   => round($g->sum('amount'), 2),
            'paid'    => round($g->where('status', 'paid')->sum('amount'), 2),
            'pending' => round($g->where('status', 'pending')->sum('amount'), 2),
            'overdue' => round($g->where('status', 'overdue')->sum('amount'), 2),
        ])->values();

        // ── Overdue aging ─────────────────────────────────────────────
        // Days overdue = today minus due_date (past date → positive number).
        // Always call diffInDays FROM the past date TO today, not the reverse.
        $overdueItems = $allDues->where('status', 'overdue');
        $aging = [
            '0_30'    => round($overdueItems->filter(fn($d) => Carbon::parse($d->due_date)->diffInDays($today) <= 30)->sum('amount'), 2),
            '31_60'   => round($overdueItems->filter(fn($d) => Carbon::parse($d->due_date)->diffInDays($today) > 30 && Carbon::parse($d->due_date)->diffInDays($today) <= 60)->sum('amount'), 2),
            '61_90'   => round($overdueItems->filter(fn($d) => Carbon::parse($d->due_date)->diffInDays($today) > 60 && Carbon::parse($d->due_date)->diffInDays($today) <= 90)->sum('amount'), 2),
            '90_plus' => round($overdueItems->filter(fn($d) => Carbon::parse($d->due_date)->diffInDays($today) > 90)->sum('amount'), 2),
        ];

        // ── Per property summary ──────────────────────────────────────
        $byProperty = \App\Models\PropertyInstallmentDue::where('property_installment_dues.company_id', $companyId)
            ->join('properties', 'property_installment_dues.property_id', '=', 'properties.id')
            ->selectRaw('properties.property_name, SUM(property_installment_dues.amount) as total, SUM(CASE WHEN property_installment_dues.status = "paid" THEN property_installment_dues.amount ELSE 0 END) as paid_amount, SUM(CASE WHEN property_installment_dues.status = "overdue" THEN property_installment_dues.amount ELSE 0 END) as overdue_amount')
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
    private function buildExpenses(int $companyId, string $dateFrom, string $dateTo): array
    {
        $expenses = PropertyExpense::where('company_id', $companyId)
            ->whereBetween('expense_date', [$dateFrom, $dateTo])
            ->with(['expenseCategory:id,category_name', 'expenseItem:id,item_name', 'payments'])
            ->get();

        $totalCommitted = round($expenses->sum('expense_amount'), 2);
        $totalPaid      = round($expenses->sum(fn($e) => $e->payments->sum('amount')), 2);
        $totalOutstanding = round($totalCommitted - $totalPaid, 2);

        // ── By category ───────────────────────────────────────────────
        $byCategory = $expenses->groupBy(fn($e) => $e->expenseCategory?->category_name ?? 'Uncategorized')
            ->map(fn($g, $cat) => [
                'label' => $cat,
                'value' => round($g->sum('expense_amount'), 2),
                'count' => $g->count(),
            ])
            ->sortByDesc('value')
            ->values();

        // ── By property ───────────────────────────────────────────────
        $byProperty = PropertyExpense::where('property_expenses.company_id', $companyId)
            ->whereBetween('property_expenses.expense_date', [$dateFrom, $dateTo])
            ->join('properties', 'property_expenses.property_id', '=', 'properties.id')
            ->selectRaw('properties.property_name, SUM(property_expenses.expense_amount) as value')
            ->groupBy('properties.id', 'properties.property_name')
            ->orderByDesc('value')
            ->get()
            ->map(fn($r) => ['label' => $r->property_name, 'value' => (float) $r->value])
            ->values();

        // ── Monthly trend ─────────────────────────────────────────────
        $monthlyTrend = PropertyExpense::where('company_id', $companyId)
            ->whereBetween('expense_date', [$dateFrom, $dateTo])
            ->selectRaw("DATE_FORMAT(expense_date, '%Y-%m') as period, DATE_FORMAT(expense_date, '%Y%m')+0 as sort_key, SUM(expense_amount) as value")
            ->groupBy('period', 'sort_key')
            ->orderBy('sort_key')
            ->get()
            ->map(fn($r) => ['period' => $r->period, 'value' => (float) $r->value])
            ->values();

        // ── Status breakdown ──────────────────────────────────────────
        $byStatus = $expenses->groupBy('status')->map(fn($g, $s) => [
            'status' => $s,
            'count'  => $g->count(),
            'amount' => round($g->sum('expense_amount'), 2),
        ])->values();

        return [
            'total_committed'  => $totalCommitted,
            'total_paid'       => $totalPaid,
            'total_outstanding'=> $totalOutstanding,
            'payment_rate'     => $totalCommitted > 0 ? round($totalPaid / $totalCommitted * 100, 1) : 0,
            'by_category'      => $byCategory,
            'by_property'      => $byProperty,
            'monthly_trend'    => $monthlyTrend,
            'by_status'        => $byStatus,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════
    // TAB 7 — PROFITABILITY
    // ═══════════════════════════════════════════════════════════════════
    private function buildProfitability(int $companyId, string $dateFrom, string $dateTo): array
    {
        // ── Total revenue in period ───────────────────────────────────
        $totalRevenue = (float) RentRevenue::where('company_id', $companyId)
            ->whereBetween('revenue_date', [$dateFrom, $dateTo])
            ->sum('revenue_amount');

        // ── Total expenses in period ──────────────────────────────────
        $totalExpenses = (float) PropertyExpense::where('company_id', $companyId)
            ->whereBetween('expense_date', [$dateFrom, $dateTo])
            ->sum('expense_amount');

        $noi           = round($totalRevenue - $totalExpenses, 2);
        $noiMargin     = $totalRevenue > 0 ? round($noi / $totalRevenue * 100, 1) : 0;

        // ── Per property P&L ──────────────────────────────────────────
        $revenueByProperty = RentRevenue::where('rent_revenues.company_id', $companyId)
            ->whereBetween('rent_revenues.revenue_date', [$dateFrom, $dateTo])
            ->join('rent_contracts', 'rent_revenues.rent_contract_id', '=', 'rent_contracts.id')
            ->join('properties', 'rent_contracts.property_id', '=', 'properties.id')
            ->selectRaw('properties.id, properties.property_name, properties.acquisition_cost, properties.book_value, SUM(rent_revenues.revenue_amount) as revenue')
            ->groupBy('properties.id', 'properties.property_name', 'properties.acquisition_cost', 'properties.book_value')
            ->get()
            ->keyBy('id');

        $expenseByProperty = PropertyExpense::where('company_id', $companyId)
            ->whereBetween('expense_date', [$dateFrom, $dateTo])
            ->selectRaw('property_id, SUM(expense_amount) as expenses')
            ->groupBy('property_id')
            ->get()
            ->keyBy('property_id');

        $allPropertyIds = $revenueByProperty->keys()->merge($expenseByProperty->keys())->unique();

        // Load all properties for acquisition cost even if no revenue yet
        $allProperties = \App\Models\Property::where('company_id', $companyId)
            ->whereIn('id', $allPropertyIds)
            ->get()
            ->keyBy('id');

        $perProperty = $allPropertyIds->map(function ($pid) use ($revenueByProperty, $expenseByProperty, $allProperties) {
            $rev  = (float) ($revenueByProperty[$pid]->revenue ?? 0);
            $exp  = (float) ($expenseByProperty[$pid]->expenses ?? 0);
            $noi  = round($rev - $exp, 2);
            $prop = $allProperties[$pid] ?? null;
            $acqCost = (float) ($revenueByProperty[$pid]->acquisition_cost ?? $prop?->acquisition_cost ?? 0);

            return [
                'property'        => $revenueByProperty[$pid]->property_name ?? ($prop?->property_name ?? 'Unknown'),
                'revenue'         => $rev,
                'expenses'        => $exp,
                'noi'             => $noi,
                'noi_margin'      => $rev > 0 ? round($noi / $rev * 100, 1) : 0,
                'acquisition_cost'=> $acqCost,
                'roi_pct'         => $acqCost > 0 ? round($noi / $acqCost * 100, 2) : null,
            ];
        })->sortByDesc('noi')->values();

        // ── Monthly NOI trend ─────────────────────────────────────────
        $monthlyRevenue = RentRevenue::where('company_id', $companyId)
            ->whereBetween('revenue_date', [$dateFrom, $dateTo])
            ->selectRaw("DATE_FORMAT(revenue_date, '%Y-%m') as period, DATE_FORMAT(revenue_date, '%Y%m')+0 as sort_key, SUM(revenue_amount) as value")
            ->groupBy('period', 'sort_key')
            ->orderBy('sort_key')
            ->get()
            ->keyBy('period');

        $monthlyExpenses = PropertyExpense::where('company_id', $companyId)
            ->whereBetween('expense_date', [$dateFrom, $dateTo])
            ->selectRaw("DATE_FORMAT(expense_date, '%Y-%m') as period, SUM(expense_amount) as value")
            ->groupBy('period')
            ->get()
            ->keyBy('period');

        $allPeriods = $monthlyRevenue->keys()->merge($monthlyExpenses->keys())->unique()->sort()->values();

        $monthlyNoi = $allPeriods->map(fn($period) => [
            'period'   => $period,
            'revenue'  => (float) ($monthlyRevenue[$period]->value ?? 0),
            'expenses' => (float) ($monthlyExpenses[$period]->value ?? 0),
            'noi'      => round((float)($monthlyRevenue[$period]->value ?? 0) - (float)($monthlyExpenses[$period]->value ?? 0), 2),
        ])->values();

        // ── Market value gain ─────────────────────────────────────────
        $latestMarketValues = \App\Models\PropertyMarketValue::where('company_id', $companyId)
            ->orderByDesc('value_date')
            ->get()
            ->groupBy('property_id')
            ->map->first();

        $totalMarketValue  = round($latestMarketValues->sum('market_value'), 2);
        $totalBookValue    = (float) \App\Models\Property::where('company_id', $companyId)->sum('book_value');
        $unrealizedGain    = round($totalMarketValue - $totalBookValue, 2);

        return [
            'total_revenue'    => round($totalRevenue, 2),
            'total_expenses'   => round($totalExpenses, 2),
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
    private function buildInsights(int $companyId, string $dateFrom, string $dateTo): array
    {
        $insights = [];
        $today    = Carbon::today();

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
            ->sum('collection_amount');

        if ($overdueCollections > 0) {
            $insights[] = [
                'type'  => 'danger',
                'icon'  => '🚨',
                'title' => 'Overdue Collections',
                'body'  => 'Total overdue rent collections: ' . number_format($overdueCollections, 0) . ' EGP. Follow up with tenants immediately.',
            ];
        }

        // ── Overdue installments ──────────────────────────────────────
        $overdueInstallments = \App\Models\PropertyInstallmentDue::where('company_id', $companyId)
            ->where('status', 'overdue')
            ->sum('amount');

        if ($overdueInstallments > 0) {
            $insights[] = [
                'type'  => 'danger',
                'icon'  => '💸',
                'title' => 'Overdue Installment Payments',
                'body'  => 'Outstanding overdue installments: ' . number_format($overdueInstallments, 0) . ' EGP. Contact developer/seller for status.',
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
            ->selectRaw("DATE_FORMAT(revenue_date, '%Y-%m') as period, DATE_FORMAT(revenue_date, '%Y%m')+0 as sort_key, SUM(revenue_amount) as value")
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
        $totalRevenue  = (float) RentRevenue::where('company_id', $companyId)->whereBetween('revenue_date', [$dateFrom, $dateTo])->sum('revenue_amount');
        $totalExpenses = (float) PropertyExpense::where('company_id', $companyId)->whereBetween('expense_date', [$dateFrom, $dateTo])->sum('expense_amount');

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
            ->sum('collection_amount');
        $totalDue = (float) RentCollection::where('company_id', $companyId)
            ->whereBetween('collection_date', [$dateFrom, $dateTo])
            ->sum('collection_amount');

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
        $properties = Property::where('company_id', $companyId)->whereNull('deleted_at')
            ->with(['units' => fn($q) => $q->whereNull('deleted_at')])->get();

        $runningContracts = RentContract::where('company_id', $companyId)->where('status', 'running')->get();
        $occupiedUnitIds  = $runningContracts->pluck('property_unit_id')->filter()->unique();
        $occupiedPropIds  = $runningContracts->where('property_unit_id', null)->pluck('property_id')->unique();

        $vacant = 0;
        foreach ($properties as $p) {
            if ($p->nature === 'unit') {
                if (!$occupiedPropIds->contains($p->id)) $vacant++;
            } else {
                foreach ($p->units as $u) {
                    if (!$occupiedUnitIds->contains($u->id)) $vacant++;
                }
            }
        }
        return $vacant;
    }

    // ═══════════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════════
    private function authorizeCompany(Company $company): void
    {
        $user = auth()->user();
        if (!$user->is_super_admin && $user->company_id !== $company->id) {
            abort(403);
        }
    }
}