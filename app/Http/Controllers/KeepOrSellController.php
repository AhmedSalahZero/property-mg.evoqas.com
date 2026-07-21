<?php

namespace App\Http\Controllers;

use App\Models\KeepOrSellAnalysis;
use App\Models\Property;
use App\Models\PropertyUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class KeepOrSellController extends Controller
{
    // ══════════════════════════════════════════════════════
    // INDEX — list all snapshots for this company
    // ══════════════════════════════════════════════════════
    public function index(Request $request, $companyId)
    {
        $this->authorizeCompanyId($companyId);

        $analyses = KeepOrSellAnalysis::where('company_id', $companyId)
            ->with(['property:id,property_name,nature', 'propertyUnit:id,unit_name', 'createdBy:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($a) => [
                'id'                  => $a->id,
                'snapshot_label'      => $a->snapshot_label,
                'property_name'       => $a->property->property_name ?? '—',
                'unit_name'           => $a->propertyUnit->unit_name ?? null,
                'auto_recommendation' => $a->auto_recommendation,
                'npv_hold'            => $a->npv_hold,
                'net_sale_proceeds'   => $a->net_sale_proceeds,
                'irr_hold'            => $a->irr_hold,
                'created_by_name'     => $a->createdBy->name ?? '—',
                'created_at'          => $a->created_at->format('d M Y'),
                'share_token'         => $a->share_token,
            ]);

        $properties = Property::where('company_id', $companyId)
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->with(['units' => fn($q) => $q->where('is_active', 1)->whereNull('deleted_at')
                ->select('id', 'property_id', 'unit_name', 'ownership')])
            ->select('id', 'property_name', 'nature', 'ownership')
            ->orderBy('property_name')
            ->get();

        return Inertia::render('Properties/KeepOrSell/Index', [
            'companyId'  => (int) $companyId,
            'analyses'   => $analyses,
            'properties' => $properties,
        ]);
    }

    // ══════════════════════════════════════════════════════
    // LOAD UNIT DATA — called when user selects a unit
    // ══════════════════════════════════════════════════════
    public function unitData(Request $request, $companyId)
    {
        $this->authorizeCompanyId($companyId);

        $propertyId = $request->property_id;
        $unitId     = $request->unit_id ?? null;

        // Market value — latest entry (base_amount now read directly off
        // the stored column — see propertyValuationConversion() fix below).
        $mvRow = DB::table('property_market_values')
            ->where('company_id', $companyId)
            ->where('property_id', $propertyId)
            ->when($unitId, fn($q) => $q->where('property_unit_id', $unitId))
            ->when(!$unitId, fn($q) => $q->whereNull('property_unit_id'))
            ->orderByDesc('value_date')
            ->first(['market_value', 'base_amount']);

        // Running rent contracts for this unit
        // Fix for audit M3 — previously had no explicit ordering, so if a
        // data-entry mistake ever left two "running" contracts on the same
        // slot, contracts[0] on the frontend (used as the baseline for every
        // future-year rent projection) was whatever order MySQL happened to
        // return, which isn't guaranteed to be stable. Ordering by the most
        // recently started contract first makes the choice deterministic and
        // matches what a user would expect as "the current contract."
        $contracts = DB::table('rent_contracts as rc')
            ->join('customers as c', 'c.id', '=', 'rc.customer_id')
            ->where('rc.company_id', $companyId)
            ->where('rc.property_id', $propertyId)
            ->where('rc.status', 'running')
            ->when($unitId, fn($q) => $q->where('rc.property_unit_id', $unitId))
            ->when(!$unitId, fn($q) => $q->whereNull('rc.property_unit_id'))
            ->select(
                'rc.id',
                'rc.start_date',
                'rc.end_date',
                'rc.monthly_rent_amount',
                'rc.min_monthly_rent',
                'rc.annual_increase_rate',
                'rc.contract_currency',
                'c.customer_name as tenant_name'
            )
            ->orderByDesc('rc.start_date')
            ->orderByDesc('rc.id')
            ->get();

        // Total contracted revenue from rent_revenues
        $revenueByYear = DB::table('rent_revenues as rr')
            ->join('rent_contracts as rc', 'rc.id', '=', 'rr.rent_contract_id')
            ->where('rr.company_id', $companyId)
            ->where('rc.property_id', $propertyId)
            ->when($unitId, fn($q) => $q->where('rc.property_unit_id', $unitId))
            ->when(!$unitId, fn($q) => $q->whereNull('rc.property_unit_id'))
            ->where('rc.status', 'running')
            ->select(
                DB::raw('YEAR(rr.revenue_date) as yr'),
                DB::raw('SUM(rr.base_amount) as total_revenue')
            )
            ->groupBy(DB::raw('YEAR(rr.revenue_date)'))
            ->orderBy(DB::raw('YEAR(rr.revenue_date)'))
            ->get();

        // Total paid expenses for this property/unit
        $expenseByYear = DB::table('property_expense_payments as pep')
            ->join('property_expenses as pe', 'pe.id', '=', 'pep.property_expense_id')
            ->where('pe.company_id', $companyId)
            ->where('pe.property_id', $propertyId)
            ->select(
                DB::raw('YEAR(pep.payment_date) as yr'),
                DB::raw('SUM(pep.base_amount) as total_expense')
            )
            ->groupBy(DB::raw('YEAR(pep.payment_date)'))
            ->orderBy(DB::raw('YEAR(pep.payment_date)'))
            ->get();

        // ── INSTALLMENT DUES — pending/overdue grouped by year ──────────
        // Only relevant when ownership = installments
        $property = DB::table('properties')->where('id', $propertyId)->first();
        $unit     = $unitId ? DB::table('property_units')->where('id', $unitId)->first() : null;

        $ownership = $unit ? ($unit->ownership ?? '') : ($property->ownership ?? '');

        $installmentByYear = [];
        $totalRemainingInstallments = 0;

        if ($ownership === 'installments') {
            $installmentRows = DB::table('property_installment_dues')
                ->where('company_id', $companyId)
                ->where('property_id', $propertyId)
                ->whereIn('status', ['pending', 'overdue'])
                ->select(
                    DB::raw('YEAR(due_date) as yr'),
                    DB::raw('SUM(base_amount) as total_due')
                )
                ->groupBy(DB::raw('YEAR(due_date)'))
                ->orderBy(DB::raw('YEAR(due_date)'))
                ->get();

            foreach ($installmentRows as $row) {
                $installmentByYear[] = ['yr' => $row->yr, 'total_due' => $row->total_due];
                $totalRemainingInstallments += (float) $row->total_due;
            }
        }

        $acquisitionCost         = (float) ($unit ? ($unit->acquisition_cost ?? 0) : ($property->acquisition_cost ?? 0));
        $acquisitionCostBase     = $unit ? $unit->acquisition_cost_base_amount : $property->acquisition_cost_base_amount;
        $marketValueRaw          = $mvRow?->market_value !== null ? (float) $mvRow->market_value : null;
        $marketValueBase         = $mvRow?->base_amount;
        $valuationCurrency       = strtoupper($unit ? ($unit->currency ?? 'EGP') : ($property->currency ?? 'EGP'));
        $baseCurrency            = strtoupper(\App\Models\Company::where('id', $companyId)->value('currency') ?: 'EGP');

        // Fix for audit Finding 1 (Keep-or-Sell currency mismatch), refined
        // by Findings 3/4 — every other figure returned by this endpoint
        // (revenue_by_year, expense_by_year, installment_by_year) is
        // already converted to the company's base currency via base_amount
        // (fix C4), but market_value/acquisition_cost used to be returned
        // raw in the property/unit's own currency. Since the compute()
        // engine treats every numeric input as already being in one common
        // currency, a foreign-currency property's market value was
        // silently mixed with base-currency revenue/expense figures in the
        // same NPV/IRR formula.
        //
        // These now come straight off the properties/property_units/
        // property_market_values.*_base_amount columns (migration
        // 2026_07_15_000001_add_base_currency_columns_to_property_valuation_tables),
        // computed once at write time by PropertyController — the exact
        // same stored values PropertyDashboardController::perPropertyFinancials()
        // reads for the Portfolio tab, so the two screens can never
        // disagree on a property's converted value again. If a rate wasn't
        // on file when the record was saved, the stored base_amount is
        // null and `valuation_fx_missing` is set so the frontend can warn
        // the analyst instead of silently proceeding with an unconverted
        // number — running `php artisan property:backfill-valuation-fx`
        // after adding the missing rate fills these in retroactively.
        $needsRate            = $valuationCurrency !== $baseCurrency;
        $marketValueConverted = $needsRate ? ($marketValueBase !== null ? (float) $marketValueBase : null) : $marketValueRaw;
        $acquisitionConverted = $needsRate ? ($acquisitionCostBase !== null ? (float) $acquisitionCostBase : null) : $acquisitionCost;
        $valuationFxMissing   = $needsRate && ($marketValueRaw !== null && $marketValueConverted === null
                                    || $acquisitionCost > 0 && $acquisitionConverted === null);

        return response()->json([
            // market_value / acquisition_cost are now in the company's base
            // currency, matching revenue_by_year / expense_by_year /
            // installment_by_year below — the compute() engine can add,
            // subtract, and discount all of these together safely.
            'market_value'                => $marketValueConverted,
            'acquisition_cost'            => $acquisitionConverted,
            // Original, unconverted figures + the source currency — kept so
            // the frontend can show the analyst what was actually on file
            // for this property ("Market Value: 5,000,000 EGP — converted
            // from 100,000 USD at rate 50.00").
            'market_value_original'       => $marketValueRaw,
            'acquisition_cost_original'   => $acquisitionCost,
            'valuation_currency'          => $valuationCurrency,
            'valuation_fx_missing'        => $valuationFxMissing,
            'contracts'                   => $contracts,
            // revenue_by_year / expense_by_year / installment_by_year ARE now
            // converted to the company's base currency (fix C4), so the
            // Keep-or-Sell projection engine never silently mixes currencies.
            'revenue_by_year'             => $revenueByYear,
            'expense_by_year'             => $expenseByYear,
            'installment_by_year'         => $installmentByYear,
            'total_remaining_installments'=> $totalRemainingInstallments,
            'ownership'                   => $ownership,
            'property_name'               => $property->property_name ?? '',
            'unit_name'                   => $unit->unit_name ?? null,
            'currency'                    => $baseCurrency,
            'base_currency'               => $baseCurrency,
        ]);
    }

    // ══════════════════════════════════════════════════════
    // COMPUTE — pure calculation, no DB save
    // ══════════════════════════════════════════════════════
    public function compute(Request $request, $companyId)
    {
        $this->authorizeCompanyId($companyId);

        $data = $request->validate([
            'property_id'              => 'required|integer',
            'property_unit_id'         => 'nullable|integer',
            'market_value'             => 'required|numeric|min:0',
            'selling_costs_pct'        => 'required|numeric|min:0|max:100',
            'holding_years'            => 'required|integer|min:1|max:30',
            'rent_growth_rate_pct'     => 'required|numeric|min:0|max:100',
            'other_opex_pct'           => 'required|numeric|min:0|max:100',
            'corporate_tax_rate_pct'   => 'required|numeric|min:0|max:100',
            'discount_rate_pct'        => 'required|numeric|min:0.01|max:100',
            // Exit value method: 'appreciation' | 'cap_rate' | 'higher_of'
            'exit_value_method'        => 'required|in:appreciation,cap_rate,higher_of',
            'appreciation_rate_pct'    => 'required_if:exit_value_method,appreciation,higher_of|nullable|numeric|min:0|max:100',
            'exit_cap_rate_pct'        => 'required_if:exit_value_method,cap_rate,higher_of|nullable|numeric|min:0.01|max:100',
            'contracted_revenues'      => 'present|array',
            'contracted_expenses'      => 'present|array',
            'installment_by_year'      => 'present|array', // [yr => amount]
            'last_contracted_rent'     => 'required|numeric|min:0',
        ]);

        $result = $this->runComputation($data);

        return response()->json($result);
    }

    // ══════════════════════════════════════════════════════
    // STORE — save a snapshot
    // ══════════════════════════════════════════════════════
    public function store(Request $request, $companyId)
    {
        $this->authorizeCompanyId($companyId);

        $data = $request->validate([
            'property_id'              => 'required|integer',
            'property_unit_id'         => 'nullable|integer',
            'snapshot_label'           => 'nullable|string|max:100',
            'market_value'             => 'required|numeric|min:0',
            'selling_costs_pct'        => 'required|numeric|min:0|max:100',
            'holding_years'            => 'required|integer|min:1|max:30',
            'rent_growth_rate_pct'     => 'required|numeric|min:0|max:100',
            'other_opex_pct'           => 'required|numeric|min:0|max:100',
            'corporate_tax_rate_pct'   => 'required|numeric|min:0|max:100',
            'discount_rate_pct'        => 'required|numeric|min:0.01|max:100',
            'exit_value_method'        => 'required|in:appreciation,cap_rate,higher_of',
            'appreciation_rate_pct'    => 'nullable|numeric|min:0|max:100',
            'exit_cap_rate_pct'        => 'nullable|numeric|min:0.01|max:100',
            'contracted_revenues'      => 'present|array',
            'contracted_expenses'      => 'present|array',
            'installment_by_year'      => 'present|array',
            'last_contracted_rent'     => 'required|numeric|min:0',
            'analyst_recommendation'   => 'nullable|string',
        ]);

        $computed = $this->runComputation($data);

        $analysis = KeepOrSellAnalysis::create([
            'company_id'             => $companyId,
            'created_by'             => auth()->id(),
            'property_id'            => $data['property_id'],
            'property_unit_id'       => $data['property_unit_id'] ?? null,
            'snapshot_label'         => $data['snapshot_label'] ?? null,
            'market_value'           => $data['market_value'],
            'selling_costs_pct'      => $data['selling_costs_pct'],
            'net_sale_proceeds'      => $computed['net_sale_proceeds'],
            'holding_years'          => $data['holding_years'],
            'rent_growth_rate_pct'   => $data['rent_growth_rate_pct'],
            'other_opex_pct'         => $data['other_opex_pct'],
            'corporate_tax_rate_pct' => $data['corporate_tax_rate_pct'],
            'discount_rate_pct'      => $data['discount_rate_pct'],
            // Store both new fields — exit_cap_rate_pct kept for backward compat
            'exit_cap_rate_pct'      => $data['exit_cap_rate_pct'] ?? null,
            'npv_hold'               => $computed['npv_hold'],
            'irr_hold'               => $computed['irr_hold'],
            'terminal_value'         => $computed['terminal_value'],
            'auto_recommendation'    => $computed['auto_recommendation'],
            'auto_flags'             => $computed['auto_flags'],
            'annual_cashflows'       => $computed['annual_cashflows'],
            'analyst_recommendation' => $data['analyst_recommendation'] ?? null,
        ]);

        return response()->json(['id' => $analysis->id, 'saved' => true]);
    }

    // ══════════════════════════════════════════════════════
    // UPDATE RECOMMENDATION — patch analyst text only
    // ══════════════════════════════════════════════════════
    public function updateRecommendation(Request $request, $companyId, $id)
    {
        $this->authorizeCompanyId($companyId);

        $analysis = KeepOrSellAnalysis::where('company_id', $companyId)->findOrFail($id);
        $analysis->update(['analyst_recommendation' => $request->analyst_recommendation]);
        return response()->json(['saved' => true]);
    }

    // ══════════════════════════════════════════════════════
    // DELETE
    // ══════════════════════════════════════════════════════
    public function destroy($companyId, $id)
    {
        $this->authorizeCompanyId($companyId);

        KeepOrSellAnalysis::where('company_id', $companyId)->findOrFail($id)->delete();
        return response()->json(['deleted' => true]);
    }

    // ══════════════════════════════════════════════════════
    // GENERATE SHARE TOKEN
    // ══════════════════════════════════════════════════════
    public function generateToken($companyId, $id)
    {
        $this->authorizeCompanyId($companyId);

        $analysis = KeepOrSellAnalysis::where('company_id', $companyId)->findOrFail($id);
        $token = Str::random(48);
        $analysis->update([
            'share_token'            => $token,
            'share_token_created_at' => now(),
        ]);
        return response()->json(['token' => $token]);
    }

    // ══════════════════════════════════════════════════════
    // PUBLIC SHARE VIEW — no auth required
    // ══════════════════════════════════════════════════════
    public function share($token)
    {
        $analysis = KeepOrSellAnalysis::where('share_token', $token)
            ->with(['property:id,property_name,nature', 'propertyUnit:id,unit_name', 'company:id,name,trade_name,currency'])
            ->firstOrFail();

        // Fix for audit finding C-3 — share links used to work forever once
        // generated, with no expiry check anywhere. Expire 90 days after the
        // token was (re)generated; regenerating the token via generateToken()
        // resets this clock. A stale token now 404s instead of staying valid
        // indefinitely.
        $expiresAfterDays = 90;
        if (
            $analysis->share_token_created_at === null
            || $analysis->share_token_created_at->lt(now()->subDays($expiresAfterDays))
        ) {
            abort(404);
        }

        return Inertia::render('Properties/KeepOrSell/Share', [
            'analysis' => $this->formatForShare($analysis),
        ]);
    }

    // ══════════════════════════════════════════════════════
    // SHOW — load a saved snapshot into the form
    // ══════════════════════════════════════════════════════
    public function show($companyId, $id)
    {
        $this->authorizeCompanyId($companyId);

        $analysis = KeepOrSellAnalysis::where('company_id', $companyId)
            ->with(['property:id,property_name,nature', 'propertyUnit:id,unit_name'])
            ->findOrFail($id);

        return response()->json($analysis);
    }

    // ══════════════════════════════════════════════════════
    // PRIVATE: CORE COMPUTATION ENGINE
    // ══════════════════════════════════════════════════════
    private function runComputation(array $data): array
    {
        $marketValue        = (float) $data['market_value'];
        $sellingCostsPct    = (float) $data['selling_costs_pct'] / 100;
        $holdingYears       = (int)   $data['holding_years'];
        $rentGrowthPct      = (float) $data['rent_growth_rate_pct'] / 100;
        $otherOpexPct       = (float) $data['other_opex_pct'] / 100;
        $taxRatePct         = (float) $data['corporate_tax_rate_pct'] / 100;
        $discountRate       = (float) $data['discount_rate_pct'] / 100;
        $exitValueMethod    = $data['exit_value_method'];                           // appreciation | cap_rate | higher_of
        $appreciationRate   = (float) ($data['appreciation_rate_pct'] ?? 0) / 100;
        $exitCapRate        = (float) ($data['exit_cap_rate_pct'] ?? 0) / 100;
        $contractedRevenues = $data['contracted_revenues'];   // [year(int) => amount]
        $contractedExpenses = $data['contracted_expenses'];   // [year(int) => amount]
        $installmentByYear  = $data['installment_by_year'];   // [year(int) => amount]  ← NEW
        $lastContractedRent = (float) $data['last_contracted_rent'];

        // ── Sell scenario ────────────────────────────────────
        $netSaleProceeds = $marketValue * (1 - $sellingCostsPct);

        // ── Build year-by-year cash flows ───────────────────
        $currentYear    = (int) date('Y');
        $annualCFs      = [];
        $lastRentAnnual = $lastContractedRent * 12;

        for ($y = 1; $y <= $holdingYears; $y++) {
            $calYear = $currentYear + $y - 1;

            // Revenue: use contracted if available, else grow from last contracted
            if (isset($contractedRevenues[$calYear]) && $contractedRevenues[$calYear] > 0) {
                $grossRevenue = (float) $contractedRevenues[$calYear];
            } else {
                $lastContractedYear = !empty($contractedRevenues) ? max(array_keys($contractedRevenues)) : ($currentYear - 1);
                $yearsAhead         = max(1, $calYear - $lastContractedYear);
                $grossRevenue       = $lastRentAnnual * pow(1 + $rentGrowthPct, $yearsAhead);
            }

            // Direct property expenses (from property_expense_payments)
            $directExpenses = (float) ($contractedExpenses[$calYear] ?? 0);

            // Other opex as % of gross revenue
            $otherOpex = $grossRevenue * $otherOpexPct;

            // ── Installment payments due this year ──────────
            // These are real cash outflows — the owner is still paying for the asset
            $installmentPayment = (float) ($installmentByYear[$calYear] ?? 0);

            // Net income before tax
            // Note: installments are capital payments (not opex), so tax is on revenue minus opex only
            $netBeforeTax = $grossRevenue - $directExpenses - $otherOpex;

            // Corporate tax (applied on positive net income only, before installments)
            $corporateTax = $netBeforeTax > 0 ? $netBeforeTax * $taxRatePct : 0;

            // True net cash flow = operating income after tax minus capital installment outflow
            $netCF = $netBeforeTax - $corporateTax - $installmentPayment;

            $annualCFs[] = [
                'year'                => $y,
                'cal_year'            => $calYear,
                'is_contracted'       => isset($contractedRevenues[$calYear]) && $contractedRevenues[$calYear] > 0,
                'gross_revenue'       => round($grossRevenue, 2),
                'direct_expenses'     => round($directExpenses, 2),
                'other_opex'          => round($otherOpex, 2),
                'corporate_tax'       => round($corporateTax, 2),
                'installment_payment' => round($installmentPayment, 2),   // ← NEW column
                'net_cf'              => round($netCF, 2),
            ];
        }

        // ── Terminal Value (Exit Value at end of holding period) ─────────
        //
        // Method A — Market Appreciation:
        //   Exit Value = Current Market Value × (1 + appreciation_rate)^holding_years
        //   This reflects what the asset is worth based on capital growth,
        //   regardless of the rent level. Correct for most real estate scenarios.
        //
        // Method B — Income Cap Rate:
        //   Exit Value = Last Year NOI / Cap Rate
        //   This reflects what an investor would pay for the income stream.
        //   Only accurate when rent is at or near market rate.
        //
        // Method C — Higher of Both:
        //   System picks whichever is greater — conservative protection.

        $lastYear = end($annualCFs);
        $lastNOI  = $lastYear['gross_revenue'] - $lastYear['direct_expenses'] - $lastYear['other_opex'];

        $tvAppreciation = $marketValue * pow(1 + $appreciationRate, $holdingYears);
        $tvCapRate      = ($exitCapRate > 0) ? ($lastNOI / $exitCapRate) : 0;

        if ($exitValueMethod === 'appreciation') {
            $terminalValue     = $tvAppreciation;
            $terminalValueNote = 'market_appreciation';
        } elseif ($exitValueMethod === 'cap_rate') {
            $terminalValue     = $tvCapRate;
            $terminalValueNote = 'cap_rate';
        } else {
            // higher_of
            $terminalValue     = max($tvAppreciation, $tvCapRate);
            $terminalValueNote = $tvAppreciation >= $tvCapRate ? 'market_appreciation' : 'cap_rate';
        }

        // ── NPV of Hold ──────────────────────────────────────
        $npv = 0;
        foreach ($annualCFs as $cf) {
            $npv += $cf['net_cf'] / pow(1 + $discountRate, $cf['year']);
        }
        // Add discounted terminal value
        $npv += $terminalValue / pow(1 + $discountRate, $holdingYears);

        // ── IRR via bisection method ─────────────────────────
        $irr = $this->computeIRR($annualCFs, $terminalValue, $holdingYears);

        // ── Auto Recommendation ──────────────────────────────
        $gap   = $npv - $netSaleProceeds;
        $flags = [];

        if ($npv > $netSaleProceeds * 1.10) {
            $recommendation = 'keep';
        } elseif ($npv < $netSaleProceeds * 0.90) {
            $recommendation = 'sell';
        } else {
            $recommendation = 'neutral';
        }

        // IRR vs discount rate check
        if ($irr !== null && $irr < (float) $data['discount_rate_pct']) {
            $flags[] = 'IRR (' . round($irr, 1) . '%) is below your required return (' . $data['discount_rate_pct'] . '%) — holding may destroy value on a risk-adjusted basis.';
        }

        // No active contract warning
        if (empty($contractedRevenues) || max($contractedRevenues) == 0) {
            $flags[] = 'No active rent contract found. Revenue projection is fully based on growth assumptions.';
        }

        // High opex ratio
        if ($otherOpexPct > 0.40) {
            $flags[] = 'Other operating costs exceed 40% of revenue — high-cost asset profile.';
        }

        // Installment burden warning
        $totalInstallments = array_sum(array_column($annualCFs, 'installment_payment'));
        if ($totalInstallments > 0) {
            $firstYearRevenue = $annualCFs[0]['gross_revenue'] ?? 0;
            $firstYearInst    = $annualCFs[0]['installment_payment'] ?? 0;
            if ($firstYearRevenue > 0 && $firstYearInst / $firstYearRevenue > 0.5) {
                $flags[] = 'Installment payments exceed 50% of annual revenue in Year 1 — significant cash drain during holding period.';
            }
            $flags[] = 'Total remaining installment payments over the holding period: ' . number_format($totalInstallments, 0) . '. These are deducted from net cash flow each year.';
        }

        // NPV gap note
        if ($recommendation === 'neutral') {
            $flags[] = 'NPV of Hold and Net Sale Proceeds are within 10% of each other — decision is sensitive to assumptions.';
        }

        return [
            'net_sale_proceeds'    => round($netSaleProceeds, 2),
            'terminal_value'       => round($terminalValue, 2),
            'terminal_value_note'  => $terminalValueNote,   // tells Vue which method produced the TV
            'tv_appreciation'      => round($tvAppreciation, 2),
            'tv_cap_rate'          => round($tvCapRate, 2),
            'npv_hold'             => round($npv, 2),
            'irr_hold'             => $irr !== null ? round($irr, 4) : null,
            'auto_recommendation'  => $recommendation,
            'auto_flags'           => $flags,
            'annual_cashflows'     => $annualCFs,
            'npv_gap'              => round($gap, 2),
            'total_installments'   => round($totalInstallments, 2),
        ];
    }

    // ══════════════════════════════════════════════════════
    // IRR via bisection
    // ══════════════════════════════════════════════════════
    private function computeIRR(array $annualCFs, float $terminalValue, int $holdingYears): ?float
    {
        $npvAt = function (float $rate) use ($annualCFs, $terminalValue, $holdingYears): float {
            if ($rate <= -1) return PHP_FLOAT_MAX;
            $npv = 0;
            foreach ($annualCFs as $cf) {
                $npv += $cf['net_cf'] / pow(1 + $rate, $cf['year']);
            }
            $npv += $terminalValue / pow(1 + $rate, $holdingYears);
            return $npv;
        };

        $lo = -0.99;
        $hi = 5.0;

        if ($npvAt($lo) * $npvAt($hi) > 0) {
            return null;
        }

        for ($i = 0; $i < 100; $i++) {
            $mid = ($lo + $hi) / 2;
            if (abs($hi - $lo) < 0.0000001) break;
            if ($npvAt($mid) * $npvAt($lo) <= 0) {
                $hi = $mid;
            } else {
                $lo = $mid;
            }
        }

        $irr = ($lo + $hi) / 2 * 100;
        return abs($irr) > 500 ? null : round($irr, 4);
    }

    private function formatForShare(KeepOrSellAnalysis $a): array
    {
        return [
            'id'                     => $a->id,
            'snapshot_label'         => $a->snapshot_label,
            'company_name'           => $a->company->trade_name ?? $a->company->name,
            'currency'               => $a->company->currency ?? 'EGP',
            'property_name'          => $a->property->property_name ?? '—',
            'unit_name'              => $a->propertyUnit->unit_name ?? null,
            'market_value'           => $a->market_value,
            'selling_costs_pct'      => $a->selling_costs_pct,
            'net_sale_proceeds'      => $a->net_sale_proceeds,
            'holding_years'          => $a->holding_years,
            'rent_growth_rate_pct'   => $a->rent_growth_rate_pct,
            'other_opex_pct'         => $a->other_opex_pct,
            'corporate_tax_rate_pct' => $a->corporate_tax_rate_pct,
            'discount_rate_pct'      => $a->discount_rate_pct,
            'exit_cap_rate_pct'      => $a->exit_cap_rate_pct,
            'npv_hold'               => $a->npv_hold,
            'irr_hold'               => $a->irr_hold,
            'terminal_value'         => $a->terminal_value,
            'auto_recommendation'    => $a->auto_recommendation,
            'auto_flags'             => $a->auto_flags ?? [],
            'annual_cashflows'       => $a->annual_cashflows ?? [],
            'analyst_recommendation' => $a->analyst_recommendation,
            'created_at'             => $a->created_at->format('d M Y'),
        ];
    }

    // ══════════════════════════════════════════════════════
    // Fix for audit finding C-1 — this controller previously had NO check
    // anywhere that the logged-in user actually belongs to the $companyId
    // taken straight from the URL, unlike every other company-scoped
    // controller in the app. Any authenticated user could view, edit,
    // delete, or mint a public share link for another company's
    // confidential Keep-or-Sell investment analysis just by editing the
    // company ID in the address bar. Deliberately takes a plain
    // int/string (not a route-bound Company model, since this controller's
    // methods use $companyId as a raw scalar throughout) so it can be
    // called the same way in every method below. NOT called from share()
    // — that endpoint is intentionally public/token-based (see the C-3
    // expiry fix on that method instead).
    // ══════════════════════════════════════════════════════
    private function authorizeCompanyId($companyId): void
    {
        $user = auth()->user();
        if (! $user->is_super_admin && (int) $user->company_id !== (int) $companyId) {
            abort(403);
        }
    }
}