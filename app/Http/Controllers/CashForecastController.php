<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Company;
use App\Models\PropertyExpense;
use App\Models\CorporateExpense;
use App\Services\CurrencyConversionService;
use App\Services\ExpensePaymentScheduleService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Controllers\Concerns\AuthorizesCompany;

/**
 * Fix for the follow-up currency-filter requirement: every view here can be
 * shown either
 *   (a) in the company's main functional currency — every currency's cash
 *       flows converted and summed together, always using the LATEST FX
 *       rate on file (not the rate that applied on the transaction's own
 *       date), so the picture always reflects "what is this really worth
 *       today" — or
 *   (b) filtered to one specific currency — showing ONLY that currency's
 *       own raw, unconverted cash in/out. A contract billed in USD but
 *       actually collected in EGP correctly shows nothing in the USD view,
 *       since collection_currency (not contract_currency) is what's real.
 */
class CashForecastController extends Controller
{
    use AuthorizesCompany;

    public function index(Company $company)
    {
        $this->authorizeCompany($company);

        // Default period: current month → +11 months
        $fromDefault = Carbon::now()->startOfMonth()->format('Y-m');
        $toDefault   = Carbon::now()->startOfMonth()->addMonths(11)->format('Y-m');

        return Inertia::render('Properties/CashForecast', [
            'company'     => $company,
            'fromDefault' => $fromDefault,
            'toDefault'   => $toDefault,
            'baseCurrency' => strtoupper($company->currency ?: 'EGP'),
        ]);
    }

    public function data(Request $request, Company $company)
    {
        $this->authorizeCompany($company);

        $from = $request->input('from'); // e.g. "2026-04"
        $to   = $request->input('to');   // e.g. "2027-03"

        if (!$from || !$to) {
            return response()->json(['error' => 'Missing period'], 422);
        }

        $fromDate = Carbon::createFromFormat('Y-m', $from)->startOfMonth();
        $toDate   = Carbon::createFromFormat('Y-m', $to)->endOfMonth();

        $baseCurrency   = strtoupper($company->currency ?: 'EGP');
        $fx             = app(CurrencyConversionService::class);
        $usedCurrencies = $fx->usedCurrencies($company->id, $baseCurrency);

        // A specific currency was picked from the dropdown → raw, single-
        // currency, unconverted view. Anything else (default / "functional")
        // → every currency converted at today's latest rate and summed.
        $viewCurrency  = $request->input('currency');
        $singleCurrency = $viewCurrency && strtoupper($viewCurrency) !== $baseCurrency
            ? strtoupper($viewCurrency)
            : null;
        // Note: even when $viewCurrency === $baseCurrency explicitly, that's
        // still effectively "functional view" (base-currency rows need no
        // conversion anyway, and other currencies still get converted in).
        $isFunctionalView = $singleCurrency === null;

        // Build list of months in range
        $months = [];
        $cursor = $fromDate->copy();
        while ($cursor->lte($toDate)) {
            $months[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        $unconvertedCurrencies = [];

        // ── RENT COLLECTIONS ─────────────────────────────────────────────
        $collectionsQuery = DB::table('rent_collections as rc')
            ->join('rent_contracts as rct', 'rc.rent_contract_id', '=', 'rct.id')
            ->join('properties as p', 'rct.property_id', '=', 'p.id')
            ->leftJoin('property_units as pu', 'rct.property_unit_id', '=', 'pu.id')
            ->leftJoin('property_types as pt_unit', 'pu.property_type_id', '=', 'pt_unit.id')
            ->leftJoin('property_types as pt_prop', 'p.property_type_id', '=', 'pt_prop.id')
            ->where('rc.company_id', $company->id)
            ->whereBetween('rc.collection_date', [$fromDate, $toDate]);

        if ($singleCurrency) {
            $collectionsQuery->where('rc.currency', $singleCurrency);
        }

        $collections = $collectionsQuery
            ->select(
                DB::raw('COALESCE(pt_unit.type_name, pt_prop.type_name, "No Type") as unit_type'),
                DB::raw('COALESCE(pu.unit_name, p.property_name) as unit_name'),
                DB::raw('DATE_FORMAT(rc.collection_date, "%Y-%m") as month'),
                'rc.currency',
                DB::raw('SUM(rc.collection_amount) as amount')
            )
            ->groupBy(
                DB::raw('COALESCE(pt_unit.type_name, pt_prop.type_name, "No Type")'),
                DB::raw('COALESCE(pu.unit_name, p.property_name)'),
                DB::raw('DATE_FORMAT(rc.collection_date, "%Y-%m")'),
                'rc.currency'
            )
            ->orderBy('unit_type')
            ->orderBy('unit_name')
            ->get();

        $rentByTypeUnit = $this->foldCurrencyRows(
            $collections, ['unit_type', 'unit_name', 'month'], $company->id, $baseCurrency, $isFunctionalView, $fx, $unconvertedCurrencies
        );

        // ── INSTALLMENT PAYMENTS ──────────────────────────────────────────
        $installmentsQuery = DB::table('property_installment_dues as pid')
            ->join('properties as p', 'pid.property_id', '=', 'p.id')
            ->leftJoin('property_types as pt', 'p.property_type_id', '=', 'pt.id')
            ->where('pid.company_id', $company->id)
            ->whereIn('pid.status', ['pending', 'overdue'])
            ->whereBetween('pid.due_date', [$fromDate, $toDate]);

        if ($singleCurrency) {
            $installmentsQuery->where('pid.currency', $singleCurrency);
        }

        $installments = $installmentsQuery
            ->select(
                DB::raw('COALESCE(pt.type_name, "No Type") as unit_type'),
                'p.property_name as unit_name',
                DB::raw('DATE_FORMAT(pid.due_date, "%Y-%m") as month'),
                'pid.currency',
                DB::raw('SUM(pid.amount) as amount')
            )
            ->groupBy(
                DB::raw('COALESCE(pt.type_name, "No Type")'),
                'p.property_name',
                DB::raw('DATE_FORMAT(pid.due_date, "%Y-%m")'),
                'pid.currency'
            )
            ->orderBy('unit_type')
            ->orderBy('unit_name')
            ->get();

        $installByTypeUnit = $this->foldCurrencyRows(
            $installments, ['unit_type', 'unit_name', 'month'], $company->id, $baseCurrency, $isFunctionalView, $fx, $unconvertedCurrencies
        );

        // ── SALE RECEIVABLES (Cash In) ──────────────────────────────────
        // Phase 2 of the Record Sale feature (confirmed July 2026) — money
        // still owed BY a buyer after a unit/property sale on installments.
        // Mirrors the Installment Payments query above exactly, just in
        // reverse direction (Cash In here, vs Cash Out there) and sourced
        // from property_sale_dues instead of property_installment_dues.
        $saleReceivablesQuery = DB::table('property_sale_dues as psd')
            ->join('property_sales as ps', 'psd.property_sale_id', '=', 'ps.id')
            ->join('properties as p', 'ps.property_id', '=', 'p.id')
            ->leftJoin('property_units as pu', 'ps.property_unit_id', '=', 'pu.id')
            ->leftJoin('property_types as pt_unit', 'pu.property_type_id', '=', 'pt_unit.id')
            ->leftJoin('property_types as pt_prop', 'p.property_type_id', '=', 'pt_prop.id')
            ->where('psd.company_id', $company->id)
            ->whereIn('psd.status', ['pending', 'overdue'])
            ->whereBetween('psd.due_date', [$fromDate, $toDate]);

        if ($singleCurrency) {
            $saleReceivablesQuery->where('psd.currency', $singleCurrency);
        }

        $saleReceivables = $saleReceivablesQuery
            ->select(
                DB::raw('COALESCE(pt_unit.type_name, pt_prop.type_name, "No Type") as unit_type'),
                DB::raw('COALESCE(pu.unit_name, p.property_name) as unit_name'),
                DB::raw('DATE_FORMAT(psd.due_date, "%Y-%m") as month'),
                'psd.currency',
                DB::raw('SUM(psd.amount) as amount')
            )
            ->groupBy(
                DB::raw('COALESCE(pt_unit.type_name, pt_prop.type_name, "No Type")'),
                DB::raw('COALESCE(pu.unit_name, p.property_name)'),
                DB::raw('DATE_FORMAT(psd.due_date, "%Y-%m")'),
                'psd.currency'
            )
            ->orderBy('unit_type')
            ->orderBy('unit_name')
            ->get();

        $saleReceivablesByTypeUnit = $this->foldCurrencyRows(
            $saleReceivables, ['unit_type', 'unit_name', 'month'], $company->id, $baseCurrency, $isFunctionalView, $fx, $unconvertedCurrencies
        );

        // ── EXPENSE PAYMENTS ──────────────────────────────────────────────
        // Fix for audit H4 — this previously only ever pulled from
        // property_expense_payments.payment_date (cash already actually
        // paid), so any future month showed $0 expense unless someone had
        // manually pre-dated a payment — defeating the whole point of a
        // forward-looking cash forecast. Now it's a blend, split at the end
        // of the current month:
        //   - months up to and including the current month: actual cash
        //     paid (payment_date) — unchanged, true cash-basis history.
        //   - months strictly after the current month: the still-OUTSTANDING
        //     balance (expense_amount minus whatever's been paid so far) of
        //     expenses not yet fully paid, placed in the month of their
        //     expense_date — a genuine forward-looking commitment, not
        //     history that hasn't happened yet.
        $currentMonthEnd = Carbon::now()->endOfMonth();

        $expensePaymentsQuery = DB::table('property_expense_payments as pep')
            ->join('property_expenses as pe', 'pep.property_expense_id', '=', 'pe.id')
            ->join('expense_items as ei', 'pe.expense_item_id', '=', 'ei.id')
            ->where('pep.company_id', $company->id)
            ->whereBetween('pep.payment_date', [$fromDate, $toDate])
            ->where('pep.payment_date', '<=', $currentMonthEnd);

        if ($singleCurrency) {
            $expensePaymentsQuery->where('pe.currency', $singleCurrency);
        }

        $expensePayments = $expensePaymentsQuery
            ->select(
                'ei.item_name',
                DB::raw('DATE_FORMAT(pep.payment_date, "%Y-%m") as month'),
                'pe.currency',
                DB::raw('SUM(pep.amount) as amount')
            )
            ->groupBy('ei.item_name', DB::raw('DATE_FORMAT(pep.payment_date, "%Y-%m")'), 'pe.currency')
            ->orderBy('ei.item_name')
            ->get();

        $forecastExpensesQuery = PropertyExpense::where('company_id', $company->id)
            ->where('status', '!=', 'fully_paid')
            ->with(['expenseItem:id,item_name', 'paymentSchedule', 'payments']);
        if ($singleCurrency) {
            $forecastExpensesQuery->where('currency', $singleCurrency);
        }
        $notFullyPaidExpenses = $forecastExpensesQuery->get();

        // Fix — Payment Schedule feature: an expense with a forecasted
        // Payment Schedule (see ExpensePaymentScheduleService) uses that
        // schedule's own still-outstanding amount and date per row, instead
        // of guessing a single lump date from expense_date. This also
        // closes the earlier gap where an overdue-but-unpaid expense with
        // no future date at all simply never appeared in the forecast —
        // every schedule row now always has a real forecasted date, past or
        // future, and outstandingRows() only returns the portion actually
        // still unpaid. Expenses with NO schedule rows at all (created
        // before this feature existed — none of your current test data
        // will have one after you start using the repeater going forward)
        // keep using the exact old fallback: forward months only, full
        // outstanding balance placed at expense_date's own month.
        $scheduleService = app(ExpensePaymentScheduleService::class);
        $forecastRows = collect();

        foreach ($notFullyPaidExpenses as $expense) {
            if ($expense->paymentSchedule->isNotEmpty()) {
                foreach ($scheduleService->outstandingRows($expense) as $row) {
                    if ($row['forecasted_date'] < $fromDate || $row['forecasted_date'] > $toDate) {
                        continue; // stay inside the requested window, same as every other query on this page
                    }
                    $forecastRows->push((object) [
                        'item_name' => $expense->expenseItem?->item_name,
                        'month'     => Carbon::parse($row['forecasted_date'])->format('Y-m'),
                        'currency'  => $row['currency'],
                        'amount'    => $row['amount'],
                    ]);
                }
                continue;
            }

            $expDate = $expense->expense_date;
            if (!$expDate) continue;
            $expDateStr = $expDate->toDateString();
            if ($expDateStr < $fromDate || $expDateStr > $toDate) continue;
            if (!$expDate->gt($currentMonthEnd)) continue;

            $outstanding = max(0, (float) $expense->expense_amount - $expense->totalPaid());
            if ($outstanding <= 0) continue;

            $forecastRows->push((object) [
                'item_name' => $expense->expenseItem?->item_name,
                'month'     => $expDate->format('Y-m'),
                'currency'  => $expense->currency,
                'amount'    => $outstanding,
            ]);
        }

        $expenseByItem = $this->foldCurrencyRows(
            $expensePayments->merge($forecastRows), ['item_name', 'month'], $company->id, $baseCurrency, $isFunctionalView, $fx, $unconvertedCurrencies
        );

        // ── CORPORATE EXPENSES (Cash Out) ─────────────────────────────────
        // Wired in per the July 2026 session — same blend pattern as the
        // fix for audit H4 above, sourced from Corporate Expenses instead
        // of Property Expenses. Shown as its own line (not merged into
        // "Expense Payments") to keep the Direct/Corporate distinction
        // visible everywhere else in the app. Amounts here are the FULL
        // company-wide payment/outstanding amount — unlike the per-property
        // Rent vs Expenses report, Cash Forecast is a company-wide cash
        // tool, so there's no reason to apportion by allocation_pct here;
        // the whole payment is a real cash movement regardless of how it's
        // later allocated across units for NOI/accounting purposes.
        $corpExpensePaymentsQuery = DB::table('corporate_expense_payments as cep')
            ->join('corporate_expenses as ce', 'cep.corporate_expense_id', '=', 'ce.id')
            ->join('expense_items as ei', 'ce.expense_item_id', '=', 'ei.id')
            ->where('cep.company_id', $company->id)
            ->whereBetween('cep.payment_date', [$fromDate, $toDate])
            ->where('cep.payment_date', '<=', $currentMonthEnd);

        if ($singleCurrency) {
            $corpExpensePaymentsQuery->where('ce.currency', $singleCurrency);
        }

        $corpExpensePayments = $corpExpensePaymentsQuery
            ->select(
                'ei.item_name',
                DB::raw('DATE_FORMAT(cep.payment_date, "%Y-%m") as month'),
                'ce.currency',
                DB::raw('SUM(cep.amount) as amount')
            )
            ->groupBy('ei.item_name', DB::raw('DATE_FORMAT(cep.payment_date, "%Y-%m")'), 'ce.currency')
            ->orderBy('ei.item_name')
            ->get();

        $corpForecastExpensesQuery = CorporateExpense::where('company_id', $company->id)
            ->where('status', '!=', 'fully_paid')
            ->with(['expenseItem:id,item_name', 'paymentSchedule', 'payments']);
        if ($singleCurrency) {
            $corpForecastExpensesQuery->where('currency', $singleCurrency);
        }
        $corpNotFullyPaidExpenses = $corpForecastExpensesQuery->get();

        // Same schedule-aware fix as Direct Expenses above — see that
        // block's comment for the full reasoning.
        $corpForecastRows = collect();

        foreach ($corpNotFullyPaidExpenses as $expense) {
            if ($expense->paymentSchedule->isNotEmpty()) {
                foreach ($scheduleService->outstandingRows($expense) as $row) {
                    if ($row['forecasted_date'] < $fromDate || $row['forecasted_date'] > $toDate) {
                        continue;
                    }
                    $corpForecastRows->push((object) [
                        'item_name' => $expense->expenseItem?->item_name,
                        'month'     => Carbon::parse($row['forecasted_date'])->format('Y-m'),
                        'currency'  => $row['currency'],
                        'amount'    => $row['amount'],
                    ]);
                }
                continue;
            }

            $expDate = $expense->expense_date;
            if (!$expDate) continue;
            $expDateStr = $expDate->toDateString();
            if ($expDateStr < $fromDate || $expDateStr > $toDate) continue;
            if (!$expDate->gt($currentMonthEnd)) continue;

            $outstanding = max(0, (float) $expense->expense_amount - $expense->totalPaid());
            if ($outstanding <= 0) continue;

            $corpForecastRows->push((object) [
                'item_name' => $expense->expenseItem?->item_name,
                'month'     => $expDate->format('Y-m'),
                'currency'  => $expense->currency,
                'amount'    => $outstanding,
            ]);
        }

        $corporateExpenseByItem = $this->foldCurrencyRows(
            $corpExpensePayments->merge($corpForecastRows), ['item_name', 'month'], $company->id, $baseCurrency, $isFunctionalView, $fx, $unconvertedCurrencies
        );

        // ── MANAGEMENT FEES (Cash Out based on collections) ──────────────────
        $managementFeesQuery = DB::table('rent_collections as rc')
            ->join('rent_contracts as rct', 'rc.rent_contract_id', '=', 'rct.id')
            ->where('rc.company_id', $company->id)
            ->whereBetween('rc.collection_date', [$fromDate, $toDate])
            ->where('rct.has_management_fees', 1)
            ->whereNotNull('rct.management_fee_expense_rate');

        if ($singleCurrency) {
            $managementFeesQuery->where('rc.currency', $singleCurrency);
        }

        $managementFees = $managementFeesQuery
            ->select(
                DB::raw('DATE_FORMAT(rc.collection_date, "%Y-%m") as month'),
                'rc.currency',
                DB::raw('SUM(rc.collection_amount * (rct.management_fee_expense_rate / 100)) as amount')
            )
            ->groupBy(DB::raw('DATE_FORMAT(rc.collection_date, "%Y-%m")'), 'rc.currency')
            ->get();

        $managementFeesFolded = $this->foldCurrencyRows(
            $managementFees, ['month'], $company->id, $baseCurrency, $isFunctionalView, $fx, $unconvertedCurrencies
        );
        // managementFeesByMonth is a flat { month: amount } map, not nested —
        // flatten the single-level fold result accordingly.
        $managementFeesByMonth = [];
        foreach ($managementFeesFolded as $month => $amount) {
            $managementFeesByMonth[$month] = round((float) $amount, 2);
        }

        return response()->json([
            'months'            => $months,
            'baseCurrency'      => $baseCurrency,
            'viewCurrency'      => $singleCurrency ?? $baseCurrency,
            'isFunctionalView'  => $isFunctionalView,
            'availableCurrencies' => $usedCurrencies,
            'unconvertedCurrencies' => array_values(array_unique($unconvertedCurrencies)),
            'rentByTypeUnit'    => $rentByTypeUnit,
            'saleReceivablesByTypeUnit' => $saleReceivablesByTypeUnit,
            'installByTypeUnit' => $installByTypeUnit,
            'expenseByItem'     => $expenseByItem,
            'corporateExpenseByItem' => $corporateExpenseByItem,
            'managementFeesByMonth' => $managementFeesByMonth,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // Fix for audit finding H-4 — Salaries / New Hirings / Other
    // Collections / Other Payments were pure in-memory Vue state with no
    // save endpoint anywhere, so every manually-entered row was silently
    // lost on refresh. These two endpoints persist and reload them.
    //
    // One row per company (see cash_forecast_manual_inputs migration);
    // month-keyed data isn't tied to whichever period window happens to be
    // selected, so this is loaded once independent of the from/to picker
    // and the frontend slices out whichever months it's currently showing.
    // ═══════════════════════════════════════════════════════════════════

    public function manualRows(Company $company)
    {
        $this->authorizeCompany($company);

        $row = \App\Models\CashForecastManualInput::where('company_id', $company->id)->first();

        return response()->json([
            'salaries'          => $row?->salaries          ?? (object) [],
            'new_hirings'       => $row?->new_hirings        ?? (object) [],
            'other_collections' => $row?->other_collections  ?? [],
            'other_payments'    => $row?->other_payments     ?? [],
            'updated_at'        => $row?->updated_at?->toIso8601String(),
        ]);
    }

    public function saveManualRows(Request $request, Company $company)
    {
        $this->authorizeCompany($company);

        $data = $request->validate([
            'salaries'                    => 'present|array',
            'salaries.*'                  => 'nullable|numeric',
            'new_hirings'                 => 'present|array',
            'new_hirings.*'               => 'present|array',
            'new_hirings.*.*.title'       => 'nullable|string|max:255',
            'new_hirings.*.*.amount'      => 'nullable|numeric',
            'other_collections'           => 'present|array',
            'other_collections.*.name'    => 'nullable|string|max:255',
            'other_collections.*.amounts' => 'present|array',
            'other_payments'              => 'present|array',
            'other_payments.*.name'       => 'nullable|string|max:255',
            'other_payments.*.amounts'    => 'present|array',
        ]);

        \App\Models\CashForecastManualInput::updateOrCreate(
            ['company_id' => $company->id],
            [
                'salaries'          => $data['salaries'],
                'new_hirings'       => $data['new_hirings'],
                'other_collections' => $data['other_collections'],
                'other_payments'    => $data['other_payments'],
                'updated_by'        => auth()->id(),
            ]
        );

        return response()->json(['saved' => true, 'saved_at' => now()->toIso8601String()]);
    }

    /**
     * Collapse a result set that includes a `currency` column into a nested
     * structure keyed by the given grouping columns, with the LAST level
     * always being a plain amount.
     *
     * - Single-currency (raw) view: rows are already pre-filtered to one
     *   currency by the caller, so this just sums duplicate keys (there
     *   normally won't be any, but a currency filter still leaves one
     *   currency's rows intact) — no conversion happens.
     * - Functional view: multiple currencies can land on the same key
     *   (e.g. same unit/month but two different contracts in different
     *   currencies) — each currency's amount is converted using the latest
     *   rate and summed into that one cell. A currency with no rate on file
     *   is skipped (added to $unconvertedCurrencies) rather than guessed at.
     *
     * @param  string[]  $groupKeys  ordered list of column names to nest by (currency excluded)
     */
    private function foldCurrencyRows($rows, array $groupKeys, int $companyId, string $baseCurrency, bool $isFunctionalView, CurrencyConversionService $fx, array &$unconvertedCurrencies): array
    {
        // Pre-fetch the latest rate for every distinct currency present, once,
        // rather than per row — a handful of cache-backed lookups regardless
        // of how many rows there are.
        $rateCache = [];
        if ($isFunctionalView) {
            foreach ($rows->pluck('currency')->unique() as $currency) {
                $currency = strtoupper($currency);
                $rateCache[$currency] = $currency === $baseCurrency ? 1.0 : $fx->latestRate($companyId, $currency);
            }
        }

        $result = [];

        foreach ($rows as $row) {
            $amount = (float) $row->amount;

            if ($isFunctionalView) {
                $currency = strtoupper($row->currency);
                $rate = $rateCache[$currency] ?? null;
                if ($rate === null) {
                    $unconvertedCurrencies[] = $currency;
                    continue; // excluded, not guessed at — matches the rest of the app's rule
                }
                $amount = $amount * $rate;
            }

            // Walk/create the nested path for this row's group keys.
            $ref = &$result;
            foreach ($groupKeys as $i => $key) {
                $value = $row->{$key};
                if ($i === count($groupKeys) - 1) {
                    $ref[$value] = ($ref[$value] ?? 0) + $amount;
                } else {
                    if (!isset($ref[$value])) $ref[$value] = [];
                    $ref = &$ref[$value];
                }
            }
            unset($ref);
        }

        return $result;
    }
}