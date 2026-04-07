<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\FinancialAnnualPlan;
use Illuminate\Support\Facades\DB;

/**
 * FixedAssetPlanService
 *
 * Calculates 12-month depreciation, cash payment, and loan schedules
 * for plan_fixed_asset_rows and persists results to plan_fixed_asset_calculations.
 *
 * Called by FixedAssetsController::calculate()
 * Read by Financial Results engine.
 */
class FixedAssetPlanService
{
    // ─────────────────────────────────────────────────────────────────
    //  ENTRY POINT — calculate & persist all rows for a plan + type
    // ─────────────────────────────────────────────────────────────────
    public function calculateAndPersist(FinancialAnnualPlan $plan, string $assetType): array
    {
        // Build the 12 YYYY-MM strings for the plan year
        $studyMonths = $this->buildMonths($plan);

        $rows = DB::table('plan_fixed_asset_rows')
            ->where('plan_id', $plan->id)
            ->where('asset_type', $assetType)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $summary = [
            'total_capex'        => 0,
            'total_depreciation' => 0,
            'rows_calculated'    => 0,
        ];

        foreach ($rows as $row) {
            $calc = $this->calculateRow($row, $studyMonths);
            $this->persistCalc($plan->id, $row->id, $calc, $studyMonths);

            $summary['total_capex']        += $row->total_cost;
            $summary['total_depreciation'] += $calc['annual_dep'];
            $summary['rows_calculated']++;
        }

        return $summary;
    }

    // ─────────────────────────────────────────────────────────────────
    //  CALCULATE ONE ROW — returns arrays indexed 0-11
    // ─────────────────────────────────────────────────────────────────
    public function calculateRow(object $row, array $studyMonths): array
    {
        $cost          = (float) $row->total_cost;
        $depYears      = (int)   $row->depreciation_years;
        $usefulLifeMos = $depYears * 12;
        $startYm       = $row->start_date; // YYYY-MM
        $equityPct     = (float) $row->equity_pct / 100;
        $debtPct       = (float) $row->debt_pct   / 100;
        $interestPct   = (float) $row->interest_pct;
        $tenorMos      = (int)   $row->tenor_months;
        $graceMos      = (int)   $row->grace_months;
        $debtAmount    = $cost * $debtPct;

        $dep           = array_fill(0, 12, 0.0);
        $pay           = array_fill(0, 12, 0.0);   // equity cash payment
        $loanPrincipal = array_fill(0, 12, 0.0);
        $loanInterest  = array_fill(0, 12, 0.0);

        if ($cost <= 0) {
            return $this->emptyResult();
        }

        // ── Depreciation ──────────────────────────────────────────────
        if ($usefulLifeMos > 0) {
            $monthlyDep = $cost / $usefulLifeMos;
            $depStart   = Carbon::parse($startYm . '-01')->startOfMonth();

            foreach ($studyMonths as $mi => $ym) {
                $ymDate           = Carbon::parse($ym . '-01')->startOfMonth();
                if ($ymDate < $depStart) continue;
                $elapsed = $depStart->diffInMonths($ymDate) + 1;
                if ($elapsed > $usefulLifeMos) continue;
                $dep[$mi] = round($monthlyDep, 4);
            }
        }

        // ── Payment Schedule → equity cash outflow ─────────────────
        $paySchedule = $this->buildPaymentSchedule($cost, $row, $studyMonths);
        foreach ($paySchedule as $mi => $amount) {
            $pay[$mi] = round($amount * $equityPct, 4);
        }

        // ── Loan / Debt Schedule ───────────────────────────────────
        if ($debtAmount > 0 && $tenorMos > 0) {
            $startIdx     = array_search($startYm, $studyMonths);
            if ($startIdx === false) $startIdx = 0;
            $repayStartIdx = min($startIdx + $graceMos, count($studyMonths) - 1);
            $repayStartYm  = $studyMonths[$repayStartIdx] ?? $startYm;

            $debtSched = $this->buildDebtSchedule($debtAmount, $interestPct, $tenorMos, $repayStartYm, $studyMonths);
            foreach ($debtSched as $mi => $dRow) {
                $loanPrincipal[$mi] = round($dRow['principal'], 4);
                $loanInterest[$mi]  = round($dRow['interest'],  4);
            }
        }

        return [
            'dep'            => $dep,
            'pay'            => $pay,
            'loan_principal' => $loanPrincipal,
            'loan_interest'  => $loanInterest,
            'annual_dep'     => array_sum($dep),
            'annual_pay'     => array_sum($pay),
            'annual_lp'      => array_sum($loanPrincipal),
            'annual_li'      => array_sum($loanInterest),
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    //  PERSIST CALC ROW  (upsert on asset_row_id)
    // ─────────────────────────────────────────────────────────────────
    private function persistCalc(int $planId, int $rowId, array $calc, array $studyMonths): void
    {
        $data = ['plan_id' => $planId, 'asset_row_id' => $rowId];

        for ($i = 1; $i <= 12; $i++) {
            $mi = $i - 1;
            $data["dep_m{$i}"]            = $calc['dep'][$mi]            ?? 0;
            $data["pay_m{$i}"]            = $calc['pay'][$mi]            ?? 0;
            $data["loan_principal_m{$i}"] = $calc['loan_principal'][$mi] ?? 0;
            $data["loan_interest_m{$i}"]  = $calc['loan_interest'][$mi]  ?? 0;
        }

        $data['total_capex']          = 0; // set on row level
        $data['total_depreciation']   = $calc['annual_dep'];
        $data['total_equity_payment'] = $calc['annual_pay'];
        $data['total_loan_principal'] = $calc['annual_lp'];
        $data['total_loan_interest']  = $calc['annual_li'];
        $data['updated_at']           = now();

        $existing = DB::table('plan_fixed_asset_calculations')->where('asset_row_id', $rowId)->first();

        if ($existing) {
            DB::table('plan_fixed_asset_calculations')
                ->where('asset_row_id', $rowId)
                ->update($data);
        } else {
            $data['created_at'] = now();
            DB::table('plan_fixed_asset_calculations')->insert($data);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  PAYMENT SCHEDULE — returns array[month_index => amount]
    // ─────────────────────────────────────────────────────────────────
    private function buildPaymentSchedule(float $cost, object $row, array $studyMonths): array
    {
        $startYm     = $row->start_date;
        $paymentTerm = $row->payment_term ?? 'cash';
        $schedule    = array_fill(0, 12, 0.0);
        $startIdx    = array_search($startYm, $studyMonths);
        if ($startIdx === false) $startIdx = 0;

        if ($paymentTerm === 'cash') {
            $schedule[$startIdx] = $cost;
            return $schedule;
        }

        if ($paymentTerm === 'installment') {
            $cfg        = is_string($row->installment_config)
                ? json_decode($row->installment_config, true) ?? []
                : (array) ($row->installment_config ?? []);
            $resvPct    = (float) ($cfg['reservation_pct'] ?? 0) / 100;
            $contrPct   = (float) ($cfg['contractual_pct'] ?? 0) / 100;
            $afterMos   = (int)   ($cfg['after_months']    ?? 0);
            $gracePd    = (int)   ($cfg['grace_period']    ?? 0);
            $count      = max(1,  (int) ($cfg['count']     ?? 1));
            $interval   = $cfg['interval'] ?? 'monthly';
            $remainPct  = max(0.0, 1.0 - $resvPct - $contrPct);

            $intervalMos = match ($interval) {
                'quarterly'   => 3,
                'semi-annual' => 6,
                'annual'      => 12,
                default       => 1,
            };

            if ($resvPct > 0) {
                $idx = min($startIdx, 11);
                $schedule[$idx] += $cost * $resvPct;
            }
            if ($contrPct > 0) {
                $idx = min($startIdx + $afterMos, 11);
                $schedule[$idx] += $cost * $contrPct;
            }
            if ($remainPct > 0 && $count > 0) {
                $instalment   = ($cost * $remainPct) / $count;
                $firstInstIdx = $startIdx + $afterMos + $gracePd + $intervalMos;
                for ($i = 0; $i < $count; $i++) {
                    $idx = min($firstInstIdx + $i * $intervalMos, 11);
                    $schedule[$idx] += $instalment;
                }
            }
            return $schedule;
        }

        if ($paymentTerm === 'customize') {
            $customPayment = is_string($row->custom_payment)
                ? json_decode($row->custom_payment, true) ?? []
                : (array) ($row->custom_payment ?? []);
            $tranches = $customPayment['tranches'] ?? [];

            foreach ($tranches as $t) {
                $pct  = (float) ($t['rate'] ?? 0) / 100;
                $days = (int)   ($t['days'] ?? 0);
                if ($pct <= 0) continue;
                $offsetMos = (int) floor($days / 30);
                $idx       = min($startIdx + $offsetMos, 11);
                $schedule[$idx] += $cost * $pct;
            }
            return $schedule;
        }

        return $schedule;
    }

    // ─────────────────────────────────────────────────────────────────
    //  DEBT SCHEDULE — equal-principal, declining-balance interest
    //  Returns array[month_index => ['principal' => x, 'interest' => y]]
    // ─────────────────────────────────────────────────────────────────
    private function buildDebtSchedule(
        float  $debtAmount,
        float  $annualRatePct,
        int    $tenorMonths,
        string $repayStartYm,
        array  $studyMonths
    ): array {
        if ($debtAmount <= 0 || $tenorMonths <= 0) return [];

        $monthlyRate    = $annualRatePct / 100 / 12;
        $principalSlice = $debtAmount / $tenorMonths;
        $startIdx       = array_search($repayStartYm, $studyMonths);
        if ($startIdx === false) $startIdx = 0;

        $schedule = [];
        $balance  = $debtAmount;

        for ($i = 0; $i < $tenorMonths; $i++) {
            $idx = $startIdx + $i;
            if ($idx > 11) break;
            $interest        = round($balance * $monthlyRate, 4);
            $schedule[$idx]  = ['principal' => round($principalSlice, 4), 'interest' => $interest];
            $balance        -= $principalSlice;
        }

        return $schedule;
    }

    // ─────────────────────────────────────────────────────────────────
    //  BUILD 12 YYYY-MM STRINGS from plan's plan_year + start_month
    // ─────────────────────────────────────────────────────────────────
    private function buildMonths(FinancialAnnualPlan $plan): array
    {
        // plan_year = the calendar year the plan covers
        // For now ZAVERO plans are Jan-Dec (fiscal_year_start = 01)
        $year   = (int) $plan->plan_year;
        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[] = sprintf('%04d-%02d', $year, $m);
        }
        return $months;
    }

    // ─────────────────────────────────────────────────────────────────
    private function emptyResult(): array
    {
        $z = array_fill(0, 12, 0.0);
        return ['dep' => $z, 'pay' => $z, 'loan_principal' => $z, 'loan_interest' => $z,
                'annual_dep' => 0, 'annual_pay' => 0, 'annual_lp' => 0, 'annual_li' => 0];
    }
}
