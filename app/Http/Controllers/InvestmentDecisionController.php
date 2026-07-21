<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesCompany;
use App\Models\Company;
use App\Models\InvestmentAnalysis;
use App\Models\InvestmentProspect;
use App\Models\InvestmentProspectUnit;
use App\Models\Property;
use App\Models\PropertyCategory;
use App\Models\PropertyUnit;
use App\Models\RentContract;
use App\Services\AcquisitionFeasibilityEngine;
use App\Services\CurrencyConversionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Investment Decision Tool — "Buy or Not Buy".
 *
 * Deliberately separate from PropertyController/KeepOrSellController — a
 * prospect is a candidate acquisition that isn't in `properties` yet
 * (confirmed scope, July 2026 planning session). This controller owns the
 * lightweight prospect CRUD plus the scenario-comparison compute endpoint;
 * all the actual financial modeling lives in AcquisitionFeasibilityEngine.
 *
 * Revision (July 2026): a prospect can now be a single Unit OR a
 * Building/Land/Complex made of several units — confirmed RAM evaluates
 * both. Mirrors how PropertyController handles the same split for real
 * properties: nature='unit' keeps price/rent/category directly on the
 * prospect; nature='building'|'land'|'complex' moves those to child
 * `investment_prospect_units` rows, and the prospect's totals become the
 * SUM of its units (see InvestmentProspect::totalPurchasePrice()/
 * totalExpectedMonthlyRent()).
 */
class InvestmentDecisionController extends Controller
{
    use AuthorizesCompany;

    public function index(Company $company)
    {
        $this->authorizeCompany($company);

        $prospects = InvestmentProspect::where('company_id', $company->id)
            ->with(['propertyCategory:id,category_name', 'propertyType:id,type_name', 'units'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($p) {
                $p->total_purchase_price = $p->totalPurchasePrice();
                $p->total_expected_monthly_rent = $p->totalExpectedMonthlyRent();
                $p->unit_count = $p->unitCount();
                return $p;
            });

        return Inertia::render('Properties/InvestmentDecision/Prospects/Index', [
            'company'      => $company,
            'prospects'    => $prospects,
            'statusLabels' => InvestmentProspect::statusLabels(),
            'natureLabels' => InvestmentProspect::natureLabels(),
        ]);
    }

    public function create(Company $company)
    {
        $this->authorizeCompany($company);

        return Inertia::render('Properties/InvestmentDecision/Prospects/Create', [
            'company'         => $company,
            'categories'      => $this->categoriesWithTypes($company),
            'natureLabels'    => InvestmentProspect::natureLabels(),
            'currencyOptions' => $this->currencyOptions(),
        ]);
    }

    public function store(Request $request, Company $company)
    {
        $this->authorizeCompany($company);

        $data = $this->validated($request);
        $units = $data['units'] ?? [];
        unset($data['units']);

        $data['company_id'] = $company->id;
        $data['created_by'] = $request->user()->id;

        // Single-unit fields don't apply to a multi-unit prospect — keep
        // them null rather than storing stale values that would confuse
        // totalPurchasePrice()/totalExpectedMonthlyRent() later.
        if ($data['nature'] !== InvestmentProspect::NATURE_UNIT) {
            $data['property_category_id']  = null;
            $data['property_type_id']      = null;
            $data['area']                  = null;
            $data['unit_of_measurement']   = null;
            $data['purchase_price']        = null;
            $data['expected_monthly_rent'] = null;
        }

        $prospect = InvestmentProspect::create($data);

        if ($data['nature'] !== InvestmentProspect::NATURE_UNIT) {
            $this->syncUnits($company, $prospect, $units);
        }

        return redirect()
            ->route('company.properties.investment-decision.workspace', [$company->id, $prospect->id])
            ->with('success', 'Prospect "' . $prospect->prospect_name . '" created. Set your assumptions below and compute.');
    }

    public function edit(Company $company, InvestmentProspect $prospect)
    {
        $this->authorizeCompany($company);
        abort_unless($prospect->company_id === $company->id, 404);

        $prospect->load('units');

        return Inertia::render('Properties/InvestmentDecision/Prospects/Edit', [
            'company'         => $company,
            'prospect'        => $prospect,
            'categories'      => $this->categoriesWithTypes($company),
            'natureLabels'    => InvestmentProspect::natureLabels(),
            'currencyOptions' => $this->currencyOptions(),
        ]);
    }

    public function update(Request $request, Company $company, InvestmentProspect $prospect)
    {
        $this->authorizeCompany($company);
        abort_unless($prospect->company_id === $company->id, 404);

        $data = $this->validated($request);
        $units = $data['units'] ?? [];
        unset($data['units']);

        if ($data['nature'] !== InvestmentProspect::NATURE_UNIT) {
            $data['property_category_id']  = null;
            $data['property_type_id']      = null;
            $data['area']                  = null;
            $data['unit_of_measurement']   = null;
            $data['purchase_price']        = null;
            $data['expected_monthly_rent'] = null;
        }

        $prospect->update($data);

        // Units are hard-replaced (delete + reinsert) on every save — this
        // is a draft feasibility record, not a ledger with history to
        // protect (unlike a real Property's installment dues), so there's
        // no reconciliation complexity needed here.
        $prospect->units()->delete();
        if ($data['nature'] !== InvestmentProspect::NATURE_UNIT) {
            $this->syncUnits($company, $prospect, $units);
        }

        return redirect()
            ->route('company.properties.investment-decision.index', $company->id)
            ->with('success', 'Prospect "' . $prospect->prospect_name . '" updated.');
    }

    public function destroy(Company $company, InvestmentProspect $prospect)
    {
        $this->authorizeCompany($company);
        abort_unless($prospect->company_id === $company->id, 404);

        // Hard delete — this is a planning tool, not an accounting ledger
        // (same rule already applied to installment dues elsewhere in the app).
        // Units cascade-delete via the FK.
        $prospect->delete();

        return response()->json(['message' => 'Prospect removed.']);
    }

    /**
     * Confirmed design decision (July 2026 session): the status is a
     * conclusion reached AFTER running the feasibility numbers, not a
     * guess made when the prospect is first entered — so it's set here,
     * from the workspace, not on the create/edit form. Every prospect
     * still starts life as 'evaluating' automatically at creation; this
     * is the only place that ever changes it. Purely a label for the
     * user's own tracking — nothing else in the app reacts to it, and
     * changing it never touches `properties` or any other table.
     */
    public function updateStatus(Request $request, Company $company, InvestmentProspect $prospect)
    {
        $this->authorizeCompany($company);
        abort_unless($prospect->company_id === $company->id, 404);

        $data = $request->validate([
            'status' => 'required|in:evaluating,pursuing,passed,acquired',
        ]);

        $prospect->update($data);

        return response()->json(['message' => 'Status updated.', 'prospect' => $prospect->fresh()]);
    }

    // ══════════════════════════════════════════════════════
    // WORKSPACE — assumptions + funding path + side-by-side compute
    // ══════════════════════════════════════════════════════
    public function workspace(Company $company, InvestmentProspect $prospect)
    {
        $this->authorizeCompany($company);
        abort_unless($prospect->company_id === $company->id, 404);

        $prospect->load(['units.propertyCategory:id,category_name', 'units.propertyType:id,type_name']);
        $prospect->total_purchase_price = $prospect->totalPurchasePrice();
        $prospect->total_expected_monthly_rent = $prospect->totalExpectedMonthlyRent();
        $prospect->unit_count = $prospect->unitCount();
        $prospect->is_multi_unit = $prospect->isMultiUnit();

        return Inertia::render('Properties/InvestmentDecision/Workspace', [
            'company'          => $company,
            'prospect'         => $prospect,
            'baseCurrency'     => strtoupper($company->currency ?: 'EGP'),
            'statusLabels'     => InvestmentProspect::statusLabels(),
            'scenarioDefaults' => AcquisitionFeasibilityEngine::scenarioDefaults(),
            // Sensible shared-assumption defaults — same numbers Keep-or-Sell
            // ships with, kept here rather than hardcoded in Vue so both
            // tools can be tuned from one place later if needed.
            'sharedDefaults'   => [
                'exit_year'                => 8,
                'discount_rate_pct'        => 12.0,
                'corporate_tax_rate_pct'   => 22.5,
                'selling_costs_pct'        => 5.0,
                'exit_value_method'        => 'higher_of',
                // Confirmed July 2026 — Egyptian commercial leases are
                // collected in advance (see the VERO logic reference), and
                // collection frequency has a genuine, provable effect on
                // NPV (hand-verified against a spreadsheet: quarterly and
                // semi-annual in advance both score HIGHER than monthly for
                // the same annual rent, since bigger lump sums land
                // earlier). Monthly is the default since it's the most
                // common interval in your existing Rent Contracts.
                'rent_collection_interval' => 'monthly',
                // Phase 3 — Company Cash Flow Impact projection, once your
                // portfolio's own scheduled data runs out. Confirmed
                // default 10%/yr (July 2026), always user-overridable.
                'inflation_rate_pct'       => 10.0,
            ],
            'bankLoanDefaults' => [
                'down_payment_pct'     => 20.0,
                'annual_rate'          => 18.0,
                'term_months'          => 120,
                'installment_interval' => 'monthly',
                'schedule_type'        => 'normal',
                'grace_months'         => 0,
            ],
        ]);
    }

    /**
     * Runs Conservative/Base/Optimistic together for the chosen funding
     * path and returns them side by side. Pure calculation — nothing is
     * persisted in Phase 1 (saved snapshots are Phase 4, matching
     * Keep-or-Sell's compute()/store() split).
     */
    public function compute(Request $request, Company $company, InvestmentProspect $prospect)
    {
        $this->authorizeCompany($company);
        abort_unless($prospect->company_id === $company->id, 404);

        $data = $request->validate($this->computeValidationRules());

        $outcome = $this->runFullComputation($company, $prospect, $data);
        if (isset($outcome['error'])) {
            return response()->json(['message' => $outcome['error']], 422);
        }

        return response()->json($outcome);
    }

    /**
     * Fix for Phase 4 (July 2026) — Save Snapshot used to risk silently
     * drifting from what Compute actually shows on screen if the two ever
     * ran slightly different logic. There is now exactly ONE place this
     * computation happens — compute() and storeAnalysis() both call this,
     * so a saved snapshot is always guaranteed to be a faithful, re-verified
     * copy of a real computation, never something trusted from the client.
     *
     * @return array{error: string}|array{base_currency: string, purchase_price_base: float, expected_rent_base: float, is_multi_unit: bool, result: array, portfolio_impact: array, cash_flow_impact: array}
     */
    private function runFullComputation(Company $company, InvestmentProspect $prospect, array $data): array
    {
        $scenarioKeys = array_keys($data['scenarios']);
        $unknown = array_diff($scenarioKeys, AcquisitionFeasibilityEngine::SCENARIOS);
        if (!empty($unknown)) {
            return ['error' => 'Unknown scenario key(s): ' . implode(', ', $unknown)];
        }

        $baseCurrency = strtoupper($company->currency ?: 'EGP');
        $isMultiUnit  = $prospect->isMultiUnit();

        // Fix-for-Finding-1-class-bug discipline: convert the prospect's own
        // figures into the company base currency BEFORE they reach the
        // engine, exactly like every other feasibility tool in this app —
        // never let two different currencies reach the same NPV formula.
        // A prospect has no transaction date of its own (it doesn't exist
        // yet), so this uses the same "latest rate on file, live view"
        // approach Keep-or-Sell uses for market_value/acquisition_cost.
        //
        // For a multi-unit prospect, the totals below are already the SUM
        // across all child units (InvestmentProspect::totalPurchasePrice()/
        // totalExpectedMonthlyRent()) — every unit is assumed to share the
        // prospect's own currency (same simplifying assumption a real
        // multi-unit Property makes; mixed-currency units aren't supported).
        if ($isMultiUnit) {
            $prospect->load('units');
        }
        $prospectCurrency    = strtoupper($prospect->currency ?: 'EGP');
        $purchasePrice       = $prospect->totalPurchasePrice();
        $expectedMonthlyRent = $prospect->totalExpectedMonthlyRent();
        $fxMissing = false;

        if ($prospectCurrency !== $baseCurrency) {
            $fx   = app(CurrencyConversionService::class);
            $rate = $fx->latestRate($company->id, $prospectCurrency);
            if ($rate === null) {
                $fxMissing = true;
            } else {
                $purchasePrice       = round($purchasePrice * $rate, 2);
                $expectedMonthlyRent = round($expectedMonthlyRent * $rate, 2);
            }
        }

        if ($fxMissing) {
            return ['error' => "This prospect is priced in {$prospectCurrency} and no exchange rate to {$baseCurrency} is on file in Statistica. Add the rate first, or edit the prospect to price it in {$baseCurrency} directly."];
        }

        if ($purchasePrice <= 0) {
            return ['error' => $isMultiUnit
                ? 'This prospect has no units with a purchase price yet — add at least one unit before computing.'
                : 'This prospect has no purchase price set.'];
        }

        $engine = new AcquisitionFeasibilityEngine();

        $result = $engine->compareScenarios(
            prospect: [
                'purchase_price'        => $purchasePrice,
                'expected_monthly_rent' => $expectedMonthlyRent,
                'is_multi_unit'         => $isMultiUnit,
            ],
            shared: [
                'exit_year'                => $data['exit_year'],
                'discount_rate_pct'        => $data['discount_rate_pct'],
                'corporate_tax_rate_pct'   => $data['corporate_tax_rate_pct'],
                'selling_costs_pct'        => $data['selling_costs_pct'],
                'exit_value_method'        => $data['exit_value_method'],
                'rent_collection_interval' => $data['rent_collection_interval'],
            ],
            scenarioInputs: $data['scenarios'],
            fundingPath: $data['funding_path'],
            fundingParams: array_merge($data['funding_params'] ?? [], [
                'disbursement_date' => now()->toDateString(),
            ])
        );

        // Phase 3 — both compared against the Base Case scenario only.
        // Showing three separate portfolio/cash-flow comparisons (one per
        // scenario) would be a lot of visual noise for what's meant to be
        // a quick sanity check, not the main event — confirmed approach,
        // July 2026 planning session.
        $portfolioImpact = $this->portfolioImpactSummary($company, $baseCurrency, $purchasePrice, $result['scenarios']['base']);
        $cashFlowImpact  = $this->companyCashFlowImpact($company, $baseCurrency, $data['exit_year'], (float) ($data['inflation_rate_pct'] ?? 10.0), $result['scenarios']['base']);

        return [
            'base_currency'         => $baseCurrency,
            'purchase_price_base'   => $purchasePrice,
            'expected_rent_base'    => $expectedMonthlyRent,
            'is_multi_unit'         => $isMultiUnit,
            'result'                => $result,
            'portfolio_impact'      => $portfolioImpact,
            'cash_flow_impact'      => $cashFlowImpact,
        ];
    }

    /**
     * Shared validation rules for compute() and storeAnalysis() — same
     * "one place, never diverge" discipline as runFullComputation() above.
     */
    private function computeValidationRules(): array
    {
        return [
            // Widened from the original 7-10 spec to 3-10 per your request
            // (July 2026) — confirmed the engine has no hidden assumption
            // tied to a 7-year minimum hold; the annual cash flow loop,
            // NPV, and IRR all work for any exit year >= 1.
            'exit_year'                  => 'required|integer|min:3|max:10',
            'discount_rate_pct'          => 'required|numeric|min:0|max:100',
            'corporate_tax_rate_pct'     => 'required|numeric|min:0|max:100',
            'selling_costs_pct'          => 'required|numeric|min:0|max:100',
            'exit_value_method'          => 'required|in:appreciation,cap_rate,higher_of',
            'rent_collection_interval'   => 'required|in:monthly,quarterly,semi_annually,annually',
            // Phase 3 — Company Cash Flow Impact: once the portfolio's own
            // scheduled data (existing rent, installments, expenses) runs
            // out, the remaining years out to the exit year are projected
            // forward from the last known year, compounding at this rate.
            // Confirmed default 10%/yr (July 2026), fully overridable.
            'inflation_rate_pct'         => 'nullable|numeric|min:-50|max:100',

            'scenarios'                              => 'required|array',
            'scenarios.*.rent_growth_rate_pct'       => 'required|numeric|min:-100|max:100',
            'scenarios.*.months_vacant'              => 'required|integer|min:0|max:60',
            'scenarios.*.occupancy_ramp_months'      => 'required|integer|min:0|max:60',
            'scenarios.*.occupancy_start_pct'        => 'required|numeric|min:0|max:100',
            'scenarios.*.appreciation_rate_pct'      => 'required|numeric|min:-100|max:100',
            'scenarios.*.exit_cap_rate_pct'          => 'required|numeric|min:0.01|max:100',
            'scenarios.*.other_opex_pct'             => 'required|numeric|min:0|max:100',

            'funding_path'                => 'required|in:cash_purchase,bank_loan,seller_installments,custom_schedule,contractor_deal',
            'funding_params'              => 'nullable|array',
            'funding_params.down_payment_pct'     => 'required_if:funding_path,bank_loan|numeric|min:0|max:100',
            'funding_params.annual_rate'          => 'required_if:funding_path,bank_loan|numeric|min:0|max:100',
            'funding_params.term_months'          => 'required_if:funding_path,bank_loan|integer|min:1|max:360',
            'funding_params.grace_months'         => 'nullable|integer|min:0|max:60',

            // Seller / Developer Installments — same shape as a real
            // Regular-Mode installment plan (PropertyInstallmentController),
            // generated by the same shared InstallmentScheduleGenerator.
            'funding_params.regular_plan'                          => 'required_if:funding_path,seller_installments|array',
            'funding_params.regular_plan.signing_amount'           => 'nullable|numeric|min:0',
            'funding_params.regular_plan.signing_date'             => 'nullable|string|max:7',
            'funding_params.regular_plan.reservation_amount'       => 'nullable|numeric|min:0',
            'funding_params.regular_plan.reservation_date'         => 'nullable|string|max:7',
            'funding_params.regular_plan.installment_rows'                     => 'nullable|array',
            'funding_params.regular_plan.installment_rows.*.amount'            => 'nullable|numeric|min:0',
            'funding_params.regular_plan.installment_rows.*.count'             => 'nullable|integer|min:1',
            'funding_params.regular_plan.installment_rows.*.start_date'        => 'nullable|string|max:7',
            'funding_params.regular_plan.installment_rows.*.interval'          => 'nullable|in:monthly,quarterly,semi_annually',
            'funding_params.regular_plan.has_annual'               => 'nullable|boolean',
            'funding_params.regular_plan.annual_start_date'        => 'nullable|string|max:7',
            'funding_params.regular_plan.annual_amount'            => 'nullable|numeric|min:0',
            'funding_params.regular_plan.annual_count'             => 'nullable|integer|min:1',
            'funding_params.regular_plan.has_delivery'             => 'nullable|boolean',
            'funding_params.regular_plan.delivery_start_date'      => 'nullable|string|max:7',
            'funding_params.regular_plan.delivery_amount'          => 'nullable|numeric|min:0',
            'funding_params.regular_plan.delivery_count'           => 'nullable|integer|min:1',
            'funding_params.regular_plan.delivery_interval'        => 'nullable|in:monthly,quarterly,semi_annually',
            'funding_params.regular_plan.has_maintenance'          => 'nullable|boolean',
            'funding_params.regular_plan.maintenance_start_date'   => 'nullable|string|max:7',
            'funding_params.regular_plan.maintenance_amount'       => 'nullable|numeric|min:0',
            'funding_params.regular_plan.maintenance_count'        => 'nullable|integer|min:1',
            'funding_params.regular_plan.maintenance_interval'     => 'nullable|in:monthly,quarterly,semi_annually',

            // Custom Payment Schedule, and Contractor Deal's own RAM-funded
            // construction draws — same free-form shape (confirmed reuse,
            // July 2026 planning session).
            'funding_params.custom_rows'                => 'required_if:funding_path,custom_schedule,contractor_deal|array',
            'funding_params.custom_rows.*.date'          => 'required_with:funding_params.custom_rows|date',
            'funding_params.custom_rows.*.amount'        => 'required_with:funding_params.custom_rows|numeric|min:0.01',
            'funding_params.custom_rows.*.notes'         => 'nullable|string|max:255',

            // Contractor Development Deal — confirmed mechanics (July
            // 2026, extended later the same month): the contractor can
            // take either, both, or neither of a rent share and a
            // sale-price share, so neither is `required_if` anymore —
            // only the funding_path itself and the construction draws are
            // mandatory; the fee fields default to 0 (no cost) if left
            // blank.
            'funding_params.contractor_fee_pct'          => 'nullable|numeric|min:0|max:100',
            'funding_params.contractor_rent_share_pct'   => 'nullable|numeric|min:0|max:100',
            'funding_params.contractor_rent_share_years' => 'nullable|integer|min:0|max:60',
        ];
    }

    // ══════════════════════════════════════════════════════
    // PHASE 4 — SAVE / SHARE SNAPSHOTS
    // Mirrors KeepOrSellController's save/share pattern exactly: same
    // route shape, same 90-day share-link expiry, same "regenerating the
    // token resets the clock" behavior. The one structural difference —
    // this tool computes 3 scenarios plus Portfolio/Cash-Flow Impact, not
    // one hold-scenario, so the bulk of a saved row lives in JSON columns
    // rather than Keep-or-Sell's mostly-flat-columns shape (see the
    // investment_analyses migration for the reasoning).
    // ══════════════════════════════════════════════════════

    public function analysesIndex(Company $company, InvestmentProspect $prospect)
    {
        $this->authorizeCompany($company);
        abort_unless($prospect->company_id === $company->id, 404);

        $analyses = InvestmentAnalysis::where('investment_prospect_id', $prospect->id)
            ->where('company_id', $company->id)
            ->orderByDesc('created_at')
            ->get([
                'id', 'snapshot_label', 'funding_path', 'exit_year',
                'npv_base_case', 'irr_base_case', 'share_token',
                'analyst_recommendation', 'created_at',
            ]);

        return response()->json(['analyses' => $analyses]);
    }

    /**
     * Re-runs the exact same computation compute() just showed on screen
     * (via the shared runFullComputation()/computeValidationRules()) and
     * saves the verified result — never trusts client-supplied numbers,
     * same discipline as everywhere else in this app that persists a
     * computed figure.
     */
    public function storeAnalysis(Request $request, Company $company, InvestmentProspect $prospect)
    {
        $this->authorizeCompany($company);
        abort_unless($prospect->company_id === $company->id, 404);

        $rules = $this->computeValidationRules();
        $rules['snapshot_label'] = 'nullable|string|max:100';
        $rules['analyst_recommendation'] = 'nullable|string|max:5000';
        $data = $request->validate($rules);

        $outcome = $this->runFullComputation($company, $prospect, $data);
        if (isset($outcome['error'])) {
            return response()->json(['message' => $outcome['error']], 422);
        }

        $baseScenario = $outcome['result']['scenarios']['base'];

        $analysis = InvestmentAnalysis::create([
            'company_id'               => $company->id,
            'created_by'               => $request->user()->id,
            'investment_prospect_id'   => $prospect->id,
            'snapshot_label'           => $data['snapshot_label'] ?? null,
            'funding_path'             => $data['funding_path'],
            'exit_year'                => $data['exit_year'],
            'discount_rate_pct'        => $data['discount_rate_pct'],
            'corporate_tax_rate_pct'   => $data['corporate_tax_rate_pct'],
            'selling_costs_pct'        => $data['selling_costs_pct'],
            'exit_value_method'        => $data['exit_value_method'],
            'rent_collection_interval' => $data['rent_collection_interval'],
            'inflation_rate_pct'       => $data['inflation_rate_pct'] ?? 10.0,
            'scenario_inputs'          => $data['scenarios'],
            'funding_params'           => $data['funding_params'] ?? [],
            'computed_result'          => $outcome,
            'npv_base_case'            => $baseScenario['npv'] ?? null,
            'irr_base_case'            => $baseScenario['irr'] ?? null,
            'analyst_recommendation'   => $data['analyst_recommendation'] ?? null,
        ]);

        return response()->json(['id' => $analysis->id, 'saved' => true]);
    }

    public function showAnalysis(Company $company, InvestmentProspect $prospect, InvestmentAnalysis $analysis)
    {
        $this->authorizeCompany($company);
        abort_unless($analysis->company_id === $company->id && $analysis->investment_prospect_id === $prospect->id, 404);

        return response()->json($analysis);
    }

    public function updateAnalysisRecommendation(Request $request, Company $company, InvestmentProspect $prospect, InvestmentAnalysis $analysis)
    {
        $this->authorizeCompany($company);
        abort_unless($analysis->company_id === $company->id && $analysis->investment_prospect_id === $prospect->id, 404);

        $data = $request->validate(['analyst_recommendation' => 'nullable|string|max:5000']);
        $analysis->update($data);

        return response()->json(['saved' => true]);
    }

    public function destroyAnalysis(Company $company, InvestmentProspect $prospect, InvestmentAnalysis $analysis)
    {
        $this->authorizeCompany($company);
        abort_unless($analysis->company_id === $company->id && $analysis->investment_prospect_id === $prospect->id, 404);

        // Hard delete — a saved snapshot is a draft feasibility study, not
        // a committed financial record (same rule as everywhere else in
        // this tool).
        $analysis->delete();

        return response()->json(['deleted' => true]);
    }

    public function generateAnalysisToken(Company $company, InvestmentProspect $prospect, InvestmentAnalysis $analysis)
    {
        $this->authorizeCompany($company);
        abort_unless($analysis->company_id === $company->id && $analysis->investment_prospect_id === $prospect->id, 404);

        $token = Str::random(48);
        $analysis->update([
            'share_token'            => $token,
            'share_token_created_at' => now(),
        ]);

        return response()->json(['token' => $token]);
    }

    /**
     * PUBLIC — no auth required. Same 90-day expiry rule as
     * KeepOrSellController::share() (audit finding C-3 there) — a stale
     * link 404s instead of staying valid forever.
     */
    public function shareAnalysis(string $token)
    {
        $analysis = InvestmentAnalysis::where('share_token', $token)
            ->with(['prospect:id,prospect_name,nature,currency', 'company:id,name,trade_name,currency'])
            ->firstOrFail();

        $expiresAfterDays = 90;
        if (
            $analysis->share_token_created_at === null
            || $analysis->share_token_created_at->lt(now()->subDays($expiresAfterDays))
        ) {
            abort(404);
        }

        return Inertia::render('Properties/InvestmentDecision/Share', [
            'analysis' => [
                'id'                       => $analysis->id,
                'snapshot_label'           => $analysis->snapshot_label,
                'company_name'             => $analysis->company->trade_name ?? $analysis->company->name,
                'currency'                 => $analysis->company->currency ?? 'EGP',
                'prospect_name'            => $analysis->prospect->prospect_name ?? '—',
                'nature'                   => $analysis->prospect->nature ?? 'unit',
                'funding_path'             => $analysis->funding_path,
                'exit_year'                => $analysis->exit_year,
                'discount_rate_pct'        => $analysis->discount_rate_pct,
                'exit_value_method'        => $analysis->exit_value_method,
                'rent_collection_interval' => $analysis->rent_collection_interval,
                'computed_result'          => $analysis->computed_result,
                'analyst_recommendation'   => $analysis->analyst_recommendation,
                'created_at'               => $analysis->created_at->format('d M Y'),
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════
    // PORTFOLIO IMPACT — before/after against the Base Case scenario.
    // Deliberately a small, self-contained aggregate query rather than
    // calling into PropertyDashboardController::buildPortfolio() directly
    // — that method is large, private, and tightly bound to several other
    // private helpers on that controller (perPropertyFinancials(),
    // slotStatus()), so reusing it here would mean either making a chain
    // of Dashboard internals public or a much bigger refactor than this
    // sanity-check output warrants. This queries the same underlying
    // tables and the same base_amount/base currency columns the Dashboard
    // already relies on, so the numbers should always agree with it — but
    // if the Dashboard's own methodology for occupancy/NOI/ROI is ever
    // changed, this should be revisited to stay in step with it.
    // ══════════════════════════════════════════════════════
    private function portfolioImpactSummary(Company $company, string $baseCurrency, float $dealPurchasePrice, array $baseScenario): array
    {
        $totalUnits = Property::where('company_id', $company->id)
            ->whereNull('deleted_at')
            ->where('nature', 'unit')
            ->count()
            + PropertyUnit::whereHas('property', fn ($q) => $q->where('company_id', $company->id)->whereNull('deleted_at'))
                ->whereNull('deleted_at')
                ->count();

        $occupiedUnits = RentContract::where('company_id', $company->id)
            ->where('status', 'running')
            ->count();

        $occupancyRateBefore = $totalUnits > 0 ? round($occupiedUnits / $totalUnits * 100, 1) : 0.0;
        $occupancyRateAfter  = ($totalUnits + 1) > 0 ? round(($occupiedUnits + 1) / ($totalUnits + 1) * 100, 1) : 0.0;

        // Trailing 12 months, same "period NOI" concept the Profitability
        // tab uses — revenue/expenses already in base currency (base_amount).
        $periodStart = now()->subMonths(12)->toDateString();
        $periodEnd   = now()->toDateString();

        $portfolioRevenue = (float) DB::table('rent_revenues')
            ->where('company_id', $company->id)
            ->whereBetween('revenue_date', [$periodStart, $periodEnd])
            ->sum('base_amount');

        $portfolioExpenses = (float) DB::table('property_expenses')
            ->where('company_id', $company->id)
            ->whereBetween('expense_date', [$periodStart, $periodEnd])
            ->sum('base_amount');

        $portfolioNoiBefore = round($portfolioRevenue - $portfolioExpenses, 2);

        $portfolioAcquisitionCost = (float) Property::where('company_id', $company->id)
            ->whereNull('deleted_at')
            ->sum('acquisition_cost_base_amount')
            + (float) PropertyUnit::whereHas('property', fn ($q) => $q->where('company_id', $company->id)->whereNull('deleted_at'))
                ->whereNull('deleted_at')
                ->sum('acquisition_cost_base_amount');

        $roiBefore = $portfolioAcquisitionCost > 0 ? round($portfolioNoiBefore / $portfolioAcquisitionCost * 100, 2) : null;

        // This deal's own contribution — Year 1's net operating income
        // (before tax/financing, matching the "operating" scope of the
        // portfolio NOI figure above) and its own acquisition cost.
        $dealYear1 = $baseScenario['annual_cashflows'][0] ?? null;
        $dealNoi   = $dealYear1 ? ((float) $dealYear1['gross_revenue'] - (float) $dealYear1['other_opex']) : 0.0;

        $portfolioNoiAfter        = round($portfolioNoiBefore + $dealNoi, 2);
        $portfolioAcquisitionAfter = $portfolioAcquisitionCost + $dealPurchasePrice;
        $roiAfter = $portfolioAcquisitionAfter > 0 ? round($portfolioNoiAfter / $portfolioAcquisitionAfter * 100, 2) : null;

        return [
            'base_currency'          => $baseCurrency,
            'total_units_before'     => $totalUnits,
            'total_units_after'      => $totalUnits + 1,
            'occupancy_rate_before'  => $occupancyRateBefore,
            'occupancy_rate_after'   => $occupancyRateAfter,
            'portfolio_noi_before'   => $portfolioNoiBefore,
            'portfolio_noi_after'    => $portfolioNoiAfter,
            'blended_roi_before'     => $roiBefore,
            'blended_roi_after'      => $roiAfter,
        ];
    }

    // ══════════════════════════════════════════════════════
    // COMPANY CASH FLOW IMPACT — extends the same underlying data Cash
    // Forecast reads (rent collections, installment dues, expense
    // payments) out to the deal's exit year, at annual granularity. The
    // existing 12-month Cash Forecast page is completely untouched — this
    // is a separate, purpose-built projection just for this comparison.
    //
    // Confirmed approach (July 2026 planning session): use real scheduled
    // data where it exists; for any year beyond the last year with actual
    // scheduled data, project forward from that last known year,
    // compounding at $inflationRatePct per year (default 10%, always
    // user-overridable) — clearly flagged as projected vs scheduled so it
    // is never mistaken for a real forecast. A year is flagged as a
    // squeeze if the COMBINED (existing + this deal) net cash flow for
    // that year is negative.
    // ══════════════════════════════════════════════════════
    private function companyCashFlowImpact(Company $company, string $baseCurrency, int $exitYear, float $inflationRatePct, array $baseScenario): array
    {
        $today    = now();
        $cashIn   = array_fill(1, $exitYear, 0.0);
        $cashOut  = array_fill(1, $exitYear, 0.0);
        $isScheduled = array_fill(1, $exitYear, false);

        $bucketByYear = function ($query, string $dateColumn, string $amountColumn) use ($today, $exitYear, &$isScheduled) {
            $rows = $query->selectRaw("YEAR({$dateColumn}) as yr, SUM({$amountColumn}) as total")
                ->groupBy('yr')
                ->get();

            $out = array_fill(1, $exitYear, 0.0);
            foreach ($rows as $row) {
                $y = (int) $row->yr - $today->year + 1;
                if ($y >= 1 && $y <= $exitYear) {
                    $out[$y] += (float) $row->total;
                    $isScheduled[$y] = true;
                }
            }
            return $out;
        };

        $rentIn = $bucketByYear(
            DB::table('rent_collections')->where('company_id', $company->id)->where('collection_date', '>=', $today->toDateString()),
            'collection_date', 'base_amount'
        );
        $installmentsOut = $bucketByYear(
            DB::table('property_installment_dues')->where('company_id', $company->id)->whereIn('status', ['pending', 'overdue'])->where('due_date', '>=', $today->toDateString()),
            'due_date', 'base_amount'
        );
        $expensesOut = $bucketByYear(
            DB::table('property_expenses')->where('company_id', $company->id)->where('expense_date', '>=', $today->toDateString()),
            'expense_date', 'base_amount'
        );
        $corpExpensesOut = $bucketByYear(
            DB::table('corporate_expense_payments')->where('company_id', $company->id)->where('payment_date', '>=', $today->toDateString()),
            'payment_date', 'base_amount'
        );

        for ($y = 1; $y <= $exitYear; $y++) {
            $cashIn[$y]  = $rentIn[$y];
            $cashOut[$y] = $installmentsOut[$y] + $expensesOut[$y] + $corpExpensesOut[$y];
        }

        // Last year with any real scheduled data at all — everything after
        // it is projected, compounding at the inflation rate.
        $lastScheduledYear = 0;
        for ($y = 1; $y <= $exitYear; $y++) {
            if ($isScheduled[$y]) {
                $lastScheduledYear = $y;
            }
        }

        $inflationRate = $inflationRatePct / 100;
        if ($lastScheduledYear > 0 && $lastScheduledYear < $exitYear) {
            $baseIn  = $cashIn[$lastScheduledYear];
            $baseOut = $cashOut[$lastScheduledYear];
            for ($y = $lastScheduledYear + 1; $y <= $exitYear; $y++) {
                $yearsOut     = $y - $lastScheduledYear;
                $cashIn[$y]   = round($baseIn * pow(1 + $inflationRate, $yearsOut), 2);
                $cashOut[$y]  = round($baseOut * pow(1 + $inflationRate, $yearsOut), 2);
            }
        }

        // Overlay this deal's own Base Case cash flow on top.
        $rows = [];
        $accumulatedExisting = 0.0;
        $accumulatedCombined = 0.0;

        for ($y = 1; $y <= $exitYear; $y++) {
            $dealCf = $baseScenario['annual_cashflows'][$y - 1] ?? null;
            $dealNet = $dealCf ? (float) $dealCf['net_cf'] : 0.0;
            if ($y === 1) {
                $dealNet -= (float) ($baseScenario['year0_equity_outflow'] ?? 0); // the acquisition outlay lands in year 1's cash position
            }

            $existingNet = round($cashIn[$y] - $cashOut[$y], 2);
            $combinedNet = round($existingNet + $dealNet, 2);

            $accumulatedExisting += $existingNet;
            $accumulatedCombined += $combinedNet;

            $rows[] = [
                'year'                  => $y,
                'is_projected'          => $lastScheduledYear > 0 && $y > $lastScheduledYear,
                'existing_cash_in'      => round($cashIn[$y], 2),
                'existing_cash_out'     => round($cashOut[$y], 2),
                'existing_net'          => $existingNet,
                'deal_net'              => round($dealNet, 2),
                'combined_net'          => $combinedNet,
                'accumulated_existing'  => round($accumulatedExisting, 2),
                'accumulated_combined'  => round($accumulatedCombined, 2),
                'is_squeeze'            => $combinedNet < 0,
            ];
        }

        return [
            'base_currency'       => $baseCurrency,
            'inflation_rate_pct'  => $inflationRatePct,
            'last_scheduled_year' => $lastScheduledYear,
            'years'               => $rows,
            'has_squeeze'         => collect($rows)->contains('is_squeeze', true),
        ];
    }

    // ══════════════════════════════════════════════════════
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'prospect_name'          => 'required|string|max:255',
            'status'                 => 'required|in:evaluating,pursuing,passed,acquired',
            'nature'                 => 'required|in:unit,building,land,complex',
            'country'                => 'nullable|string|max:100',
            'governorate'            => 'nullable|string|max:100',
            'province'               => 'nullable|string|max:100',
            'location'               => 'nullable|string|max:255',
            'currency'               => 'required|string|max:10',
            'notes'                  => 'nullable|string|max:2000',

            // Single-unit only — required_if enforces these can't be left
            // blank when nature=unit, but they're simply not read at all
            // (and nulled out by the caller) for building/land/complex.
            'property_category_id'   => 'nullable|exists:property_categories,id',
            'property_type_id'       => 'nullable|exists:property_types,id',
            'area'                   => 'nullable|numeric|min:0',
            'unit_of_measurement'    => 'nullable|string|max:50',
            'purchase_price'         => 'required_if:nature,unit|nullable|numeric|min:0.01',
            'expected_monthly_rent'  => 'nullable|numeric|min:0',

            // Multi-unit only.
            'units'                                  => 'required_if:nature,building,land,complex|array',
            'units.*.unit_name'                      => 'required|string|max:255',
            'units.*.slot_type'                       => 'nullable|in:built_unit,land_slot',
            'units.*.property_category_id'           => 'nullable|exists:property_categories,id',
            'units.*.property_type_id'                => 'nullable|exists:property_types,id',
            'units.*.area'                            => 'nullable|numeric|min:0',
            'units.*.unit_of_measurement'             => 'nullable|string|max:50',
            'units.*.purchase_price'                  => 'required|numeric|min:0.01',
            'units.*.expected_monthly_rent'           => 'nullable|numeric|min:0',
        ]);

        if (in_array($data['nature'], ['building', 'land', 'complex']) && empty($data['units'])) {
            abort(422, 'A building, land, or complex prospect needs at least one unit.');
        }

        return $data;
    }

    /**
     * Hard-replace pattern (delete handled by the caller before this runs
     * on update; store() just inserts fresh). Every unit inherits the
     * PARENT prospect's currency — this app doesn't support mixed
     * currencies within one multi-unit deal (same simplification a real
     * multi-unit Property makes), so there's no per-unit currency input on
     * the form at all; this is just where that assumption is enforced.
     */
    private function syncUnits(Company $company, InvestmentProspect $prospect, array $units): void
    {
        foreach ($units as $i => $u) {
            InvestmentProspectUnit::create([
                'investment_prospect_id' => $prospect->id,
                'company_id'             => $company->id,
                'unit_name'              => $u['unit_name'],
                'slot_type'              => $u['slot_type'] ?? InvestmentProspectUnit::SLOT_BUILT_UNIT,
                'property_category_id'   => $u['property_category_id'] ?? null,
                'property_type_id'       => $u['property_type_id'] ?? null,
                'area'                   => $u['area'] ?? null,
                'unit_of_measurement'    => $u['unit_of_measurement'] ?? null,
                'purchase_price'         => $u['purchase_price'],
                'currency'               => $prospect->currency,
                'expected_monthly_rent'  => $u['expected_monthly_rent'] ?? null,
                'sort_order'             => $i,
            ]);
        }
    }

    private function categoriesWithTypes(Company $company)
    {
        return PropertyCategory::where('company_id', $company->id)
            ->with(['types' => fn($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    // Same list PropertyController offers — kept as its own copy rather
    // than a shared import, since PropertyController's version is private
    // to that class; if this list ever needs to grow, update both.
    private function currencyOptions(): array
    {
        return ['EGP', 'USD', 'EUR', 'GBP', 'SAR', 'AED'];
    }
}
