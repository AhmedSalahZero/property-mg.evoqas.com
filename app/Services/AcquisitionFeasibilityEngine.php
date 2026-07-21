<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Investment Decision Tool — Acquisition Feasibility Engine.
 *
 * Answers: "if we buy this prospect, hold for N years (3–10, user-chosen),
 * and sell at the end — is it worth it, and can we actually pay for it
 * along the way?"
 *
 * Revision history relevant to reading this file:
 * - Phase 1 (July 2026): single-unit only, one flat occupancy-ramp model
 *   applied to every prospect regardless of size, annual-only discounting.
 * - This revision (July 2026, same month): two real gaps closed after
 *   direct testing against a spreadsheet model —
 *     1. The occupancy-ramp model only makes sense for a MULTI-UNIT deal
 *        (a building/land/complex genuinely fills up unit by unit). A
 *        single shop is either rented or empty — there's no such thing as
 *        "30% occupied." Single-unit prospects now use a plain vacancy
 *        period instead (see averageOccupancyForYearSingleUnit()).
 *     2. Cash flow discounting was annual-only, which silently assumed
 *        rent is collected once a year. Rent collection interval
 *        (monthly/quarterly/semi-annual/annual), collected IN ADVANCE
 *        (standard for Egyptian commercial leases — see the VERO logic
 *        reference), is now modeled explicitly with the mathematically
 *        correct geometric period rate — see computeNpv()/computeIrr().
 *        Confirmed by hand-verification: quarterly/semi-annual in advance
 *        should score HIGHER than monthly for the same annual rent, since
 *        larger lump sums land earlier — the engine now reproduces that.
 *
 * Structural difference from Keep-or-Sell's engine (unchanged from Phase
 * 1): this tool evaluates a property NOT YET OWNED, so the Year-0
 * purchase outlay is part of NPV/IRR here, unlike Keep-or-Sell which
 * starts from an asset already on the books.
 */
class AcquisitionFeasibilityEngine
{
    const FUNDING_CASH_PURCHASE       = 'cash_purchase';
    const FUNDING_BANK_LOAN           = 'bank_loan';
    const FUNDING_SELLER_INSTALLMENTS = 'seller_installments';
    const FUNDING_CUSTOM_SCHEDULE     = 'custom_schedule';
    const FUNDING_CONTRACTOR_DEAL     = 'contractor_deal';

    const SCENARIOS = ['conservative', 'base', 'optimistic'];

    const COLLECTION_INTERVALS = [
        'monthly'       => 12,
        'quarterly'     => 4,
        'semi_annually' => 2,
        'annually'      => 1,
    ];

    /**
     * Sensible starting defaults per scenario — all fully overridable,
     * never locked. Carries both occupancy models (months_vacant for a
     * single unit, occupancy_ramp_months/occupancy_start_pct for a
     * multi-unit deal) so one shared default set works for either
     * prospect shape; the engine picks whichever one applies based on
     * the prospect's nature — see buildAnnualCashFlows().
     */
    public static function scenarioDefaults(): array
    {
        return [
            'conservative' => [
                'rent_growth_rate_pct'   => 3.0,
                'months_vacant'          => 3,   // single-unit: 3 months empty before first tenant
                'occupancy_ramp_months'  => 12,  // multi-unit: 12 months to fully lease the building/complex
                'occupancy_start_pct'    => 30.0,
                'appreciation_rate_pct'  => 5.0,
                'exit_cap_rate_pct'      => 9.0,
                'other_opex_pct'         => 15.0,
            ],
            'base' => [
                'rent_growth_rate_pct'   => 6.0,
                'months_vacant'          => 1,
                'occupancy_ramp_months'  => 6,
                'occupancy_start_pct'    => 50.0,
                'appreciation_rate_pct'  => 8.0,
                'exit_cap_rate_pct'      => 7.0,
                'other_opex_pct'         => 12.0,
            ],
            'optimistic' => [
                'rent_growth_rate_pct'   => 9.0,
                'months_vacant'          => 0,
                'occupancy_ramp_months'  => 3,
                'occupancy_start_pct'    => 70.0,
                'appreciation_rate_pct'  => 11.0,
                'exit_cap_rate_pct'      => 6.0,
                'other_opex_pct'         => 10.0,
            ],
        ];
    }

    /**
     * Runs all three scenarios (or whichever subset is passed) against one
     * prospect + one funding path, and returns them side by side.
     *
     * @param  array  $prospect  ['purchase_price' => float, 'expected_monthly_rent' => float, 'is_multi_unit' => bool] — purchase_price/expected_monthly_rent already in base currency AND already totaled across units if multi-unit (both done by the caller).
     * @param  array  $shared    ['exit_year' => int (3-10), 'corporate_tax_rate_pct' => float, 'selling_costs_pct' => float, 'discount_rate_pct' => float, 'exit_value_method' => 'appreciation'|'cap_rate'|'higher_of', 'rent_collection_interval' => 'monthly'|'quarterly'|'semi_annually'|'annually']
     * @param  array  $scenarioInputs  ['conservative' => [...], 'base' => [...], 'optimistic' => [...]]
     * @param  string $fundingPath     self::FUNDING_CASH_PURCHASE | self::FUNDING_BANK_LOAN
     * @param  array  $fundingParams   funding-path-specific params
     */
    public function compareScenarios(array $prospect, array $shared, array $scenarioInputs, string $fundingPath, array $fundingParams): array
    {
        $fundingSchedule = $this->computeFundingSchedule($fundingPath, $fundingParams, (float) $prospect['purchase_price'], (int) $shared['exit_year']);

        $results = [];
        foreach ($scenarioInputs as $scenarioKey => $assumptions) {
            $results[$scenarioKey] = $this->runOne($prospect, $shared, $assumptions, $fundingSchedule);
        }

        return [
            'funding_path'     => $fundingPath,
            'funding_schedule' => [
                'year0_equity_outflow'        => $fundingSchedule['year0_equity_outflow'],
                'annual_financing_outflow'    => $fundingSchedule['annual_financing_outflow'],
                'outstanding_balance_by_year' => $fundingSchedule['outstanding_balance_by_year'],
                'loan_schedule'               => $fundingSchedule['loan_schedule'],
            ],
            'scenarios' => $results,
        ];
    }

    // ══════════════════════════════════════════════════════
    // ONE SCENARIO
    // ══════════════════════════════════════════════════════
    private function runOne(array $prospect, array $shared, array $assumptions, array $fundingSchedule): array
    {
        $purchasePrice   = (float) $prospect['purchase_price'];
        $isMultiUnit     = (bool) ($prospect['is_multi_unit'] ?? false);
        $exitYear        = (int) $shared['exit_year'];
        $discountRate    = (float) $shared['discount_rate_pct'] / 100;
        $taxRate         = (float) $shared['corporate_tax_rate_pct'] / 100;
        $sellingCostsPct = (float) $shared['selling_costs_pct'] / 100;
        $exitMethod      = $shared['exit_value_method'];
        $interval        = $shared['rent_collection_interval'] ?? 'monthly';
        $periodsPerYear  = self::COLLECTION_INTERVALS[$interval] ?? 12;

        $annualCFs = $this->buildAnnualCashFlows($prospect, $assumptions, $fundingSchedule, $taxRate, $exitYear, $isMultiUnit);

        $lastYear = end($annualCFs);
        $lastNoi  = $lastYear['gross_revenue'] - $lastYear['other_opex'];

        $appreciationRate = (float) $assumptions['appreciation_rate_pct'] / 100;
        $exitCapRate      = (float) $assumptions['exit_cap_rate_pct'] / 100;

        $tvAppreciation = $purchasePrice * pow(1 + $appreciationRate, $exitYear);
        $tvCapRate      = $exitCapRate > 0 ? ($lastNoi / $exitCapRate) : 0;

        if ($exitMethod === 'appreciation') {
            $terminalValue     = $tvAppreciation;
            $terminalValueNote = 'market_appreciation';
        } elseif ($exitMethod === 'cap_rate') {
            $terminalValue     = $tvCapRate;
            $terminalValueNote = 'cap_rate';
        } else {
            $terminalValue     = max($tvAppreciation, $tvCapRate);
            $terminalValueNote = $tvAppreciation >= $tvCapRate ? 'market_appreciation' : 'cap_rate';
        }

        $outstandingAtExit = (float) ($fundingSchedule['outstanding_balance_by_year'][$exitYear] ?? 0.0);

        // Contractor Development Deal only — confirmed mechanics (July 2026
        // planning session): the contractor's cut is a % of the FULL sale
        // price (not profit above cost), and is owed ONLY if/when the
        // property is actually sold — modeled here as an exit-only
        // deduction from sale proceeds, same treatment as an outstanding
        // loan balance. Zero for every other funding path.
        $contractorFeePct = (float) ($fundingSchedule['contractor_fee_pct'] ?? 0) / 100;
        $contractorFee    = round($terminalValue * $contractorFeePct, 2);

        $netSaleProceeds = round($terminalValue * (1 - $sellingCostsPct) - $outstandingAtExit - $contractorFee, 2);

        $year0Outflow = (float) $fundingSchedule['year0_equity_outflow'];

        $npv = $this->computeNpv($annualCFs, $netSaleProceeds, $year0Outflow, $discountRate, $exitYear, $periodsPerYear);
        $irr = $this->computeIrr($annualCFs, $netSaleProceeds, $year0Outflow, $exitYear, $periodsPerYear);

        // Permanent safety net (added July 2026 after a real bug — a units
        // mismatch feeding LoanEngine a percentage where it expected a
        // decimal fraction — produced numbers around 10^33 in magnitude).
        // No legitimate deal's NPV, outstanding loan balance, or sale
        // proceeds should ever be many multiples of its own purchase
        // price. If any of them are, something upstream fed the engine a
        // degenerate input (whether from this specific bug, a future one,
        // or a genuine typo), and the honest thing to do is say so rather
        // than render a confident-looking, meaningless number anywhere —
        // including the year-by-year detail table and its summary
        // sentence, not just the headline NPV figure. The threshold (100x
        // purchase price) is deliberately generous — a real answer should
        // never come remotely close to it even in extreme but legitimate
        // scenarios.
        $isImplausible = $purchasePrice > 0 && (
            abs($npv) > $purchasePrice * 100
            || abs($outstandingAtExit) > $purchasePrice * 100
            || abs($netSaleProceeds) > $purchasePrice * 100
        );

        $warning = $isImplausible
            ? 'This result looks invalid (magnitude far beyond anything realistic) — please double-check the Bank Loan fields (especially Loan Term and Interest Rate) and recompute.'
            : null;

        return [
            'year0_equity_outflow'     => round($year0Outflow, 2),
            'annual_cashflows'         => $isImplausible ? [] : $annualCFs,
            'terminal_value'           => round($terminalValue, 2),
            'terminal_value_note'      => $terminalValueNote,
            'tv_appreciation'          => round($tvAppreciation, 2),
            'tv_cap_rate'              => round($tvCapRate, 2),
            'outstanding_loan_at_exit' => $isImplausible ? null : round($outstandingAtExit, 2),
            'contractor_fee_at_exit'   => $isImplausible ? null : $contractorFee,
            'net_sale_proceeds'        => $isImplausible ? null : $netSaleProceeds,
            'npv'                      => $isImplausible ? null : round($npv, 2),
            'irr'                      => $isImplausible ? null : ($irr !== null ? round($irr, 4) : null),
            'rent_collection_interval' => $interval,
            'computation_warning'      => $warning,
        ];
    }

    // ══════════════════════════════════════════════════════
    // ANNUAL CASH FLOWS
    // ══════════════════════════════════════════════════════
    private function buildAnnualCashFlows(array $prospect, array $assumptions, array $fundingSchedule, float $taxRate, int $exitYear, bool $isMultiUnit): array
    {
        $monthlyRentPotential = (float) ($prospect['expected_monthly_rent'] ?? 0);
        $rentGrowth           = (float) $assumptions['rent_growth_rate_pct'] / 100;
        $otherOpexPct         = (float) $assumptions['other_opex_pct'] / 100;

        $rows = [];

        // Contractor Development Deal only — confirmed mechanics (July
        // 2026): the contractor can take BOTH a % of annual rent (for a
        // set number of years, starting from Year 1 — "rent start") AND a
        // % of the sale price at exit, independently — either, both, or
        // neither can be filled in. Unlike the sale-price share (which is
        // only ever owed if RAM actually sells — see runOne()), the rent
        // share is earned every one of its years regardless of whether
        // RAM ever sells, since it's tied to rental income, not the exit
        // event. Treated as a genuine operating cost (like a management
        // fee), reducing taxable income — NOT a capital item the way a
        // loan payment or seller-installment is.
        $rentSharePct   = (float) ($fundingSchedule['contractor_rent_share_pct'] ?? 0) / 100;
        $rentShareYears = (int) ($fundingSchedule['contractor_rent_share_years'] ?? 0);

        for ($y = 1; $y <= $exitYear; $y++) {
            $potentialAnnualRent = $monthlyRentPotential * 12 * pow(1 + $rentGrowth, $y - 1);

            $avgOccupancy = $isMultiUnit
                ? $this->averageOccupancyForYearMultiUnit($y, max(0, (int) $assumptions['occupancy_ramp_months']), min(100, max(0, (float) $assumptions['occupancy_start_pct'])) / 100)
                : $this->averageOccupancyForYearSingleUnit($y, max(0, (int) ($assumptions['months_vacant'] ?? 0)));

            $grossRevenue = $potentialAnnualRent * $avgOccupancy;
            $otherOpex    = $grossRevenue * $otherOpexPct;

            $contractorRentShare = ($y <= $rentShareYears) ? round($grossRevenue * $rentSharePct, 2) : 0.0;

            $netBeforeTax = $grossRevenue - $otherOpex - $contractorRentShare;
            $corporateTax = $netBeforeTax > 0 ? $netBeforeTax * $taxRate : 0;

            // Capital item, same treatment as Keep-or-Sell's installment_payment
            // — deducted from cash flow but never part of the taxable base.
            $financingOutflow = (float) ($fundingSchedule['annual_financing_outflow'][$y] ?? 0.0);

            $netCf = $netBeforeTax - $corporateTax - $financingOutflow;

            $rows[] = [
                'year'                  => $y,
                'potential_annual_rent' => round($potentialAnnualRent, 2),
                'avg_occupancy_pct'     => round($avgOccupancy * 100, 1),
                'gross_revenue'         => round($grossRevenue, 2),
                'other_opex'            => round($otherOpex, 2),
                'contractor_rent_share' => $contractorRentShare,
                'net_before_tax'        => round($netBeforeTax, 2),
                'corporate_tax'         => round($corporateTax, 2),
                'financing_outflow'     => round($financingOutflow, 2),
                'net_cf'                => round($netCf, 2),
            ];
        }

        return $rows;
    }

    /**
     * SINGLE UNIT — a shop/office/apartment is either rented or empty,
     * never "40% occupied." Rent is 0 for the first $monthsVacant months
     * of the whole holding period, then full rent every month after.
     * Averaged across the 12 months that fall in acquisition-year $year,
     * so a vacancy period that ends mid-year is reflected as a blended
     * rate for that year rather than snapping straight to 100%.
     */
    private function averageOccupancyForYearSingleUnit(int $year, int $monthsVacant): float
    {
        if ($monthsVacant <= 0) {
            return 1.0;
        }

        $firstMonth = ($year - 1) * 12 + 1;
        $sum        = 0.0;

        for ($m = $firstMonth; $m < $firstMonth + 12; $m++) {
            $sum += $m <= $monthsVacant ? 0.0 : 1.0;
        }

        return $sum / 12;
    }

    /**
     * MULTI-UNIT (Building / Land / Complex) — a genuine fill-up curve
     * across many separate units, linear from $startPct (month 1) to
     * 100% (month $rampMonths), flat 100% before/after. This is the
     * original Phase 1 model, correctly scoped now to only the case
     * where a percentage-occupied state is physically real.
     */
    private function averageOccupancyForYearMultiUnit(int $year, int $rampMonths, float $startPct): float
    {
        if ($rampMonths <= 0) {
            return 1.0;
        }

        $firstMonth = ($year - 1) * 12 + 1;
        $sum        = 0.0;

        for ($m = $firstMonth; $m < $firstMonth + 12; $m++) {
            $sum += $m >= $rampMonths ? 1.0 : $startPct + (1 - $startPct) * ($m / $rampMonths);
        }

        return $sum / 12;
    }

    // ══════════════════════════════════════════════════════
    // FUNDING SCHEDULE — cash_purchase | bank_loan
    // ══════════════════════════════════════════════════════
    private function computeFundingSchedule(string $fundingPath, array $params, float $purchasePrice, int $exitYear): array
    {
        if ($fundingPath === self::FUNDING_BANK_LOAN) {
            return $this->bankLoanSchedule($params, $purchasePrice, $exitYear);
        }

        if ($fundingPath === self::FUNDING_SELLER_INSTALLMENTS) {
            return $this->sellerInstallmentsSchedule($params, $exitYear);
        }

        if ($fundingPath === self::FUNDING_CUSTOM_SCHEDULE) {
            return $this->customPaymentSchedule($params, $exitYear);
        }

        if ($fundingPath === self::FUNDING_CONTRACTOR_DEAL) {
            return $this->contractorDealSchedule($params, $exitYear);
        }

        return [
            'year0_equity_outflow'        => $purchasePrice,
            'annual_financing_outflow'    => [],
            'outstanding_balance_by_year' => [],
            'loan_schedule'               => null,
        ];
    }

    /**
     * Fix for a real bug found in production (July 2026): `$params['x'] ?? $default`
     * only falls back when the key is missing/null — NOT when it's an empty
     * string, which is exactly what a cleared number input sends from the
     * browser. `(int) ''` silently becomes 0, and a 0-month loan term used
     * to reach LoanEngine unchallenged even though Laravel's own
     * `required_if|integer|min:1` validation on this same field should have
     * caught it. This normalizer treats null, missing, AND empty string all
     * the same way — "not provided, use the default" — so nothing empty can
     * silently coerce into a meaningless 0 again, regardless of how it got
     * here or whether an earlier validation layer should have already
     * caught it. Defense in depth, not a replacement for that validation.
     */
    private function positiveIntOrDefault($value, int $default): int
    {
        if ($value === null || $value === '' || !is_numeric($value) || (int) $value < 1) {
            return $default;
        }
        return (int) $value;
    }

    private function bankLoanSchedule(array $params, float $purchasePrice, int $exitYear): array
    {
        $downPaymentPct = (float) ($params['down_payment_pct'] ?? 20) / 100;
        $downPayment    = round($purchasePrice * $downPaymentPct, 2);
        $loanPrincipal  = round($purchasePrice - $downPayment, 2);

        $disbursementDate = $params['disbursement_date'] ?? Carbon::today()->toDateString();

        $loanResult = LoanEngine::generate([
            'principal'            => $loanPrincipal,
            // Fix for a real bug found in production (July 2026):
            // LoanEngine expects annual_rate as a DECIMAL FRACTION (0.18
            // for 18%), confirmed by cross-checking LoanEngineController
            // (the real Loan Calculator), which explicitly does
            // `$validated['annual_rate'] / 100` before calling generate().
            // This was passing the raw percentage (18) straight through —
            // LoanEngine silently treated 18 as 1800%, compounded monthly
            // over the loan term, producing an NPV around 10^33 in
            // magnitude. This exact class of bug (a number silently
            // correct-looking but 100x off) is why the permanent
            // "implausible result" safety net was added at the same time
            // — that net is what actually caught this on screen instead
            // of it looking like a real, if surprising, number.
            'annual_rate'          => (float) ($params['annual_rate'] ?? 0) / 100,
            'term_months'          => $this->positiveIntOrDefault($params['term_months'] ?? null, 120),
            'disbursement_date'    => $disbursementDate,
            'payment_timing'       => 'end',
            'installment_interval' => $params['installment_interval'] ?? 'monthly',
            'schedule_type'        => $params['schedule_type'] ?? 'normal',
            'grace_months'         => max(0, (int) ($params['grace_months'] ?? 0)),
        ]);

        $annualFinancingOutflow   = [];
        $outstandingBalanceByYear = [];
        $baseYear                 = Carbon::parse($disbursementDate)->year;

        foreach ($loanResult['schedule'] as $row) {
            if ($row['row_type'] !== 'payment') {
                continue;
            }
            $rowYear = Carbon::parse($row['date'])->year - $baseYear + 1;
            if ($rowYear < 1 || $rowYear > $exitYear) {
                continue;
            }
            $annualFinancingOutflow[$rowYear] = ($annualFinancingOutflow[$rowYear] ?? 0.0) + (float) $row['installment'];
            $outstandingBalanceByYear[$rowYear] = (float) $row['closing_balance'];
        }

        for ($y = 1; $y <= $exitYear; $y++) {
            if (!isset($outstandingBalanceByYear[$y])) {
                $outstandingBalanceByYear[$y] = $y > 1 ? ($outstandingBalanceByYear[$y - 1] ?? 0.0) : $loanPrincipal;
            }
        }

        return [
            'year0_equity_outflow'        => $downPayment,
            'annual_financing_outflow'    => $annualFinancingOutflow,
            'outstanding_balance_by_year' => $outstandingBalanceByYear,
            'loan_schedule'               => $loanResult['schedule'],
        ];
    }

    // ══════════════════════════════════════════════════════
    // SELLER / DEVELOPER INSTALLMENTS — reuses the exact same date/amount
    // generation logic as a real property's Regular-Mode installment plan
    // (InstallmentScheduleGenerator, shared with PropertyInstallmentPlan —
    // confirmed reuse, July 2026 planning session), so this tool can never
    // silently disagree with what a real installment schedule would
    // produce for the same inputs.
    // ══════════════════════════════════════════════════════
    private function sellerInstallmentsSchedule(array $params, int $exitYear): array
    {
        $dealStartDate = $params['disbursement_date'] ?? Carbon::today()->toDateString();
        $rows          = InstallmentScheduleGenerator::generateRows($params['regular_plan'] ?? []);

        return $this->scheduleRowsToFundingOutflow($rows, $dealStartDate, $exitYear);
    }

    // ══════════════════════════════════════════════════════
    // CUSTOM PAYMENT SCHEDULE — plain user-entered {date, amount} rows,
    // same shape as a real property's Variable-Mode installment dues.
    // ══════════════════════════════════════════════════════
    private function customPaymentSchedule(array $params, int $exitYear): array
    {
        $dealStartDate = $params['disbursement_date'] ?? Carbon::today()->toDateString();
        $rows          = $this->normalizeCustomRows($params['custom_rows'] ?? []);

        return $this->scheduleRowsToFundingOutflow($rows, $dealStartDate, $exitYear);
    }

    // ══════════════════════════════════════════════════════
    // CONTRACTOR DEVELOPMENT DEAL — confirmed mechanics (July 2026
    // planning session, extended later the same month): RAM funds the
    // build itself (modeled as the same free-form dated-draw schedule as
    // Custom Payment Schedule), and the contractor can take BOTH,
    // EITHER, or NEITHER of:
    //   - a % of ANNUAL RENT for a set number of years starting from
    //     Year 1 ("rent start") — earned every one of those years
    //     regardless of whether RAM ever sells (see the deduction in
    //     buildAnnualCashFlows(), treated as a genuine operating cost);
    //   - a % of the FULL SALE PRICE, owed ONLY if/when the property is
    //     sold — never a cost if RAM holds and leases instead (see the
    //     deduction in runOne(), applied once against exit sale proceeds).
    // Neither is required — a blank/zero field simply means that leg of
    // the deal isn't part of this particular contract.
    // ══════════════════════════════════════════════════════
    private function contractorDealSchedule(array $params, int $exitYear): array
    {
        $dealStartDate = $params['disbursement_date'] ?? Carbon::today()->toDateString();
        $rows          = $this->normalizeCustomRows($params['custom_rows'] ?? []);

        $schedule = $this->scheduleRowsToFundingOutflow($rows, $dealStartDate, $exitYear);
        $schedule['contractor_fee_pct']          = (float) ($params['contractor_fee_pct'] ?? 0);
        $schedule['contractor_rent_share_pct']   = (float) ($params['contractor_rent_share_pct'] ?? 0);
        $schedule['contractor_rent_share_years'] = (int) ($params['contractor_rent_share_years'] ?? 0);

        return $schedule;
    }

    private function normalizeCustomRows(array $rows): array
    {
        return array_values(array_filter(array_map(function ($row) {
            $date   = $row['date'] ?? null;
            $amount = (float) ($row['amount'] ?? 0);
            if (empty($date) || $amount <= 0) {
                return null;
            }
            return ['due_type' => 'custom', 'due_date' => Carbon::parse($date)->toDateString(), 'amount' => $amount];
        }, $rows)));
    }

    /**
     * Shared by all three "dated schedule" funding paths (Seller
     * Installments, Custom Schedule, and Contractor Deal's construction
     * draws) — buckets a list of {due_date, amount} rows into:
     *   - year0_equity_outflow: anything due in the same calendar month as
     *     the deal start date — paid immediately, never discounted, same
     *     convention as a Bank Loan's down payment.
     *   - annual_financing_outflow[year]: everything else that falls
     *     within the holding period, bucketed by acquisition-year (same
     *     calendar-year-based convention bankLoanSchedule() already uses).
     *   - outstanding_balance_by_year[exitYear]: the sum of everything
     *     still due AFTER the exit year — confirmed design decision (July
     *     2026): if the schedule runs longer than the holding period,
     *     whatever's left unpaid is settled out of sale proceeds at exit,
     *     the same way an outstanding bank loan balance already is. This
     *     reuses runOne()'s existing outstanding-balance lookup unchanged
     *     — no new deduction logic was needed there.
     */
    private function scheduleRowsToFundingOutflow(array $rows, string $dealStartDate, int $exitYear): array
    {
        $dealStart               = Carbon::parse($dealStartDate);
        $year0EquityOutflow      = 0.0;
        $annualFinancingOutflow  = [];
        $remainingAfterExit      = 0.0;

        foreach ($rows as $row) {
            $dueDate = Carbon::parse($row['due_date']);
            $amount  = (float) $row['amount'];

            if ($dueDate->year === $dealStart->year && $dueDate->month === $dealStart->month) {
                $year0EquityOutflow += $amount;
                continue;
            }

            $rowYear = $dueDate->year - $dealStart->year + 1;

            if ($rowYear < 1) {
                // Due before the deal technically starts (shouldn't
                // normally happen) — treat as immediate, same as day-0.
                $year0EquityOutflow += $amount;
            } elseif ($rowYear <= $exitYear) {
                $annualFinancingOutflow[$rowYear] = ($annualFinancingOutflow[$rowYear] ?? 0.0) + $amount;
            } else {
                $remainingAfterExit += $amount;
            }
        }

        $outstandingBalanceByYear = [];
        for ($y = 1; $y <= $exitYear; $y++) {
            $outstandingBalanceByYear[$y] = ($y === $exitYear) ? $remainingAfterExit : 0.0;
        }

        return [
            'year0_equity_outflow'        => round($year0EquityOutflow, 2),
            'annual_financing_outflow'    => $annualFinancingOutflow,
            'outstanding_balance_by_year' => $outstandingBalanceByYear,
            'loan_schedule'               => null,
        ];
    }

    // ══════════════════════════════════════════════════════
    // NPV — REVENUE is discounted at collection-interval granularity, IN
    // ADVANCE (confirmed July 2026, hand-verified against a spreadsheet
    // test): each year's gross revenue is split evenly into
    // $periodsPerYear equal installments, the first one landing at the
    // very start of that year (zero discount for period 0 of year 1),
    // using the geometric period rate — NEVER a flat division of the
    // annual rate, since (1+r/12)^12 ≠ (1+r) and would silently apply a
    // different effective annual rate than the one the rest of the model
    // uses (this was the exact bug caught during testing).
    //
    // OpEx / Tax / Financing outflows and the exit sale proceeds remain
    // on the original annual, year-end convention — those aren't governed
    // by the tenant's collection interval (a loan is paid monthly
    // regardless of how the tenant pays rent, and there's no equivalent
    // "collection schedule" for a one-off sale), so splitting them the
    // same way would model a timing precision the deal doesn't actually
    // have.
    // ══════════════════════════════════════════════════════
    private function computeNpv(array $annualCFs, float $netSaleProceeds, float $year0Outflow, float $discountRate, int $exitYear, int $periodsPerYear): float
    {
        $periodRate = pow(1 + $discountRate, 1 / $periodsPerYear) - 1;

        $npv = -$year0Outflow;

        foreach ($annualCFs as $cf) {
            $y = $cf['year'];
            $periodRevenue = $cf['gross_revenue'] / $periodsPerYear;
            for ($p = 0; $p < $periodsPerYear; $p++) {
                $globalPeriodIndex = ($y - 1) * $periodsPerYear + $p; // in advance: period 0 of year 1 = today, zero discount
                $npv += $periodRevenue / pow(1 + $periodRate, $globalPeriodIndex);
            }
            $annualCosts = $cf['other_opex'] + $cf['corporate_tax'] + $cf['financing_outflow'];
            $npv -= $annualCosts / pow(1 + $discountRate, $y);
        }

        $npv += $netSaleProceeds / pow(1 + $discountRate, $exitYear);

        return $npv;
    }

    // ══════════════════════════════════════════════════════
    // IRR via bisection — same split (revenue at collection-interval
    // granularity, costs/terminal annual) applied at every trial rate.
    // ══════════════════════════════════════════════════════
    private function computeIrr(array $annualCFs, float $netSaleProceeds, float $year0Outflow, int $exitYear, int $periodsPerYear): ?float
    {
        $npvAt = function (float $rate) use ($annualCFs, $netSaleProceeds, $year0Outflow, $exitYear, $periodsPerYear): float {
            if ($rate <= -1) {
                return PHP_FLOAT_MAX;
            }
            $periodRate = pow(1 + $rate, 1 / $periodsPerYear) - 1;

            $npv = -$year0Outflow;
            foreach ($annualCFs as $cf) {
                $y = $cf['year'];
                $periodRevenue = $cf['gross_revenue'] / $periodsPerYear;
                for ($p = 0; $p < $periodsPerYear; $p++) {
                    $globalPeriodIndex = ($y - 1) * $periodsPerYear + $p;
                    $npv += $periodRevenue / pow(1 + $periodRate, $globalPeriodIndex);
                }
                $annualCosts = $cf['other_opex'] + $cf['corporate_tax'] + $cf['financing_outflow'];
                $npv -= $annualCosts / pow(1 + $rate, $y);
            }
            $npv += $netSaleProceeds / pow(1 + $rate, $exitYear);

            return $npv;
        };

        $lo = -0.99;
        $hi = 5.0;

        if ($npvAt($lo) * $npvAt($hi) > 0) {
            return null;
        }

        for ($i = 0; $i < 100; $i++) {
            $mid = ($lo + $hi) / 2;
            if (abs($hi - $lo) < 0.0000001) {
                break;
            }
            if ($npvAt($mid) * $npvAt($lo) <= 0) {
                $hi = $mid;
            } else {
                $lo = $mid;
            }
        }

        $irr = ($lo + $hi) / 2 * 100;

        return abs($irr) > 500 ? null : $irr;
    }
}