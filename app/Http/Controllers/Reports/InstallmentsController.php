<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesCompany;

class InstallmentsController extends Controller
{
    use AuthorizesCompany;

    public function index(Company $company)
    {
        $this->authorizeCompany($company);

        return Inertia::render('Reports/Installments', [
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

        $rows = DB::table('property_installment_dues as pid')
            ->join('properties as p', 'p.id', '=', 'pid.property_id')
            ->leftJoin('property_types as pt', 'pt.id', '=', 'p.property_type_id')
            ->where('pid.company_id', $company->id)
            ->whereBetween('pid.due_date', [$start, $end])
            ->select(
                'pid.id',
                'pid.due_date',
                'pid.due_type',
                'pid.amount',
                'pid.base_amount',
                'pid.currency',
                'pid.status',
                'pid.paid_date',
                'pid.notes',
                DB::raw('p.property_name'),
                DB::raw('COALESCE(pt.type_name, "—") as unit_type'),
            )
            ->orderBy('pid.due_date')
            ->orderBy('p.property_name')
            ->get();

        return response()->json([
            'rows'              => $rows->values(),
            'base_currency'     => strtoupper($company->currency ?: 'EGP'),
            'total_due'         => round($rows->sum('base_amount'), 2),
            'total_paid'        => round($rows->where('status', 'paid')->sum('base_amount'), 2),
            'total_overdue'     => round($rows->where('status', 'overdue')->sum('base_amount'), 2),
            'total_pending'     => round($rows->where('status', 'pending')->sum('base_amount'), 2),
            'unconverted_count' => $rows->whereNull('base_amount')->count(),
        ]);
    }
}
