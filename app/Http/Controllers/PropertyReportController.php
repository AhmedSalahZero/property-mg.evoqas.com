<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Property;
use App\Services\CurrencyConversionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesCompany;

class PropertyReportController extends Controller
{
    use AuthorizesCompany;

    public function index(Company $company, Property $property)
    {
        $this->authorizeCompany($company);

        return Inertia::render('Properties/Reports/Index', [
            'company' => $company,
            'property' => $property,
        ]);
    }

    public function rentExpenses(Company $company, Property $property)
    {
        $this->authorizeCompany($company);

        return Inertia::render('Properties/Reports/RentExpenses', [
            'company' => $company,
            'property' => $property,
            'defaultStartDate' => now()->startOfYear()->format('Y-m-d'),
            'defaultEndDate' => now()->endOfMonth()->format('Y-m-d'),
            'baseCurrency' => strtoupper($company->currency ?: 'EGP'),
        ]);
    }

    public function rentExpensesData(Request $request, Company $company, Property $property)
    {
        $this->authorizeCompany($company);

        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'currency' => ['nullable', 'string', 'max:10'],
        ]);

        $startDate = Carbon::parse($data['start_date'])->startOfDay();
        $endDate = Carbon::parse($data['end_date'])->endOfDay();

        $months = [];
        $cursor = $startDate->copy()->startOfMonth();
        $last = $endDate->copy()->startOfMonth();
        while ($cursor->lte($last)) {
            $months[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        $baseCurrency = strtoupper($company->currency ?: 'EGP');
        $fx = app(CurrencyConversionService::class);

        // A specific currency picked from the dropdown → raw, unconverted
        // figures for that currency only. Otherwise → main functional
        // currency, every currency converted at today's LATEST rate and
        // summed — same rule as the Dashboard and Cash Forecast.
        $requested = $request->input('currency') ? strtoupper($request->input('currency')) : null;
        $viewCurrency = ($requested && $requested !== $baseCurrency) ? $requested : null;
        $isFunctionalView = $viewCurrency === null;
        $unconvertedCurrencies = [];

        // ═══════════════════════════════════════════════════════════════
        // ACCRUAL BASIS — powers the "Rent / Expenses Report" tab.
        // Confirmed July 2026 session: this tab shows what's COMMITTED —
        // rent earned per the revenue schedule, expenses incurred per their
        // expense_date — regardless of whether cash has actually moved yet.
        // A brand-new unpaid expense shows up here immediately; it will NOT
        // show in the Cashflow tab below until it's actually paid.
        // ═══════════════════════════════════════════════════════════════
        $rentAccrualQuery = DB::table('rent_revenues as rr')
            ->join('rent_contracts as c', 'c.id', '=', 'rr.rent_contract_id')
            ->where('rr.company_id', $company->id)
            ->where('c.property_id', $property->id)
            ->whereBetween('rr.revenue_date', [$startDate, $endDate]);
        if ($viewCurrency) {
            $rentAccrualQuery->where('rr.currency', $viewCurrency);
        }
        $rentAccrualRows = $rentAccrualQuery
            ->select(
                DB::raw('DATE_FORMAT(rr.revenue_date, "%Y-%m") as month'),
                'rr.currency',
                DB::raw('SUM(rr.revenue_amount) as amount')
            )
            ->groupBy(DB::raw('DATE_FORMAT(rr.revenue_date, "%Y-%m")'), 'rr.currency')
            ->get();

        $directAccrualQuery = DB::table('property_expenses as pe')
            ->where('pe.company_id', $company->id)
            ->where('pe.property_id', $property->id)
            ->whereBetween('pe.expense_date', [$startDate, $endDate]);
        if ($viewCurrency) {
            $directAccrualQuery->where('pe.currency', $viewCurrency);
        }
        $directAccrualRows = $directAccrualQuery
            ->select(
                DB::raw('DATE_FORMAT(pe.expense_date, "%Y-%m") as month'),
                'pe.currency',
                DB::raw('SUM(pe.expense_amount) as amount')
            )
            ->groupBy(DB::raw('DATE_FORMAT(pe.expense_date, "%Y-%m")'), 'pe.currency')
            ->get();

        // allocated_amount is already this property's committed SHARE
        // (amount × allocation_pct, computed once at allocation time) — no
        // extra multiplication needed here, unlike the cash-basis query
        // below which has to apply the % to each individual payment.
        $corporateAccrualQuery = DB::table('corporate_expense_allocations as cea')
            ->join('corporate_expenses as ce', 'ce.id', '=', 'cea.corporate_expense_id')
            ->where('ce.company_id', $company->id)
            ->where('cea.property_id', $property->id)
            ->whereBetween('ce.expense_date', [$startDate, $endDate]);
        if ($viewCurrency) {
            $corporateAccrualQuery->where('ce.currency', $viewCurrency);
        }
        $corporateAccrualRows = $corporateAccrualQuery
            ->select(
                DB::raw('DATE_FORMAT(ce.expense_date, "%Y-%m") as month'),
                'ce.currency',
                DB::raw('SUM(cea.allocated_amount) as amount')
            )
            ->groupBy(DB::raw('DATE_FORMAT(ce.expense_date, "%Y-%m")'), 'ce.currency')
            ->get();

        $rentAccrualByMonth      = $this->foldByMonth($rentAccrualRows, $company->id, $baseCurrency, $isFunctionalView, $fx, $unconvertedCurrencies);
        $directAccrualByMonth    = $this->foldByMonth($directAccrualRows, $company->id, $baseCurrency, $isFunctionalView, $fx, $unconvertedCurrencies);
        $corporateAccrualByMonth = $this->foldByMonth($corporateAccrualRows, $company->id, $baseCurrency, $isFunctionalView, $fx, $unconvertedCurrencies);

        $totalExpensesAccrualByMonth = [];
        foreach ($months as $month) {
            $totalExpensesAccrualByMonth[$month] = round((float) ($directAccrualByMonth[$month] ?? 0) + (float) ($corporateAccrualByMonth[$month] ?? 0), 2);
        }

        // ═══════════════════════════════════════════════════════════════
        // CASH BASIS — powers the "Cashflow Report" tab (fix for audit H3,
        // unchanged from before). Only money that has ACTUALLY moved counts.
        // ═══════════════════════════════════════════════════════════════
        $rentCashQuery = DB::table('rent_collections as rc')
            ->join('rent_contracts as c', 'c.id', '=', 'rc.rent_contract_id')
            ->where('rc.company_id', $company->id)
            ->where('c.property_id', $property->id)
            ->where('rc.status', 'collected')
            ->whereNotNull('rc.collected_date')
            ->whereBetween('rc.collected_date', [$startDate, $endDate]);
        if ($viewCurrency) {
            $rentCashQuery->where('rc.currency', $viewCurrency);
        }
        $rentCashRows = $rentCashQuery
            ->select(
                DB::raw('DATE_FORMAT(rc.collected_date, "%Y-%m") as month'),
                'rc.currency',
                DB::raw('SUM(rc.collection_amount) as amount')
            )
            ->groupBy(DB::raw('DATE_FORMAT(rc.collected_date, "%Y-%m")'), 'rc.currency')
            ->get();

        $directCashQuery = DB::table('property_expense_payments as pep')
            ->join('property_expenses as pe', 'pe.id', '=', 'pep.property_expense_id')
            ->where('pep.company_id', $company->id)
            ->where('pe.property_id', $property->id)
            ->whereBetween('pep.payment_date', [$startDate, $endDate]);
        if ($viewCurrency) {
            $directCashQuery->where('pe.currency', $viewCurrency);
        }
        $directCashRows = $directCashQuery
            ->select(
                DB::raw('DATE_FORMAT(pep.payment_date, "%Y-%m") as month'),
                'pe.currency',
                DB::raw('SUM(pep.amount) as amount')
            )
            ->groupBy(DB::raw('DATE_FORMAT(pep.payment_date, "%Y-%m")'), 'pe.currency')
            ->get();

        $corporateCashQuery = DB::table('corporate_expense_payments as cep')
            ->join('corporate_expenses as ce', 'ce.id', '=', 'cep.corporate_expense_id')
            ->join('corporate_expense_allocations as cea', 'cea.corporate_expense_id', '=', 'ce.id')
            ->where('cep.company_id', $company->id)
            ->where('cea.property_id', $property->id)
            ->whereBetween('cep.payment_date', [$startDate, $endDate]);
        if ($viewCurrency) {
            $corporateCashQuery->where('ce.currency', $viewCurrency);
        }
        $corporateCashRows = $corporateCashQuery
            ->select(
                DB::raw('DATE_FORMAT(cep.payment_date, "%Y-%m") as month'),
                'ce.currency',
                DB::raw('SUM(cep.amount * cea.allocation_pct / 100) as amount')
            )
            ->groupBy(DB::raw('DATE_FORMAT(cep.payment_date, "%Y-%m")'), 'ce.currency')
            ->get();

        $rentCashByMonth      = $this->foldByMonth($rentCashRows, $company->id, $baseCurrency, $isFunctionalView, $fx, $unconvertedCurrencies);
        $directCashByMonth    = $this->foldByMonth($directCashRows, $company->id, $baseCurrency, $isFunctionalView, $fx, $unconvertedCurrencies);
        $corporateCashByMonth = $this->foldByMonth($corporateCashRows, $company->id, $baseCurrency, $isFunctionalView, $fx, $unconvertedCurrencies);

        $totalExpensesCashByMonth = [];
        foreach ($months as $month) {
            $totalExpensesCashByMonth[$month] = round((float) ($directCashByMonth[$month] ?? 0) + (float) ($corporateCashByMonth[$month] ?? 0), 2);
        }

        $cashflowByMonth = [];
        $accumulatedByMonth = [];
        $running = 0.0;
        foreach ($months as $month) {
            $rent = (float) ($rentCashByMonth[$month] ?? 0);
            $expense = (float) ($totalExpensesCashByMonth[$month] ?? 0);
            $net = $rent - $expense;
            $running += $net;
            $cashflowByMonth[$month] = round($net, 2);
            $accumulatedByMonth[$month] = round($running, 2);
        }

        return response()->json([
            'months' => $months,
            'baseCurrency' => $baseCurrency,
            'viewCurrency' => $viewCurrency ?? $baseCurrency,
            'isFunctionalView' => $isFunctionalView,
            'availableCurrencies' => $fx->usedCurrencies($company->id, $baseCurrency),
            'unconvertedCurrencies' => array_values(array_unique($unconvertedCurrencies)),

            // Accrual — "Rent / Expenses Report" tab
            'rentAccrualByMonth' => $rentAccrualByMonth,
            'directExpensesAccrualByMonth' => $directAccrualByMonth,
            'corporateExpensesAccrualByMonth' => $corporateAccrualByMonth,
            'totalExpensesAccrualByMonth' => $totalExpensesAccrualByMonth,

            // Cash — "Cashflow Report" tab
            'rentCashByMonth' => $rentCashByMonth,
            'directExpensesCashByMonth' => $directCashByMonth,
            'corporateExpensesCashByMonth' => $corporateCashByMonth,
            'totalExpensesCashByMonth' => $totalExpensesCashByMonth,
            'cashflowByMonth' => $cashflowByMonth,
            'accumulatedByMonth' => $accumulatedByMonth,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // LINE-ITEM DETAIL — fetched on demand when a Direct/Corporate row is
    // expanded (father/son UI). Never eager-loaded with the summary above,
    // same lazy-load pattern used for Corporate Expenses' own allocation
    // breakdown — keeps the main report payload flat regardless of how many
    // transactions exist in the selected period.
    // ═══════════════════════════════════════════════════════════════════
    public function rentExpensesDetail(Request $request, Company $company, Property $property)
    {
        $this->authorizeCompany($company);

        $data = $request->validate([
            'source'     => ['required', 'in:direct,corporate'],
            'basis'      => ['required', 'in:accrual,cash'],
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after_or_equal:start_date'],
            'currency'   => ['nullable', 'string', 'max:10'],
        ]);

        $startDate = Carbon::parse($data['start_date'])->startOfDay();
        $endDate = Carbon::parse($data['end_date'])->endOfDay();
        $currency = $data['currency'] ?? null;

        if ($data['source'] === 'direct' && $data['basis'] === 'accrual') {
            $rows = DB::table('property_expenses as pe')
                ->join('expense_categories as cat', 'cat.id', '=', 'pe.expense_category_id')
                ->join('expense_items as item', 'item.id', '=', 'pe.expense_item_id')
                ->leftJoin('property_expense_payments as pep', 'pep.property_expense_id', '=', 'pe.id')
                ->where('pe.company_id', $company->id)
                ->where('pe.property_id', $property->id)
                ->whereBetween('pe.expense_date', [$startDate, $endDate])
                ->when($currency, fn ($q) => $q->where('pe.currency', $currency))
                ->groupBy('pe.id', 'cat.category_name', 'item.item_name', 'pe.expense_date', 'pe.expense_amount', 'pe.currency', 'pe.status')
                ->select(
                    'pe.id', 'cat.category_name', 'item.item_name', 'pe.expense_date',
                    'pe.expense_amount', 'pe.currency', 'pe.status',
                    DB::raw('COALESCE(SUM(pep.amount), 0) as paid')
                )
                ->orderByDesc('pe.expense_date')
                ->get()
                ->map(fn ($r) => [
                    'category' => $r->category_name,
                    'item'     => $r->item_name,
                    'date'     => $r->expense_date,
                    'amount'   => (float) $r->expense_amount,
                    'currency' => $r->currency,
                    'paid'     => (float) $r->paid,
                    'balance'  => round((float) $r->expense_amount - (float) $r->paid, 2),
                    'status'   => $r->status,
                ]);
        } elseif ($data['source'] === 'direct' && $data['basis'] === 'cash') {
            $rows = DB::table('property_expense_payments as pep')
                ->join('property_expenses as pe', 'pe.id', '=', 'pep.property_expense_id')
                ->join('expense_categories as cat', 'cat.id', '=', 'pe.expense_category_id')
                ->join('expense_items as item', 'item.id', '=', 'pe.expense_item_id')
                ->where('pep.company_id', $company->id)
                ->where('pe.property_id', $property->id)
                ->whereBetween('pep.payment_date', [$startDate, $endDate])
                ->when($currency, fn ($q) => $q->where('pe.currency', $currency))
                ->select('cat.category_name', 'item.item_name', 'pep.payment_date', 'pep.amount', 'pe.currency')
                ->orderByDesc('pep.payment_date')
                ->get()
                ->map(fn ($r) => [
                    'category' => $r->category_name,
                    'item'     => $r->item_name,
                    'date'     => $r->payment_date,
                    'amount'   => (float) $r->amount,
                    'currency' => $r->currency,
                ]);
        } elseif ($data['source'] === 'corporate' && $data['basis'] === 'accrual') {
            $rows = DB::table('corporate_expense_allocations as cea')
                ->join('corporate_expenses as ce', 'ce.id', '=', 'cea.corporate_expense_id')
                ->join('expense_categories as cat', 'cat.id', '=', 'ce.expense_category_id')
                ->join('expense_items as item', 'item.id', '=', 'ce.expense_item_id')
                ->where('ce.company_id', $company->id)
                ->where('cea.property_id', $property->id)
                ->whereBetween('ce.expense_date', [$startDate, $endDate])
                ->when($currency, fn ($q) => $q->where('ce.currency', $currency))
                ->select(
                    'cat.category_name', 'item.item_name', 'ce.expense_date', 'ce.status',
                    'cea.allocation_pct', 'cea.allocated_amount', 'ce.currency'
                )
                ->orderByDesc('ce.expense_date')
                ->get()
                ->map(fn ($r) => [
                    'category'       => $r->category_name,
                    'item'           => $r->item_name,
                    'date'           => $r->expense_date,
                    'allocation_pct' => (float) $r->allocation_pct,
                    'amount'         => (float) $r->allocated_amount,
                    'currency'       => $r->currency,
                    'status'         => $r->status,
                ]);
        } else { // corporate, cash
            $rows = DB::table('corporate_expense_payments as cep')
                ->join('corporate_expenses as ce', 'ce.id', '=', 'cep.corporate_expense_id')
                ->join('corporate_expense_allocations as cea', 'cea.corporate_expense_id', '=', 'ce.id')
                ->join('expense_categories as cat', 'cat.id', '=', 'ce.expense_category_id')
                ->join('expense_items as item', 'item.id', '=', 'ce.expense_item_id')
                ->where('cep.company_id', $company->id)
                ->where('cea.property_id', $property->id)
                ->whereBetween('cep.payment_date', [$startDate, $endDate])
                ->when($currency, fn ($q) => $q->where('ce.currency', $currency))
                ->select(
                    'cat.category_name', 'item.item_name', 'cep.payment_date',
                    'cea.allocation_pct', 'cep.amount', 'ce.currency'
                )
                ->orderByDesc('cep.payment_date')
                ->get()
                ->map(fn ($r) => [
                    'category'       => $r->category_name,
                    'item'           => $r->item_name,
                    'date'           => $r->payment_date,
                    'allocation_pct' => (float) $r->allocation_pct,
                    // this unit's apportioned share of THIS payment
                    'amount'         => round((float) $r->amount * (float) $r->allocation_pct / 100, 2),
                    'currency'       => $r->currency,
                ]);
        }

        return response()->json(['rows' => $rows]);
    }

    /**
     * Same fold-by-currency pattern used in CashForecastController: raw sum
     * for a single selected currency, or every currency converted at the
     * latest rate and summed for the functional view.
     */
    private function foldByMonth($rows, int $companyId, string $baseCurrency, bool $isFunctionalView, CurrencyConversionService $fx, array &$unconvertedCurrencies): array
    {
        $rateCache = [];
        if ($isFunctionalView) {
            foreach (collect($rows)->pluck('currency')->unique() as $currency) {
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
                    continue;
                }
                $amount *= $rate;
            }

            $result[$row->month] = round(($result[$row->month] ?? 0) + $amount, 2);
        }

        return $result;
    }
}