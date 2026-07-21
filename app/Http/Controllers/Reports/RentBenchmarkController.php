<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\CurrencyConversionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesCompany;

class RentBenchmarkController extends Controller
{
    use AuthorizesCompany;

    public function __construct(private CurrencyConversionService $fx) {}

    public function index(Company $company)
    {
        $this->authorizeCompany($company);

        // Property types that have at least one running contract in this company
        $types = DB::table('rent_contracts as rc')
            ->join('properties as p', 'p.id', '=', 'rc.property_id')
            ->leftJoin('property_units as pu', 'pu.id', '=', 'rc.property_unit_id')
            ->leftJoin('property_types as pt_unit', 'pt_unit.id', '=', 'pu.property_type_id')
            ->leftJoin('property_types as pt_prop', 'pt_prop.id', '=', 'p.property_type_id')
            ->where('rc.company_id', $company->id)
            ->where('rc.status', 'running')
            ->select(
                DB::raw('COALESCE(pt_unit.id, pt_prop.id) as type_id'),
                DB::raw('COALESCE(pt_unit.type_name, pt_prop.type_name, "—") as type_name'),
            )
            ->distinct()
            ->orderBy('type_name')
            ->get()
            ->filter(fn($t) => $t->type_id !== null)
            ->values();

        return Inertia::render('Reports/RentBenchmark', [
            'company' => $company,
            'types'   => $types,
        ]);
    }

    public function data(Request $request, Company $company)
    {
        $this->authorizeCompany($company);

        $validated = $request->validate([
            'type_id'   => ['required', 'integer'],
            'threshold' => ['required', 'numeric', 'min:1', 'max:100'],
        ]);

        $typeId    = (int) $validated['type_id'];
        $threshold = (float) $validated['threshold']; // e.g. 80

        // Fetch all running contracts for units of the selected type
        $rows = DB::table('rent_contracts as rc')
            ->join('properties as p', 'p.id', '=', 'rc.property_id')
            ->leftJoin('property_units as pu', 'pu.id', '=', 'rc.property_unit_id')
            ->leftJoin('property_types as pt_unit', 'pt_unit.id', '=', 'pu.property_type_id')
            ->leftJoin('property_types as pt_prop', 'pt_prop.id', '=', 'p.property_type_id')
            ->join('customers as cu', 'cu.id', '=', 'rc.customer_id')
            ->where('rc.company_id', $company->id)
            ->where('rc.status', 'running')
            ->where(function ($q) use ($typeId) {
                $q->where('pu.property_type_id', $typeId)
                  ->orWhere(function ($q2) use ($typeId) {
                      $q2->whereNull('rc.property_unit_id')
                         ->where('p.property_type_id', $typeId);
                  });
            })
            ->select(
                'rc.id as contract_id',
                'rc.start_date',
                'rc.end_date',
                'rc.monthly_rent_amount',
                'rc.min_monthly_rent',
                'rc.contract_currency',
                DB::raw('COALESCE(pu.unit_name, p.property_name) as unit_name'),
                DB::raw('p.property_name'),
                DB::raw('COALESCE(p.governorate, "—") as governorate'),
                DB::raw('COALESCE(p.province, "—") as province'),
                DB::raw('COALESCE(pt_unit.type_name, pt_prop.type_name, "—") as type_name'),
                DB::raw('cu.customer_name as tenant_name'),
            )
            ->orderBy('p.governorate')
            ->orderBy('p.province')
            ->orderBy('p.property_name')
            ->get();

        $baseCurrency = strtoupper($company->currency ?: 'EGP');

        // Calculate effective rent basis per row, then convert to the company's
        // base currency using the latest rate on file (live comparison — same
        // "main functional currency" pattern used elsewhere in the app, since
        // this is a point-in-time benchmark, not a stored historical record).
        // Rows in a currency with no rate on file can't be safely compared
        // against others, so they're excluded from the province average and
        // flagged rather than silently treated as 1:1.
        $rows = $rows->map(function ($row) use ($baseCurrency, $company) {
            $row->rent_basis = $row->min_monthly_rent
                ? (float) $row->min_monthly_rent
                : (float) $row->monthly_rent_amount;

            $currency = strtoupper($row->contract_currency ?: $baseCurrency);
            if ($currency === $baseCurrency) {
                $row->rent_basis_base = $row->rent_basis;
            } else {
                $rate = $this->fx->latestRate($company->id, $currency);
                $row->rent_basis_base = $rate !== null ? round($row->rent_basis * $rate, 2) : null;
            }
            return $row;
        });

        // Calculate average rent per province (for benchmark) — base currency only
        $provinceAverages = $rows->whereNotNull('rent_basis_base')->groupBy('province')->map(function ($group) {
            return round($group->avg('rent_basis_base'), 2);
        });

        // Build final rows with recommendation
        $result = $rows->map(function ($row) use ($provinceAverages, $threshold) {
            $avgForProvince = $provinceAverages[$row->province] ?? 0;

            if ($row->rent_basis_base === null) {
                $recommendation = 'FX Rate Missing';
            } else {
                $cutoff         = $avgForProvince * ($threshold / 100);
                $recommendation = ($avgForProvince > 0 && $row->rent_basis_base < $cutoff)
                    ? 'Correction Needed'
                    : 'OK';
            }

            return [
                'contract_id'      => $row->contract_id,
                'property_name'    => $row->property_name,
                'unit_name'        => $row->unit_name,
                'type_name'        => $row->type_name,
                'governorate'      => $row->governorate,
                'province'         => $row->province,
                'tenant_name'      => $row->tenant_name,
                'rent_basis'       => round($row->rent_basis, 2),
                'rent_basis_base'  => $row->rent_basis_base,
                'currency'         => $row->contract_currency,
                'start_date'       => $row->start_date,
                'end_date'         => $row->end_date,
                'avg_province'     => $avgForProvince,
                'recommendation'   => $recommendation,
            ];
        });

        // Build summary per governorate → province — base currency only
        $summary = [];
        foreach ($rows->groupBy('governorate') as $gov => $govRows) {
            $govSummary = [];
            foreach ($govRows->groupBy('province') as $prov => $provRows) {
                $rents = $provRows->pluck('rent_basis_base')->filter(fn($v) => $v !== null);
                $govSummary[] = [
                    'province'    => $prov,
                    'count'       => $rents->count(),
                    'min_rent'    => $rents->count() ? round($rents->min(), 2) : null,
                    'max_rent'    => $rents->count() ? round($rents->max(), 2) : null,
                    'avg_rent'    => $rents->count() ? round($rents->avg(), 2) : null,
                    'flagged'     => $result->where('province', $prov)->where('recommendation', 'Correction Needed')->count(),
                ];
            }
            $summary[] = [
                'governorate' => $gov,
                'provinces'   => $govSummary,
            ];
        }

        return response()->json([
            'rows'              => $result->values(),
            'summary'           => $summary,
            'threshold'         => $threshold,
            'type_name'         => $rows->first()?->type_name ?? '—',
            'base_currency'     => $baseCurrency,
            'total'             => $result->count(),
            'flagged'           => $result->where('recommendation', 'Correction Needed')->count(),
            'unconverted_count' => $rows->whereNull('rent_basis_base')->count(),
        ]);
    }
}
