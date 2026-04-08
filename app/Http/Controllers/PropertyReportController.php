<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Property;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PropertyReportController extends Controller
{
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
        ]);
    }

    public function rentExpensesData(Request $request, Company $company, Property $property)
    {
        $this->authorizeCompany($company);

        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
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

        $rentRows = DB::table('rent_collections as rc')
            ->join('rent_contracts as c', 'c.id', '=', 'rc.rent_contract_id')
            ->where('rc.company_id', $company->id)
            ->where('c.property_id', $property->id)
            ->whereBetween('rc.collection_date', [$startDate, $endDate])
            ->select(
                DB::raw('DATE_FORMAT(rc.collection_date, "%Y-%m") as month'),
                DB::raw('SUM(rc.collection_amount) as amount')
            )
            ->groupBy(DB::raw('DATE_FORMAT(rc.collection_date, "%Y-%m")'))
            ->get();

        $expenseRows = DB::table('property_expense_payments as pep')
            ->join('property_expenses as pe', 'pe.id', '=', 'pep.property_expense_id')
            ->where('pep.company_id', $company->id)
            ->where('pe.property_id', $property->id)
            ->whereBetween('pep.payment_date', [$startDate, $endDate])
            ->select(
                DB::raw('DATE_FORMAT(pep.payment_date, "%Y-%m") as month'),
                DB::raw('SUM(pep.amount) as amount')
            )
            ->groupBy(DB::raw('DATE_FORMAT(pep.payment_date, "%Y-%m")'))
            ->get();

        $rentByMonth = [];
        foreach ($rentRows as $row) {
            $rentByMonth[$row->month] = round((float) $row->amount, 2);
        }

        $expensesByMonth = [];
        foreach ($expenseRows as $row) {
            $expensesByMonth[$row->month] = round((float) $row->amount, 2);
        }

        $cashflowByMonth = [];
        $accumulatedByMonth = [];
        $running = 0.0;
        foreach ($months as $month) {
            $rent = (float) ($rentByMonth[$month] ?? 0);
            $expense = (float) ($expensesByMonth[$month] ?? 0);
            $net = $rent - $expense;
            $running += $net;
            $cashflowByMonth[$month] = round($net, 2);
            $accumulatedByMonth[$month] = round($running, 2);
        }

        return response()->json([
            'months' => $months,
            'rentByMonth' => $rentByMonth,
            'expensesByMonth' => $expensesByMonth,
            'cashflowByMonth' => $cashflowByMonth,
            'accumulatedByMonth' => $accumulatedByMonth,
        ]);
    }

    private function authorizeCompany(Company $company): void
    {
        $user = auth()->user();
        if (! $user->is_super_admin && $user->company_id !== $company->id) {
            abort(403);
        }
    }
}
