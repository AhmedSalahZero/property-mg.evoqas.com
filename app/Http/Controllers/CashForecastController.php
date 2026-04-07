<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CashForecastController extends Controller
{
    public function index(Company $company)
    {
        // Default period: current month → +11 months
        $fromDefault = Carbon::now()->startOfMonth()->format('Y-m');
        $toDefault   = Carbon::now()->startOfMonth()->addMonths(11)->format('Y-m');

        return Inertia::render('Properties/CashForecast', [
            'company'     => $company,
            'fromDefault' => $fromDefault,
            'toDefault'   => $toDefault,
        ]);
    }

    public function data(Request $request, Company $company)
    {
        $from = $request->input('from'); // e.g. "2026-04"
        $to   = $request->input('to');   // e.g. "2027-03"

        if (!$from || !$to) {
            return response()->json(['error' => 'Missing period'], 422);
        }

        $fromDate = Carbon::createFromFormat('Y-m', $from)->startOfMonth();
        $toDate   = Carbon::createFromFormat('Y-m', $to)->endOfMonth();

        // Build list of months in range
        $months = [];
        $cursor = $fromDate->copy();
        while ($cursor->lte($toDate)) {
            $months[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        // ── RENT COLLECTIONS ─────────────────────────────────────────────
        // Group by unit type → unit name, distributed by collection_date
        $collections = DB::table('rent_collections as rc')
            ->join('rent_contracts as rct', 'rc.rent_contract_id', '=', 'rct.id')
            ->join('properties as p', 'rct.property_id', '=', 'p.id')
            ->leftJoin('property_units as pu', 'rct.property_unit_id', '=', 'pu.id')
            ->leftJoin('property_types as pt_unit', 'pu.property_type_id', '=', 'pt_unit.id')
            ->leftJoin('property_types as pt_prop', 'p.property_type_id', '=', 'pt_prop.id')
            ->where('rc.company_id', $company->id)
            ->whereBetween('rc.collection_date', [$fromDate, $toDate])
            ->select(
                DB::raw('COALESCE(pt_unit.type_name, pt_prop.type_name, "No Type") as unit_type'),
                DB::raw('COALESCE(pu.unit_name, p.property_name) as unit_name'),
                DB::raw('DATE_FORMAT(rc.collection_date, "%Y-%m") as month'),
                DB::raw('SUM(rc.collection_amount) as amount')
            )
            ->groupBy(
                DB::raw('COALESCE(pt_unit.type_name, pt_prop.type_name, "No Type")'),
                DB::raw('COALESCE(pu.unit_name, p.property_name)'),
                DB::raw('DATE_FORMAT(rc.collection_date, "%Y-%m")')
            )
            ->orderBy('unit_type')
            ->orderBy('unit_name')
            ->get();

        // Structure: { typeName: { unitName: { "2026-04": 5000, ... } } }
        $rentByTypeUnit = [];
        foreach ($collections as $row) {
            $type = $row->unit_type;
            $unit = $row->unit_name;
            if (!isset($rentByTypeUnit[$type])) $rentByTypeUnit[$type] = [];
            if (!isset($rentByTypeUnit[$type][$unit])) $rentByTypeUnit[$type][$unit] = [];
            $rentByTypeUnit[$type][$unit][$row->month] = (float) $row->amount;
        }

        // ── INSTALLMENT PAYMENTS ──────────────────────────────────────────
        // Group by unit type → unit name (property level), by due_date
        $installments = DB::table('property_installment_dues as pid')
            ->join('properties as p', 'pid.property_id', '=', 'p.id')
            ->leftJoin('property_types as pt', 'p.property_type_id', '=', 'pt.id')
            ->where('pid.company_id', $company->id)
            ->whereIn('pid.status', ['pending', 'overdue'])
            ->whereBetween('pid.due_date', [$fromDate, $toDate])
            ->select(
                DB::raw('COALESCE(pt.type_name, "No Type") as unit_type'),
                'p.property_name as unit_name',
                DB::raw('DATE_FORMAT(pid.due_date, "%Y-%m") as month'),
                DB::raw('SUM(pid.amount) as amount')
            )
            ->groupBy(
                DB::raw('COALESCE(pt.type_name, "No Type")'),
                'p.property_name',
                DB::raw('DATE_FORMAT(pid.due_date, "%Y-%m")')
            )
            ->orderBy('unit_type')
            ->orderBy('unit_name')
            ->get();

        $installByTypeUnit = [];
        foreach ($installments as $row) {
            $type = $row->unit_type;
            $unit = $row->unit_name;
            if (!isset($installByTypeUnit[$type])) $installByTypeUnit[$type] = [];
            if (!isset($installByTypeUnit[$type][$unit])) $installByTypeUnit[$type][$unit] = [];
            $installByTypeUnit[$type][$unit][$row->month] = (float) $row->amount;
        }

        // ── EXPENSE PAYMENTS ──────────────────────────────────────────────
        // One row per expense item name, amounts by payment_date
        $expensePayments = DB::table('property_expense_payments as pep')
            ->join('property_expenses as pe', 'pep.property_expense_id', '=', 'pe.id')
            ->join('expense_items as ei', 'pe.expense_item_id', '=', 'ei.id')
            ->where('pep.company_id', $company->id)
            ->whereBetween('pep.payment_date', [$fromDate, $toDate])
            ->select(
                'ei.item_name',
                DB::raw('DATE_FORMAT(pep.payment_date, "%Y-%m") as month'),
                DB::raw('SUM(pep.amount) as amount')
            )
            ->groupBy('ei.item_name', DB::raw('DATE_FORMAT(pep.payment_date, "%Y-%m")'))
            ->orderBy('ei.item_name')
            ->get();

        $expenseByItem = [];
        foreach ($expensePayments as $row) {
            $item = $row->item_name;
            if (!isset($expenseByItem[$item])) $expenseByItem[$item] = [];
            $expenseByItem[$item][$row->month] = (float) $row->amount;
        }

        return response()->json([
            'months'            => $months,
            'rentByTypeUnit'    => $rentByTypeUnit,
            'installByTypeUnit' => $installByTypeUnit,
            'expenseByItem'     => $expenseByItem,
        ]);
    }
}