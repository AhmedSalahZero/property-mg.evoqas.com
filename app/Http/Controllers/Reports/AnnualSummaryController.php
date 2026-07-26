<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesCompany;

class AnnualSummaryController extends Controller
{
    use AuthorizesCompany;

    public function index(Company $company)
    {
        $this->authorizeCompany($company);

        return Inertia::render('Reports/AnnualSummary', [
            'company'     => $company,
            'defaultYear' => now()->year,
        ]);
    }

    public function data(Request $request, Company $company)
    {
        $this->authorizeCompany($company);

        $validated = $request->validate([
            'year'  => ['required', 'integer', 'min:2000', 'max:2100'],
            'scope' => ['required', 'string', 'in:company,unit'],
        ]);

        $year  = (int) $validated['year'];
        $scope = $validated['scope'];

        $yearStart = "{$year}-01-01";
        $yearEnd   = "{$year}-12-31";

        // ── Revenue collected ────────────────────────────────────────────
        $revenueRows = DB::table('rent_collections as rc')
            ->join('rent_contracts as con', 'con.id', '=', 'rc.rent_contract_id')
            ->where('rc.company_id', $company->id)
            ->where('rc.status', 'collected')
            ->whereBetween('rc.collected_date', [$yearStart, $yearEnd])
            ->select(
                'con.property_id',
                'con.property_unit_id',
                DB::raw('SUM(rc.base_amount) as total_collected'),
                DB::raw('SUM(CASE WHEN rc.base_amount IS NULL THEN 1 ELSE 0 END) as unconverted_count'),
            )
            ->groupBy('con.property_id', 'con.property_unit_id')
            ->get();

        // ── Expenses paid ────────────────────────────────────────────────
        $expenseRows = DB::table('property_expense_payments as pep')
            ->join('property_expenses as pe', 'pe.id', '=', 'pep.property_expense_id')
            ->where('pe.company_id', $company->id)
            ->whereBetween('pep.payment_date', [$yearStart, $yearEnd])
            ->select(
                'pe.property_id',
                DB::raw('SUM(pep.base_amount) as total_expenses'),
                DB::raw('SUM(CASE WHEN pep.base_amount IS NULL THEN 1 ELSE 0 END) as unconverted_count'),
            )
            ->groupBy('pe.property_id')
            ->get()
            ->keyBy('property_id');

        // ── Market value — latest entry on or before year end ────────────
        $mvUnit = DB::table('property_market_values')
            ->where('company_id', $company->id)
            ->whereNotNull('property_unit_id')
            ->where('value_date', '<=', substr($yearEnd, 0, 7))
            ->select('property_unit_id', DB::raw('MAX(value_date) as latest_date'))
            ->groupBy('property_unit_id')
            ->get();

        $mvUnitValues = [];
        foreach ($mvUnit as $row) {
            $val = DB::table('property_market_values')
                ->where('company_id', $company->id)
                ->where('property_unit_id', $row->property_unit_id)
                ->where('value_date', $row->latest_date)
                ->value('market_value');
            $mvUnitValues[$row->property_unit_id] = (float) $val;
        }

        $mvProp = DB::table('property_market_values')
            ->where('company_id', $company->id)
            ->whereNull('property_unit_id')
            ->where('value_date', '<=', substr($yearEnd, 0, 7))
            ->select('property_id', DB::raw('MAX(value_date) as latest_date'))
            ->groupBy('property_id')
            ->get();

        $mvPropValues = [];
        foreach ($mvProp as $row) {
            $val = DB::table('property_market_values')
                ->where('company_id', $company->id)
                ->where('property_id', $row->property_id)
                ->whereNull('property_unit_id')
                ->where('value_date', $row->latest_date)
                ->value('market_value');
            $mvPropValues[$row->property_id] = (float) $val;
        }

        if ($scope === 'unit') {
            // ── Standalone units ─────────────────────────────────────────
            $standaloneProps = DB::table('properties as p')
                ->leftJoin('property_types as pt', 'pt.id', '=', 'p.property_type_id')
                ->where('p.company_id', $company->id)
                ->where('p.nature', 'unit')
                ->select(
                    'p.id',
                    'p.property_name',
                    'p.acquisition_cost',
                    'p.currency',
                    DB::raw('COALESCE(pt.type_name, "—") as type_name'),
                )
                ->get();

            // ── Child units ───────────────────────────────────────────────
            $childUnits = DB::table('property_units as pu')
                ->join('properties as p', 'p.id', '=', 'pu.property_id')
                ->leftJoin('property_types as pt', 'pt.id', '=', 'pu.property_type_id')
                ->where('pu.company_id', $company->id)
                ->select(
                    'pu.id as unit_id',
                    'pu.unit_name',
                    'pu.acquisition_cost',
                    'pu.currency',
                    'p.id as property_id',
                    'p.property_name',
                    DB::raw('COALESCE(pt.type_name, "—") as type_name'),
                )
                ->get();

            $lines = collect();

            foreach ($standaloneProps as $prop) {
                $rev            = $revenueRows->where('property_id', $prop->id)->whereNull('property_unit_id')->first();
                $exp            = $expenseRows->get($prop->id);
                $totalCollected = $rev ? (float) $rev->total_collected : 0;
                $totalExpenses  = $exp ? (float) $exp->total_expenses  : 0;
                $noi            = $totalCollected - $totalExpenses;
                $marketValue    = $mvPropValues[$prop->id] ?? null;
                $acqCost        = (float) ($prop->acquisition_cost ?? 0);
                $unrealizedGain = $marketValue !== null ? $marketValue - $acqCost : null;

                $lines->push([
                    'label'           => $prop->property_name,
                    'property_name'   => $prop->property_name,
                    'unit_name'       => null,
                    'type_name'       => $prop->type_name,
                    'currency'        => $prop->currency,
                    'acquisition_cost'=> $acqCost,
                    'total_collected' => round($totalCollected, 2),
                    'total_expenses'  => round($totalExpenses, 2),
                    'noi'             => round($noi, 2),
                    'market_value'    => $marketValue ? round($marketValue, 2) : null,
                    'unrealized_gain' => $unrealizedGain !== null ? round($unrealizedGain, 2) : null,
                ]);
            }

            foreach ($childUnits as $unit) {
                $rev            = $revenueRows->where('property_id', $unit->property_id)->where('property_unit_id', $unit->unit_id)->first();
                $totalCollected = $rev ? (float) $rev->total_collected : 0;
                $totalExpenses  = 0;
                $noi            = $totalCollected - $totalExpenses;
                $marketValue    = $mvUnitValues[$unit->unit_id] ?? null;
                $acqCost        = (float) ($unit->acquisition_cost ?? 0);
                $unrealizedGain = $marketValue !== null ? $marketValue - $acqCost : null;

                $lines->push([
                    'label'           => $unit->property_name . ' › ' . $unit->unit_name,
                    'property_name'   => $unit->property_name,
                    'unit_name'       => $unit->unit_name,
                    'type_name'       => $unit->type_name,
                    'currency'        => $unit->currency,
                    'acquisition_cost'=> $acqCost,
                    'total_collected' => round($totalCollected, 2),
                    'total_expenses'  => round($totalExpenses, 2),
                    'noi'             => round($noi, 2),
                    'market_value'    => $marketValue ? round($marketValue, 2) : null,
                    'unrealized_gain' => $unrealizedGain !== null ? round($unrealizedGain, 2) : null,
                ]);
            }

            $rows = $lines->sortBy('label')->values();

        } else {
            // ── Company-wide single row ───────────────────────────────────
            $totalCollected = round($revenueRows->sum('total_collected'), 2);
            $totalExpenses  = round($expenseRows->sum('total_expenses'), 2);
            $noi            = round($totalCollected - $totalExpenses, 2);
            $totalMV        = round(array_sum($mvUnitValues) + array_sum($mvPropValues), 2);
            $totalAcq       = round(
                DB::table('properties')->where('company_id', $company->id)->sum('acquisition_cost')
                + DB::table('property_units')->where('company_id', $company->id)->sum('acquisition_cost'),
                2
            );
            $unrealizedGain = $totalMV > 0 ? round($totalMV - $totalAcq, 2) : null;

            $rows = collect([[
                'label'           => $company->company_name ?? 'Company',
                'property_name'   => null,
                'unit_name'       => null,
                'type_name'       => '—',
                'currency'        => strtoupper($company->currency ?: 'EGP'),
                'acquisition_cost'=> $totalAcq,
                'total_collected' => $totalCollected,
                'total_expenses'  => $totalExpenses,
                'noi'             => $noi,
                'market_value'    => $totalMV ?: null,
                'unrealized_gain' => $unrealizedGain,
            ]]);
        }

        return response()->json([
            'year'              => $year,
            'scope'             => $scope,
            'base_currency'     => strtoupper($company->currency ?: 'EGP'),
            'rows'              => $rows->values(),
            'grand_collected'   => round($rows->sum('total_collected'), 2),
            'grand_expenses'    => round($rows->sum('total_expenses'), 2),
            'grand_noi'         => round($rows->sum('noi'), 2),
            'grand_mv'          => round($rows->whereNotNull('market_value')->sum('market_value'), 2),
            'grand_unrealized'  => round($rows->whereNotNull('unrealized_gain')->sum('unrealized_gain'), 2),
            'unconverted_count' => (int) $revenueRows->sum('unconverted_count') + (int) $expenseRows->sum('unconverted_count'),
        ]);
    }
}
