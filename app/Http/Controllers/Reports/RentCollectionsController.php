<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesCompany;

class RentCollectionsController extends Controller
{
    use AuthorizesCompany;

    public function index(Company $company)
    {
        $this->authorizeCompany($company);

        return Inertia::render('Reports/RentCollections', [
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

        $rows = DB::table('rent_collections as rc')
            ->join('rent_contracts as con', 'con.id', '=', 'rc.rent_contract_id')
            ->join('customers as cu', 'cu.id', '=', 'con.customer_id')
            ->join('properties as p', 'p.id', '=', 'con.property_id')
            ->leftJoin('property_units as pu', 'pu.id', '=', 'con.property_unit_id')
            ->leftJoin('property_types as pt_unit', 'pt_unit.id', '=', 'pu.property_type_id')
            ->leftJoin('property_types as pt_prop', 'pt_prop.id', '=', 'p.property_type_id')
            ->where('rc.company_id', $company->id)
            ->whereBetween('rc.collection_date', [$start, $end])
            ->select(
                'rc.id',
                'rc.collection_date',
                'rc.period_from',
                'rc.period_to',
                'rc.collection_amount',
                'rc.base_amount',
                'rc.currency',
                'rc.status',
                'rc.collected_date',
                'rc.notes',
                DB::raw('cu.customer_name as tenant_name'),
                DB::raw('p.property_name'),
                DB::raw('pu.unit_name'),
                DB::raw('COALESCE(pu.unit_name, p.property_name) as unit_label'),
                DB::raw('COALESCE(pt_unit.type_name, pt_prop.type_name, "—") as unit_type'),
            )
            ->orderBy('rc.collection_date')
            ->orderBy('p.property_name')
            ->get();

        // Totals are in the company's base currency (base_amount, set at write
        // time by CurrencyConversionService — audit fix C4). Rows still awaiting
        // an FX rate have a null base_amount and are excluded from totals rather
        // than assumed 1:1; unconverted_count lets the UI flag that to the user.
        return response()->json([
            'rows'              => $rows->values(),
            'base_currency'     => strtoupper($company->currency ?: 'EGP'),
            'total_due'         => round($rows->sum('base_amount'), 2),
            'total_collected'   => round($rows->where('status', 'collected')->sum('base_amount'), 2),
            'total_overdue'     => round($rows->where('status', 'overdue')->sum('base_amount'), 2),
            'total_pending'     => round($rows->where('status', 'pending')->sum('base_amount'), 2),
            'unconverted_count' => $rows->whereNull('base_amount')->count(),
        ]);
    }
}
