<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * FinVero NBFS — Loan Engine v5
 *
 * Day-count basis      : Actual days per calendar month / 360
 * Payment timing       : END (arrears) | BEGIN (advance)
 * Installment interval : monthly | quarterly | semi_annual
 *
 * Schedule types:
 *   normal          — fixed installment every period (annuity / PMT)
 *   variable        — fixed principal per period + actual-day interest
 *                     (interest-only during grace for grace variants)
 *   step_up         — installment amount increases by step_pct% every step_interval
 *   step_down       — installment amount decreases by step_pct% every step_interval
 *   grace_no_cap    — grace periods: interest paid, principal deferred
 *   grace_cap       — grace periods: interest capitalised into balance
 *   step_up_grace   — grace (capitalised) then step-up installments
 *   step_down_grace — grace (capitalised) then step-down installments
 *
 * Variable Schedule mechanic:
 *   Principal per period = total_principal / amort_periods
 *   Interest per period  = opening_balance × (annual_rate × actual_days / 360)
 *   Installment          = principal_per_period + interest
 *   If CBE corridor changes → only the interest portion changes;
 *   the principal per period stays fixed.
 *   Grace periods (grace_no_cap style only for variable): interest paid, principal deferred.
 *   No grace_cap variant for variable (principal is fixed, capitalising doesn't apply).
 *
 * Step-up / Step-down mechanic:
 *   The INSTALLMENT AMOUNT steps, not the interest rate.
 *   PMT base solved so PV of all stepped installments = balance at amort start.
 *
 * Step interval options: semi_annual (6 mo) | annual (12 mo)
 * Disbursement row (row 0) always shown.
 * Quarterly/Semi-annual: expanded to one row per calendar month.
 * Last installment: forced to closing balance = 0.00 exactly.
 *
 * Output row key naming — interest fields:
 *   monthly_interest   — interest recognized for that specific calendar month
 *                        (present on every expanded row: accrual + payment months)
 *                        Summing monthly_interest month-to-month builds the
 *                        Accrued Interest Receivable balance on the Balance Sheet.
 *                        On monthly schedules this field is always 0.0 (use `interest`).
 *   interest_payment   — cash interest actually collected (0 on accrual rows,
 *                        full period interest on payment month)
 *   interest          — interest for monthly (non-expanded) payment rows only
 */
class LoanEngine
{
    const DAY_COUNT_BASIS = 360;

    const INTERVAL_MONTHS = [
        'monthly'     => 1,
        'quarterly'   => 3,
        'semi_annual' => 6,
    ];

    const STEP_INTERVAL_MONTHS = [
        'semi_annual' => 6,
        'annual'      => 12,
    ];

    // ─────────────────────────────────────────────────────────────────
    // Public entry point
    // ─────────────────────────────────────────────────────────────────

    public static function generate(array $params): array
    {
        // ── Normalise inputs ──────────────────────────────────────────
        $principal       = (float)  $params['principal'];
        $annualRate      = (float)  $params['annual_rate'];
        $termMonths      = (int)    $params['term_months'];
        $disbDate        = Carbon::parse($params['disbursement_date']);
        $timing          = strtolower($params['payment_timing']        ?? 'end');
        $interval        = strtolower($params['installment_interval']  ?? 'monthly');
        $scheduleType    = strtolower($params['schedule_type']         ?? 'normal');
        $graceMonths     = (int)   ($params['grace_months']            ?? 0);
        $stepPct         = (float) ($params['step_pct']                ?? 0.0);
        $stepInterval    = strtolower($params['step_interval']         ?? 'annual');
        $corridorChanges = (array) ($params['corridor_changes']        ?? []);

        $isVariable = ($scheduleType === 'variable');
        $isStepType = in_array($scheduleType, ['step_up','step_down','step_up_grace','step_down_grace']);
        $stepDir    = in_array($scheduleType, ['step_up','step_up_grace']) ? 1 : -1;

        // ── Period structure ──────────────────────────────────────────
        $monthsPerPeriod    = self::INTERVAL_MONTHS[$interval] ?? 1;
        $totalPeriods       = (int) ceil($termMonths / $monthsPerPeriod);
        $gracePeriods       = (int) floor($graceMonths / $monthsPerPeriod);
        $amortPeriods       = $totalPeriods - $gracePeriods;

        $stepIntervalMonths  = self::STEP_INTERVAL_MONTHS[$stepInterval] ?? 12;
        $stepIntervalPeriods = max(1, (int) round($stepIntervalMonths / $monthsPerPeriod));

        // ── Build period dates + rate maps ────────────────────────────
        $periodDates = self::buildPeriodDates($disbDate, $totalPeriods, $monthsPerPeriod, $timing);
        $rateMap     = self::buildRateMap($annualRate, $totalPeriods);

        if (!empty($corridorChanges)) {
            $rateMap = self::applyCorridorChanges($rateMap, $corridorChanges, $periodDates);
        }

        $periodRates = self::buildPeriodRates($rateMap, $periodDates, $totalPeriods);

        // ── Accrual rate map ──────────────────────────────────────────
        // For END timing: interest on period p is computed over the accrual
        // window (the month(s) BEFORE the payment month). The rate that was
        // in effect during those accrual months is rateMap[p-1], not rateMap[p].
        // Period 1 accrues during the disbursement month — no corridor change
        // can precede disbursement, so the rate is always the base rate.
        // For BEGIN timing: accrual and payment months are identical → pass-through.
        $accrualRateMap = self::buildAccrualRateMap($rateMap, $totalPeriods, $timing);

        // ── Variable schedule — fixed principal per period ────────────
        if ($isVariable) {
            $principalPerPeriod = $amortPeriods > 0 ? round($principal / $amortPeriods, 2) : 0.0;

            $schedule = self::buildVariableSchedule(
                $principal, $principalPerPeriod, $accrualRateMap, $periodDates,
                $totalPeriods, $gracePeriods, $monthsPerPeriod, $timing, $disbDate
            );

            $summary = self::buildSummary($principal, $schedule);

            return [
                'params' => array_merge($params, [
                    'pmt_base'                => $principalPerPeriod,
                    'principal_per_period'    => $principalPerPeriod,
                    'total_periods'           => $totalPeriods,
                    'grace_periods'           => $gracePeriods,
                    'amort_periods'           => $amortPeriods,
                    'months_per_period'       => $monthsPerPeriod,
                    'step_interval_periods'   => 0,
                    'day_count_basis'         => self::DAY_COUNT_BASIS,
                    'is_expanded'             => ($monthsPerPeriod > 1),
                    'is_stepped'              => false,
                    'is_variable'             => true,
                ]),
                'schedule' => $schedule,
                'summary'  => $summary,
            ];
        }

        // ── Annuity / Step / Grace schedules (original logic) ─────────
        $graceCap = in_array($scheduleType, ['grace_cap','step_up_grace','step_down_grace']);

        $balanceAtAmort = $principal;
        if ($gracePeriods > 0 && $graceCap) {
            for ($p = 1; $p <= $gracePeriods; $p++) {
                $balanceAtAmort += $balanceAtAmort * $periodRates[$p];
            }
        }

        $pmtMultipliers = self::buildPmtMultipliers(
            $amortPeriods, $isStepType, $stepPct, $stepDir, $stepIntervalPeriods
        );

        $pmt1 = self::solvePmt1(
            $balanceAtAmort, $periodRates, $pmtMultipliers,
            $gracePeriods, $totalPeriods, $timing
        );

        $pmtMap = self::buildPmtMap($pmt1, $pmtMultipliers, $gracePeriods, $totalPeriods);

        $schedule = self::buildSchedule(
            $principal, $pmt1, $pmtMap, $rateMap, $accrualRateMap, $periodRates, $periodDates,
            $totalPeriods, $gracePeriods, $timing, $scheduleType,
            $monthsPerPeriod, $disbDate
        );

        $summary = self::buildSummary($principal, $schedule);

        return [
            'params' => array_merge($params, [
                'pmt_base'              => round($pmt1, 2),
                'total_periods'         => $totalPeriods,
                'grace_periods'         => $gracePeriods,
                'amort_periods'         => $amortPeriods,
                'months_per_period'     => $monthsPerPeriod,
                'step_interval_periods' => $stepIntervalPeriods,
                'day_count_basis'       => self::DAY_COUNT_BASIS,
                'is_expanded'           => ($monthsPerPeriod > 1),
                'is_stepped'            => $isStepType,
                'is_variable'           => false,
            ]),
            'schedule' => $schedule,
            'summary'  => $summary,
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // VARIABLE SCHEDULE BUILDER
    // Principal is fixed per period = total_principal / amort_periods
    // Interest is actual-day based on opening balance each period
    // CBE changes affect only the interest — principal stays fixed
    // Grace periods: interest paid, principal deferred (grace_no_cap style)
    // ─────────────────────────────────────────────────────────────────

    private static function buildVariableSchedule(
        float $principal, float $principalPerPeriod,
        array $accrualRateMap, array $periodDates,
        int $totalPeriods, int $gracePeriods,
        int $monthsPerPeriod, string $timing, Carbon $disbDate
    ): array {
        $rows        = [];
        $rows[]      = self::disbursementRow($principal, $disbDate);
        $openBalance = $principal;

        if ($monthsPerPeriod === 1) {
            // ── Monthly variable ─────────────────────────────────────
            for ($p = 1; $p <= $totalPeriods; $p++) {
                $pd         = $periodDates[$p];
                // accrualRateMap[p] = rate in effect during the accrual window:
                // for END timing this is the prior period's rate (disbursement
                // month rate for p=1); for BEGIN it is the payment-month rate.
                $annualRate = $accrualRateMap[$p];
                $periodRate = $annualRate * $pd['days'] / self::DAY_COUNT_BASIS;
                $isGrace    = ($p <= $gracePeriods);
                $isLast     = ($p === $totalPeriods);

                $interest = round($openBalance * $periodRate, 2);

                if ($isGrace) {
                    // Grace: pay interest only, principal deferred
                    $principalPaid = 0.0;
                    $installment   = $interest;
                    $closeBalance  = $openBalance;
                } elseif ($isLast) {
                    // Last: clear the balance exactly
                    $principalPaid = $openBalance;
                    $installment   = round($openBalance + $interest, 2);
                    $closeBalance  = 0.0;
                } else {
                    $principalPaid = $principalPerPeriod;
                    $installment   = round($principalPerPeriod + $interest, 2);
                    $closeBalance  = round($openBalance - $principalPerPeriod, 2);
                }

                $rows[] = [
                    'row_type'         => 'payment',
                    'period'           => $p,
                    'month_num'        => $p,
                    'date'             => $pd['date'],
                    'period_label'     => $pd['label'],
                    'days_in_period'   => $pd['days'],
                    'annual_rate'      => round($annualRate * 100, 4) . '%',
                    'annual_rate_raw'  => $annualRate,
                    'period_rate'      => round($periodRate * 100, 6) . '%',
                    'opening_balance'  => round($openBalance, 2),
                    'disbursement'     => 0.0,
                    'monthly_interest' => $interest,   // for monthly variable: recognized = collected same month
                    'interest_payment' => 0.0,         // always 0 on monthly rows (use 'interest' for cash)
                    'interest'         => $interest,
                    'principal'        => round($principalPaid, 2),
                    'installment'      => $installment,
                    'closing_balance'  => round($closeBalance, 2),
                    'is_grace'         => $isGrace,
                    'is_payment_month' => true,
                    'is_last'          => $isLast,
                    'is_adjusted'      => false,
                    'note'             => $isGrace
                        ? 'Grace — interest paid'
                        : ($isLast ? 'Last payment' : ''),
                ];

                $openBalance = $closeBalance;
            }

        } else {
            // ── Quarterly / Semi-annual variable — expanded ───────────
            $rowNum = 0;

            for ($p = 1; $p <= $totalPeriods; $p++) {
                $pd         = $periodDates[$p];
                // accrualRateMap[p] = rate in effect during the accrual window
                $annualRate = $accrualRateMap[$p];
                $isGrace    = ($p <= $gracePeriods);
                $isLast     = ($p === $totalPeriods);

                // Full-period interest on opening balance
                $periodRate      = $annualRate * $pd['days'] / self::DAY_COUNT_BASIS;
                $periodInterest  = round($openBalance * $periodRate, 2);

                $periodPrincipal = $isGrace ? 0.0 : ($isLast ? $openBalance : $principalPerPeriod);
                $periodInstallment = $isGrace
                    ? $periodInterest
                    : round($periodPrincipal + $periodInterest, 2);

                $balanceAfter = $isGrace
                    ? $openBalance
                    : round($openBalance - $periodPrincipal, 2);

                $monthBalance   = $openBalance;
                $priorSlicesSum = 0.0;  // tracks sum of monthly_interest for accrual months

                foreach ($pd['months'] as $mIdx => $monthCarbon) {
                    $rowNum++;
                    $isPaymentMonth  = ($mIdx === count($pd['months']) - 1);
                    $daysInMonth     = (int) $monthCarbon->daysInMonth;
                    $monthRate       = $annualRate * $daysInMonth / self::DAY_COUNT_BASIS;

                    // Payment month slice = periodInterest - priorSlicesSum (exact, no rounding gap)
                    if ($isPaymentMonth) {
                        $accruedInterest = round($periodInterest - $priorSlicesSum, 2);
                    } else {
                        $accruedInterest = round($monthBalance * $monthRate, 2);
                        $priorSlicesSum += $accruedInterest;
                    }

                    if ($isPaymentMonth) {
                        $interestPayment = $periodInterest;
                        $installment     = $periodInstallment;
                        $principalRow    = $isGrace ? 0.0 : $periodPrincipal;
                        $closeBalance    = $balanceAfter;
                    } else {
                        $interestPayment = 0.0;
                        $installment     = 0.0;
                        $principalRow    = 0.0;
                        $closeBalance    = $monthBalance;
                    }

                    $rows[] = [
                        'row_type'         => $isPaymentMonth ? 'payment' : 'accrual',
                        'period'           => $p,
                        'month_num'        => $rowNum,
                        'date'             => $monthCarbon->format('Y-m-d'),
                        'period_label'     => $monthCarbon->format('M Y'),
                        'days_in_period'   => $daysInMonth,
                        'annual_rate'      => round($annualRate * 100, 4) . '%',
                        'annual_rate_raw'  => $annualRate,
                        'period_rate'      => round($monthRate * 100, 6) . '%',
                        'opening_balance'  => round($monthBalance, 2),
                        'disbursement'     => 0.0,
                        'monthly_interest' => round($accruedInterest, 2),
                        'interest_payment' => round($interestPayment, 2),
                        'interest'         => 0.0,
                        'principal'        => round($principalRow, 2),
                        'installment'      => round($installment, 2),
                        'closing_balance'  => round($closeBalance, 2),
                        'is_grace'         => $isGrace,
                        'is_payment_month' => $isPaymentMonth,
                        'is_last'          => ($isLast && $isPaymentMonth),
                        'is_adjusted'      => false,
                        'note'             => $isPaymentMonth
                            ? ($isGrace ? 'Grace — interest paid' : ($isLast ? 'Last payment' : ''))
                            : 'Accrual',
                    ];

                    $monthBalance = $closeBalance;
                }

                $openBalance = $balanceAfter;
            }
        }

        return $rows;
    }

    // ─────────────────────────────────────────────────────────────────
    // Period Date Builder
    // ─────────────────────────────────────────────────────────────────

    private static function buildPeriodDates(
        Carbon $disbDate, int $totalPeriods, int $monthsPerPeriod, string $timing
    ): array {
        $periods = [];
        $offset  = ($timing === 'begin') ? 0 : 1;

        for ($p = 1; $p <= $totalPeriods; $p++) {
            // ── Payment months (row date / label / display) ───────────
            $months      = [];
            $startOffset = $offset + ($p - 1) * $monthsPerPeriod;

            for ($m = 0; $m < $monthsPerPeriod; $m++) {
                $month    = $disbDate->copy()->addMonths($startOffset + $m)->startOfMonth();
                $months[] = $month;
            }

            // ── Accrual months (day-count for interest) ───────────────
            // BEGIN: installment paid at start of period → interest accrues
            //        over those same months → accrual months = payment months.
            // END:   installment paid at end of period → interest accrued
            //        over the PREVIOUS month(s) → shift back one period.
            $accrualMonths = [];
            $totalDays = 0;
            if ($timing === 'end') {
                $accrualStart = $startOffset - $monthsPerPeriod;
                for ($m = 0; $m < $monthsPerPeriod; $m++) {
                    $accrualMonth    = $disbDate->copy()->addMonths($accrualStart + $m)->startOfMonth();
                    $accrualMonths[] = $accrualMonth;
                    $totalDays      += (int) $accrualMonth->daysInMonth;
                }
            } else {
                foreach ($months as $month) {
                    $accrualMonths[] = $month;
                    $totalDays      += (int) $month->daysInMonth;
                }
            }

            $lastMonth  = end($months);
            $firstMonth = $months[0];

            $periods[$p] = [
                'months'        => $months,
                'accrual_months'=> $accrualMonths,
                'days'          => $totalDays,
                'date'          => $lastMonth->format('Y-m-d'),
                'label'         => $monthsPerPeriod === 1
                    ? $lastMonth->format('M Y')
                    : ($firstMonth->format('M Y') . ' – ' . $lastMonth->format('M Y')),
            ];
        }

        return $periods;
    }

    // ─────────────────────────────────────────────────────────────────
    // Rate Map
    // ─────────────────────────────────────────────────────────────────

    private static function buildRateMap(float $baseRate, int $totalPeriods): array
    {
        $map = [];
        for ($p = 1; $p <= $totalPeriods; $p++) {
            $map[$p] = $baseRate;
        }
        return $map;
    }

    // ─────────────────────────────────────────────────────────────────
    // CBE Corridor Changes
    // ─────────────────────────────────────────────────────────────────

    private static function applyCorridorChanges(
        array $rateMap, array $corridorChanges, array $periodDates
    ): array {
        foreach ($corridorChanges as $change) {
            $changeDate = Carbon::parse($change['date'])->startOfMonth();
            $newRate    = (float) $change['corridor_rate'] + (float) $change['margin'];
            foreach ($periodDates as $period => $pd) {
                // Compare against the ACCRUAL month, not the payment month.
                // The rate in effect during the accrual window is what determines
                // how much interest accrues — so a corridor change that takes
                // effect in January must be applied to the period whose accrual
                // window starts in January (which for END timing is the period
                // whose payment row lands in February).
                if ($pd['accrual_months'][0]->greaterThanOrEqualTo($changeDate)) {
                    $rateMap[$period] = $newRate;
                }
            }
        }
        return $rateMap;
    }

    // ─────────────────────────────────────────────────────────────────
    // Period Rate Builder
    // ─────────────────────────────────────────────────────────────────

    private static function buildPeriodRates(array $rateMap, array $periodDates, int $totalPeriods): array
    {
        $rates = [];
        for ($p = 1; $p <= $totalPeriods; $p++) {
            $rates[$p] = $rateMap[$p] * $periodDates[$p]['days'] / self::DAY_COUNT_BASIS;
        }
        return $rates;
    }

    // ─────────────────────────────────────────────────────────────────
    // Accrual Rate Map
    // applyCorridorChanges now assigns rates based on accrual_months[0],
    // so rateMap[p] already holds the rate that was in effect during
    // period p's accrual window for both END and BEGIN timing.
    // This function is retained as a clean pass-through so the call
    // sites remain explicit about intent.
    // ─────────────────────────────────────────────────────────────────

    private static function buildAccrualRateMap(
        array $rateMap, int $totalPeriods, string $timing
    ): array {
        return $rateMap; // rateMap is already accrual-window-correct after applyCorridorChanges
    }

    // ─────────────────────────────────────────────────────────────────
    // PMT Multiplier Map
    // ─────────────────────────────────────────────────────────────────

    private static function buildPmtMultipliers(
        int $amortPeriods, bool $isStepType,
        float $stepPct, int $stepDir, int $stepIntervalPeriods
    ): array {
        $multipliers = [];
        for ($i = 0; $i < $amortPeriods; $i++) {
            if (!$isStepType || $stepPct == 0.0) {
                $multipliers[$i] = 1.0;
            } else {
                $group           = (int) floor($i / $stepIntervalPeriods);
                $multipliers[$i] = pow(1 + $stepDir * $stepPct, $group);
            }
        }
        return $multipliers;
    }

    // ─────────────────────────────────────────────────────────────────
    // Solve PMT₁
    // ─────────────────────────────────────────────────────────────────

    private static function solvePmt1(
        float $pv, array $periodRates, array $pmtMultipliers,
        int $gracePeriods, int $totalPeriods, string $timing
    ): float {
        $n = count($pmtMultipliers);
        if ($n === 0) return 0.0;

        $sumWeightedDiscount = 0.0;
        $cumulativeDiscount  = 1.0;

        foreach ($pmtMultipliers as $i => $multiplier) {
            $periodIdx = $gracePeriods + 1 + $i;

            if ($timing === 'end') {
                $r = $periodRates[$periodIdx] ?? 0.0;
                $cumulativeDiscount *= (1 + $r);
                $sumWeightedDiscount += $multiplier / $cumulativeDiscount;
            } else {
                $sumWeightedDiscount += $multiplier / $cumulativeDiscount;
                $r = $periodRates[$periodIdx] ?? 0.0;
                $cumulativeDiscount *= (1 + $r);
            }
        }

        return $sumWeightedDiscount == 0 ? 0.0 : $pv / $sumWeightedDiscount;
    }

    // ─────────────────────────────────────────────────────────────────
    // Build PMT Map
    // ─────────────────────────────────────────────────────────────────

    private static function buildPmtMap(
        float $pmt1, array $pmtMultipliers, int $gracePeriods, int $totalPeriods
    ): array {
        $map = [];
        for ($p = 1; $p <= $gracePeriods; $p++) {
            $map[$p] = 0.0;
        }
        foreach ($pmtMultipliers as $i => $multiplier) {
            $p       = $gracePeriods + 1 + $i;
            $map[$p] = round($pmt1 * $multiplier, 2);
        }
        return $map;
    }

    // ─────────────────────────────────────────────────────────────────
    // Schedule Builder — dispatcher
    // ─────────────────────────────────────────────────────────────────

    private static function buildSchedule(
        float $principal, float $pmt1, array $pmtMap,
        array $rateMap, array $accrualRateMap, array $periodRates, array $periodDates,
        int $totalPeriods, int $gracePeriods,
        string $timing, string $scheduleType,
        int $monthsPerPeriod, Carbon $disbDate
    ): array {
        $rows   = [];
        $rows[] = self::disbursementRow($principal, $disbDate);

        if ($monthsPerPeriod === 1) {
            $rows = array_merge($rows, self::buildMonthlyRows(
                $principal, $pmtMap, $accrualRateMap, $periodRates, $periodDates,
                $totalPeriods, $gracePeriods, $scheduleType, $timing
            ));
        } else {
            $rows = array_merge($rows, self::buildExpandedRows(
                $principal, $pmtMap, $accrualRateMap, $periodDates,
                $totalPeriods, $gracePeriods, $scheduleType,
                $monthsPerPeriod, $timing
            ));
        }

        return $rows;
    }

    // ─────────────────────────────────────────────────────────────────
    // Disbursement Row
    // ─────────────────────────────────────────────────────────────────

    private static function disbursementRow(float $principal, Carbon $disbDate): array
    {
        return [
            'row_type'         => 'disbursement',
            'period'           => 0,
            'month_num'        => 0,
            'date'             => $disbDate->format('Y-m-d'),
            'period_label'     => $disbDate->format('M Y') . ' (Disbursement)',
            'days_in_period'   => 0,
            'annual_rate'      => '—',
            'annual_rate_raw'  => 0,
            'period_rate'      => '—',
            'opening_balance'  => 0.0,
            'disbursement'     => round($principal, 2),
            'monthly_interest' => 0.0,
            'interest_payment' => 0.0,
            'interest'         => 0.0,
            'principal'        => 0.0,
            'installment'      => 0.0,
            'closing_balance'  => round($principal, 2),
            'is_grace'         => false,
            'is_payment_month' => false,
            'is_last'          => false,
            'is_adjusted'      => false,
            'note'             => 'Disbursement',
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // Monthly schedule (annuity/step/grace)
    // ─────────────────────────────────────────────────────────────────

    private static function buildMonthlyRows(
        float $principal, array $pmtMap,
        array $accrualRateMap, array $periodRates, array $periodDates,
        int $totalPeriods, int $gracePeriods, string $scheduleType,
        string $timing
    ): array {
        $rows        = [];
        $openBalance = $principal;
        $graceCap    = in_array($scheduleType, ['grace_cap','step_up_grace','step_down_grace']);
        $graceNoCap  = ($scheduleType === 'grace_no_cap');
        $isBegin     = ($timing === 'begin');

        for ($p = 1; $p <= $totalPeriods; $p++) {
            $pd           = $periodDates[$p];
            // Use the accrual-window rate for interest calculation.
            // For END timing this is the rate of the prior period's month.
            // For BEGIN timing accrualRateMap === rateMap (pass-through).
            $annualRate   = $accrualRateMap[$p];
            $periodRate   = $annualRate * $pd['days'] / self::DAY_COUNT_BASIS;
            $isGrace      = ($p <= $gracePeriods);
            $isLast       = ($p === $totalPeriods);
            $scheduledPmt = $pmtMap[$p] ?? 0.0;
            $isAdjusted   = false;

            if ($isBegin) {
                if ($isGrace && $graceCap) {
                    $installment   = 0.0;
                    $principalPaid = 0.0;
                    $interest      = round($openBalance * $periodRate, 2);
                    $closeBalance  = round($openBalance + $interest, 2);
                } elseif ($isGrace && $graceNoCap) {
                    $interest      = round($openBalance * $periodRate, 2);
                    $installment   = $interest;
                    $principalPaid = 0.0;
                    $closeBalance  = $openBalance;
                } elseif ($isLast) {
                    $installment   = $openBalance;
                    $principalPaid = $openBalance;
                    $interest      = 0.0;
                    $closeBalance  = 0.0;
                    $isAdjusted    = (abs($installment - $scheduledPmt) >= 0.01);
                } else {
                    $installment         = $scheduledPmt;
                    $principalPaid       = $installment;
                    $balanceAfterPayment = round($openBalance - $principalPaid, 2);
                    $interest            = round($balanceAfterPayment * $periodRate, 2);
                    $principalPaid       = round($installment - $interest, 2);
                    $closeBalance        = round($openBalance - $principalPaid, 2);
                }
            } else {
                $interest = round($openBalance * $periodRate, 2);

                if ($isGrace && $graceCap) {
                    $installment   = 0.0;
                    $principalPaid = 0.0;
                    $closeBalance  = round($openBalance + $interest, 2);
                } elseif ($isGrace && $graceNoCap) {
                    $installment   = $interest;
                    $principalPaid = 0.0;
                    $closeBalance  = $openBalance;
                } elseif ($isLast) {
                    $installment   = round($openBalance + $interest, 2);
                    $principalPaid = round($openBalance, 2);
                    $closeBalance  = 0.0;
                    $isAdjusted    = (abs($installment - $scheduledPmt) >= 0.01);
                } else {
                    $installment   = $scheduledPmt;
                    $principalPaid = round($installment - $interest, 2);
                    $closeBalance  = round($openBalance - $principalPaid, 2);
                }
            }

            $diff = $isAdjusted ? round($installment - $scheduledPmt, 2) : 0;

            $rows[] = [
                'row_type'         => 'payment',
                'period'           => $p,
                'month_num'        => $p,
                'date'             => $pd['date'],
                'period_label'     => $pd['label'],
                'days_in_period'   => $pd['days'],
                'annual_rate'      => round($annualRate * 100, 4) . '%',
                'annual_rate_raw'  => $annualRate,
                'period_rate'      => round($periodRate * 100, 6) . '%',
                'opening_balance'  => round($openBalance, 2),
                'disbursement'     => 0.0,
                'monthly_interest' => round($interest, 2),  // for monthly: recognized = collected same month
                'interest_payment' => 0.0,                  // always 0 on monthly rows (use 'interest' for cash)
                'interest'         => round($interest, 2),
                'principal'        => round($principalPaid, 2),
                'installment'      => round($installment, 2),
                'closing_balance'  => round($closeBalance, 2),
                'is_grace'         => $isGrace,
                'is_payment_month' => true,
                'is_last'          => $isLast,
                'is_adjusted'      => $isAdjusted,
                'note'             => self::buildNote($isGrace, $graceCap, $graceNoCap, $isLast, $isAdjusted, $diff),
            ];

            $openBalance = $closeBalance;
        }

        return $rows;
    }

    // ─────────────────────────────────────────────────────────────────
    // Expanded schedule (quarterly / semi-annual)
    // ─────────────────────────────────────────────────────────────────

    private static function buildExpandedRows(
        float $principal, array $pmtMap,
        array $accrualRateMap, array $periodDates,
        int $totalPeriods, int $gracePeriods, string $scheduleType,
        int $monthsPerPeriod, string $timing
    ): array {
        $rows        = [];
        $openBalance = $principal;
        $rowNum      = 0;
        $graceCap    = in_array($scheduleType, ['grace_cap','step_up_grace','step_down_grace']);
        $graceNoCap  = ($scheduleType === 'grace_no_cap');
        $isBegin     = ($timing === 'begin');

        for ($p = 1; $p <= $totalPeriods; $p++) {
            $pd           = $periodDates[$p];
            // Use the accrual-window rate for interest calculation.
            // For END timing this is the rate of the prior period's month.
            // For BEGIN timing accrualRateMap === rateMap (pass-through).
            $annualRate   = $accrualRateMap[$p];
            $isGrace      = ($p <= $gracePeriods);
            $isLast       = ($p === $totalPeriods);
            $scheduledPmt = $pmtMap[$p] ?? 0.0;

            $periodRate = $annualRate * $pd['days'] / self::DAY_COUNT_BASIS;

            $isAdjusted = false;
            if ($isBegin) {
                if ($isGrace && $graceCap) {
                    $periodInterest    = round($openBalance * $periodRate, 2);
                    $periodInstallment = 0.0;
                    $principalPaid     = 0.0;
                    $balanceAfter      = round($openBalance + $periodInterest, 2);
                } elseif ($isGrace && $graceNoCap) {
                    $periodInterest    = round($openBalance * $periodRate, 2);
                    $periodInstallment = $periodInterest;
                    $principalPaid     = 0.0;
                    $balanceAfter      = $openBalance;
                } elseif ($isLast) {
                    $periodInstallment = $openBalance;
                    $principalPaid     = $openBalance;
                    $periodInterest    = 0.0;
                    $balanceAfter      = 0.0;
                    $isAdjusted        = (abs($periodInstallment - $scheduledPmt) >= 0.01);
                } else {
                    $periodInstallment   = $scheduledPmt;
                    $balanceAfterPayment = round($openBalance - $periodInstallment, 2);
                    $periodInterest      = round($balanceAfterPayment * $periodRate, 2);
                    $principalPaid       = round($periodInstallment - $periodInterest, 2);
                    $balanceAfter        = round($openBalance - $principalPaid, 2);
                }
            } else {
                $periodInterest = round($openBalance * $periodRate, 2);
                if ($isGrace && $graceCap) {
                    $periodInstallment = 0.0;
                    $principalPaid     = 0.0;
                    $balanceAfter      = round($openBalance + $periodInterest, 2);
                } elseif ($isGrace && $graceNoCap) {
                    $periodInstallment = $periodInterest;
                    $principalPaid     = 0.0;
                    $balanceAfter      = $openBalance;
                } elseif ($isLast) {
                    $periodInstallment = round($openBalance + $periodInterest, 2);
                    $principalPaid     = round($openBalance, 2);
                    $balanceAfter      = 0.0;
                    $isAdjusted        = (abs($periodInstallment - $scheduledPmt) >= 0.01);
                } else {
                    $periodInstallment = $scheduledPmt;
                    $principalPaid     = round($periodInstallment - $periodInterest, 2);
                    $balanceAfter      = round($openBalance - $principalPaid, 2);
                }
            }

            $diff           = $isAdjusted ? round($periodInstallment - $scheduledPmt, 2) : 0;
            $monthBalance   = $openBalance;
            $priorSlicesSum = 0.0;  // tracks sum of monthly_interest for accrual months

            foreach ($pd['months'] as $mIdx => $monthCarbon) {
                $rowNum++;
                $isPaymentMonth  = ($mIdx === count($pd['months']) - 1);
                $daysInMonth     = (int) $monthCarbon->daysInMonth;
                $monthRate       = $annualRate * $daysInMonth / self::DAY_COUNT_BASIS;

                // On payment month: slice = periodInterest - sum_of_prior_slices
                // This guarantees: slice1 + slice2 + slice3 = periodInterest exactly.
                // No rounding residual → bs_accrued_revenue zeroes cleanly on payment.
                // On accrual months: compute normally and accumulate.
                if ($isPaymentMonth) {
                    $accruedInterest = round($periodInterest - $priorSlicesSum, 2);
                } else {
                    $accruedInterest = round($monthBalance * $monthRate, 2);
                    $priorSlicesSum += $accruedInterest;
                }

                if ($isPaymentMonth) {
                    if ($isGrace && $graceCap) {
                        $interestPayment = 0.0;
                        $installment     = 0.0;
                        $principalRow    = 0.0;
                        $closeBalance    = round($monthBalance + $accruedInterest, 2);
                    } elseif ($isGrace && $graceNoCap) {
                        $interestPayment = $periodInterest;
                        $installment     = $periodInterest;
                        $principalRow    = 0.0;
                        $closeBalance    = $openBalance;
                    } else {
                        $interestPayment = $periodInterest;
                        $installment     = $periodInstallment;
                        $principalRow    = $principalPaid;
                        $closeBalance    = $balanceAfter;
                    }
                } else {
                    $interestPayment = 0.0;
                    $installment     = 0.0;
                    $principalRow    = 0.0;
                    $closeBalance    = $monthBalance;
                }

                $rows[] = [
                    'row_type'         => $isPaymentMonth ? 'payment' : 'accrual',
                    'period'           => $p,
                    'month_num'        => $rowNum,
                    'date'             => $monthCarbon->format('Y-m-d'),
                    'period_label'     => $monthCarbon->format('M Y'),
                    'days_in_period'   => $daysInMonth,
                    'annual_rate'      => round($annualRate * 100, 4) . '%',
                    'annual_rate_raw'  => $annualRate,
                    'period_rate'      => round($monthRate * 100, 6) . '%',
                    'opening_balance'  => round($monthBalance, 2),
                    'disbursement'     => 0.0,
                    'monthly_interest' => round($accruedInterest, 2),
                    'interest_payment' => round($interestPayment, 2),
                    'interest'         => 0.0,
                    'principal'        => round($principalRow, 2),
                    'installment'      => round($installment, 2),
                    'closing_balance'  => round($closeBalance, 2),
                    'is_grace'         => $isGrace,
                    'is_payment_month' => $isPaymentMonth,
                    'is_last'          => ($isLast && $isPaymentMonth),
                    'is_adjusted'      => ($isPaymentMonth && $isAdjusted),
                    'note'             => $isPaymentMonth
                        ? self::buildNote($isGrace, $graceCap, $graceNoCap, $isLast, $isAdjusted, $diff)
                        : 'Accrual',
                ];

                $monthBalance = $closeBalance;
            }

            $openBalance = $balanceAfter;
        }

        return $rows;
    }

    // ─────────────────────────────────────────────────────────────────
    // Note builder
    // ─────────────────────────────────────────────────────────────────

    private static function buildNote(
        bool $isGrace, bool $graceCap, bool $graceNoCap,
        bool $isLast, bool $isAdjusted, float $diff
    ): string {
        if ($isGrace && $graceCap)   return 'Grace — capitalised';
        if ($isGrace && $graceNoCap) return 'Grace — interest paid';
        if ($isLast && $isAdjusted)  return 'Last — adjusted (' . ($diff > 0 ? '+' : '') . $diff . ')';
        return '';
    }

    // ─────────────────────────────────────────────────────────────────
    // Summary
    // ─────────────────────────────────────────────────────────────────

    private static function buildSummary(float $principal, array $schedule): array
    {
        $totalInterest    = 0.0;
        $totalPrincipal   = 0.0;
        $totalInstallment = 0.0;

        foreach ($schedule as $row) {
            $totalInterest    += ($row['interest_payment'] > 0)
                ? $row['interest_payment']
                : $row['interest'];
            $totalInstallment += $row['installment'];
            if ($row['principal'] > 0) {
                $totalPrincipal += $row['principal'];
            }
        }

        return [
            'original_principal'   => round($principal, 2),
            'total_interest'       => round($totalInterest, 2),
            'total_principal_paid' => round($totalPrincipal, 2),
            'total_installments'   => round($totalInstallment, 2),
            'periods'              => count($schedule),
        ];
    }
}