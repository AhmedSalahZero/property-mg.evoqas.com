<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesCompany;

class TenantLedgerController extends Controller
{
    use AuthorizesCompany;

    public function index(Company $company)
    {
        $this->authorizeCompany($company);

        $tenants = DB::table('customers as c')
            ->join('rent_contracts as rc', 'rc.customer_id', '=', 'c.id')
            ->where('rc.company_id', $company->id)
            ->select('c.id', 'c.customer_name')
            ->distinct()
            ->orderBy('c.customer_name')
            ->get();

        return Inertia::render('Reports/TenantLedger', [
            'company' => $company,
            'tenants' => $tenants,
        ]);
    }

    public function data(Request $request, Company $company)
    {
        $this->authorizeCompany($company);

        $validated = $request->validate([
            'tenant_id' => ['required', 'integer'],
            'status'    => ['nullable', 'string', 'in:all,running,expired,terminated'],
        ]);

        $tenantId     = $validated['tenant_id'];
        $statusFilter = $validated['status'] ?? 'all';

        $tenant = DB::table('customers')->where('id', $tenantId)->first();
        if (!$tenant) return response()->json(['error' => 'Tenant not found'], 404);

        $contractsQuery = DB::table('rent_contracts as rc')
            ->leftJoin('properties as p', 'p.id', '=', 'rc.property_id')
            ->leftJoin('property_units as pu', 'pu.id', '=', 'rc.property_unit_id')
            ->where('rc.company_id', $company->id)
            ->where('rc.customer_id', $tenantId);

        if ($statusFilter !== 'all') {
            $contractsQuery->where('rc.status', $statusFilter);
        }

        $contracts = $contractsQuery
            ->select(
                'rc.id',
                'rc.status',
                'rc.start_date',
                'rc.end_date',
                'rc.contract_currency',
                'rc.collection_currency',
                'rc.monthly_rent_amount',
                'rc.min_monthly_rent',
                'rc.insurance_amount',
                'rc.insurance_months',
                'rc.revenue_type',
                'rc.collection_interval_months',
                DB::raw('COALESCE(pu.unit_name, p.property_name) as unit_label'),
                DB::raw('p.nature as property_nature'),
                DB::raw('p.property_name'),
                DB::raw('pu.unit_name'),
            )
            ->orderBy('rc.start_date')
            ->get();

        $contractIds = $contracts->pluck('id')->toArray();

        $collectionsRaw = DB::table('rent_collections')
            ->whereIn('rent_contract_id', $contractIds)
            ->select(
                'rent_contract_id',
                'id',
                'collection_date',
                'period_from',
                'period_to',
                'collection_amount',
                'base_amount',
                'currency',
                'status',
                'collected_date',
                'notes',
            )
            ->orderBy('collection_date')
            ->get()
            ->groupBy('rent_contract_id');

        // Base-currency company (companies.currency) is used for base_amount.
        $baseCurrency = strtoupper($company->currency ?? 'EGP');

        $result = $contracts->map(function ($c) use ($collectionsRaw) {
            $collections    = $collectionsRaw->get($c->id, collect());
            $totalDue       = $collections->sum('base_amount');
            $totalCollected = $collections->where('status', 'collected')->sum('base_amount');
            $outstanding    = $totalDue - $totalCollected;

            return [
                'id'                        => $c->id,
                'status'                    => $c->status,
                'unit_label'                => $c->unit_label,
                'property_name'             => $c->property_name,
                'unit_name'                 => $c->unit_name,
                'property_nature'           => $c->property_nature,
                'start_date'                => $c->start_date,
                'end_date'                  => $c->end_date,
                'contract_currency'         => $c->contract_currency,
                'collection_currency'       => $c->collection_currency,
                'monthly_rent_amount'       => (float) $c->monthly_rent_amount,
                'min_monthly_rent'          => $c->min_monthly_rent ? (float) $c->min_monthly_rent : null,
                'insurance_amount'          => (float) $c->insurance_amount,
                'insurance_months'          => (int) $c->insurance_months,
                'revenue_type'              => $c->revenue_type,
                'collection_interval_months'=> (int) $c->collection_interval_months,
                'total_due'                 => round($totalDue, 2),
                'total_collected'           => round($totalCollected, 2),
                'outstanding'               => round($outstanding, 2),
                'unconverted_count'         => $collections->whereNull('base_amount')->count(),
                'collections'               => $collections->values()->map(fn($col) => [
                    'id'               => $col->id,
                    'collection_date'  => $col->collection_date,
                    'period_from'      => $col->period_from,
                    'period_to'        => $col->period_to,
                    'collection_amount'=> (float) $col->collection_amount,
                    'currency'         => $col->currency,
                    'status'           => $col->status,
                    'collected_date'   => $col->collected_date,
                    'notes'            => $col->notes,
                ])->all(),
            ];
        });

        return response()->json([
            'tenant'               => ['id' => $tenant->id, 'name' => $tenant->customer_name],
            'base_currency'        => $baseCurrency,
            'contracts'            => $result->values(),
            'grand_total_due'      => round($result->sum('total_due'), 2),
            'grand_total_collected'=> round($result->sum('total_collected'), 2),
            'grand_outstanding'    => round($result->sum('outstanding'), 2),
            'grand_insurance'      => round($result->sum('insurance_amount'), 2),
            'unconverted_count'    => (int) $result->sum('unconverted_count'),
        ]);
    }
}
