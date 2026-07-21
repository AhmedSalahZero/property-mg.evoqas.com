<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesCompany;

class ExpenseReportController extends Controller
{
    use AuthorizesCompany;

    public function index(Company $company)
    {
        $this->authorizeCompany($company);

        return Inertia::render('Reports/ExpenseReport', [
            'company'          => $company,
            'defaultStartDate' => now()->startOfMonth()->format('Y-m-d'),
            'defaultEndDate'   => now()->endOfMonth()->format('Y-m-d'),
        ]);
    }

    public function data(Request $request, Company $company)
    {
        $this->authorizeCompany($company);

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $start = Carbon::parse($validated['start_date'])->startOfDay();
        $end   = Carbon::parse($validated['end_date'])->endOfDay();

        // property_expense_payments columns: id, company_id, property_expense_id, payment_date, amount
        // property_expenses columns: id, company_id, property_id, expense_category_id, expense_item_id,
        //                            expense_date, expense_amount, currency, fx_rate, notes, status
        $payments = DB::table('property_expense_payments as pep')
            ->join('property_expenses as pe', 'pe.id', '=', 'pep.property_expense_id')
            ->join('expense_items as ei', 'ei.id', '=', 'pe.expense_item_id')
            ->join('expense_categories as ec', 'ec.id', '=', 'ei.expense_category_id')
            ->join('properties as p', 'p.id', '=', 'pe.property_id')
            ->where('pe.company_id', $company->id)
            ->whereBetween('pep.payment_date', [$start, $end])
            ->select(
                'pep.id as payment_id',
                'pep.payment_date',
                'pep.amount as payment_amount',
                'pep.base_amount',
                'pe.currency',
                'pe.notes as expense_notes',
                'pe.expense_amount',
                'ei.item_name',
                'ec.category_name',
                'p.property_name',
            )
            ->orderBy('ec.category_name')
            ->orderBy('ei.item_name')
            ->orderBy('pep.payment_date')
            ->get();

        // Totals roll up in the company's base currency (base_amount, C4 fix);
        // per-row payment_amount + currency are kept for the transaction detail.
        $baseCurrency = strtoupper($company->currency ?: 'EGP');
        $grandTotal   = round($payments->sum('base_amount'), 2);

        $grouped = [];
        foreach ($payments->groupBy('category_name') as $catName => $catPayments) {
            $items = [];
            foreach ($catPayments->groupBy('item_name') as $itemName => $itemPayments) {
                $items[] = [
                    'item_name' => $itemName,
                    'total'     => round($itemPayments->sum('base_amount'), 2),
                    'payments'  => $itemPayments->map(fn($pay) => [
                        'payment_id'     => $pay->payment_id,
                        'payment_date'   => $pay->payment_date,
                        'property_name'  => $pay->property_name,
                        'payment_amount' => round((float) $pay->payment_amount, 2),
                        'currency'       => $pay->currency,
                        'notes'          => $pay->expense_notes,
                    ])->values()->all(),
                ];
            }
            $grouped[] = [
                'category_name' => $catName,
                'total'         => round($catPayments->sum('base_amount'), 2),
                'items'         => $items,
            ];
        }

        return response()->json([
            'grouped'           => $grouped,
            'grand_total'       => $grandTotal,
            'base_currency'     => $baseCurrency,
            'count'             => $payments->count(),
            'unconverted_count' => $payments->whereNull('base_amount')->count(),
        ]);
    }
}
