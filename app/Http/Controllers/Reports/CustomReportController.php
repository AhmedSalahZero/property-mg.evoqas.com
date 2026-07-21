<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CustomReport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Font;
use App\Http\Controllers\Concerns\AuthorizesCompany;

class CustomReportController extends Controller
{
    use AuthorizesCompany;

    // ══════════════════════════════════════════════════════════════════════════
    // DIMENSION & MEASURE DEFINITIONS
    // ══════════════════════════════════════════════════════════════════════════

    private function dimensionMap(string $source): array
    {
        $shared = [
            'governorate'   => ['label' => 'Governorate',   'expr' => 'p.governorate'],
            'province'      => ['label' => 'Province',      'expr' => 'p.province'],
            'property_name' => ['label' => 'Property Name', 'expr' => 'p.property_name'],
            'property_type' => ['label' => 'Property Type', 'expr' => 'COALESCE(pt_unit.type_name, pt_prop.type_name)'],
            'unit_name'     => ['label' => 'Unit Name',     'expr' => 'COALESCE(pu.unit_name, p.property_name)'],
            'month'         => ['label' => 'Month',         'expr' => null],
            'quarter'       => ['label' => 'Quarter',       'expr' => null],
            'year'          => ['label' => 'Year',          'expr' => null],
        ];

        $withTenant = array_merge($shared, [
            'tenant_name'   => ['label' => 'Tenant Name',   'expr' => 'cu.customer_name'],
            'tenant_nature' => ['label' => 'Tenant Nature', 'expr' => 'rcon.tenant_nature'],
            'revenue_type'  => ['label' => 'Revenue Type',  'expr' => 'rcon.revenue_type'],
        ]);

        $withExpense = array_merge($shared, [
            'expense_category' => ['label' => 'Expense Category', 'expr' => 'ec.category_name'],
            'expense_item'     => ['label' => 'Expense Item',     'expr' => 'ei.item_name'],
            'currency'         => ['label' => 'Currency',         'expr' => 'pe.currency'],
        ]);

        return match($source) {
            'rent_collections'  => $withTenant,
            'rent_revenues'     => $withTenant,
            'property_expenses' => $withExpense,
            'installment_dues'  => array_merge($shared, [
                'due_type' => ['label' => 'Due Type', 'expr' => 'pid.due_type'],
                'currency' => ['label' => 'Currency', 'expr' => 'pid.currency'],
            ]),
            default => $shared,
        };
    }

    private function measureMap(string $source): array
    {
        return match($source) {
            'rent_collections' => [
                'amount_collected' => ['label' => 'Amount Collected (Base Currency)', 'expr' => 'SUM(rc.base_amount)'],
                'collection_count' => ['label' => 'Collection Count',                  'expr' => 'COUNT(rc.id)'],
            ],
            'rent_revenues' => [
                'revenue_amount' => ['label' => 'Revenue Amount (Base Currency)', 'expr' => 'SUM(rr.base_amount)'],
                'revenue_count'  => ['label' => 'Revenue Count',                  'expr' => 'COUNT(rr.id)'],
            ],
            'property_expenses' => [
                'committed_amount'   => ['label' => 'Committed Amount (Base Currency)', 'expr' => 'SUM(pe.base_amount)'],
                'paid_amount'        => ['label' => 'Paid Amount (Base Currency)',      'expr' => 'COALESCE(SUM(pep.base_amount), 0)'],
                'outstanding_amount' => ['label' => 'Outstanding (Base Currency)',      'expr' => 'SUM(pe.base_amount) - COALESCE(SUM(pep.base_amount), 0)'],
                'payment_count'      => ['label' => 'Expense Count',                    'expr' => 'COUNT(DISTINCT pe.id)'],
            ],
            'installment_dues' => [
                'due_amount'         => ['label' => 'Due Amount (Base Currency)',   'expr' => 'SUM(pid.base_amount)'],
                'paid_amount'        => ['label' => 'Paid Amount (Base Currency)',  'expr' => 'SUM(CASE WHEN pid.status = \'paid\' THEN pid.base_amount ELSE 0 END)'],
                'outstanding_amount' => ['label' => 'Outstanding (Base Currency)',  'expr' => 'SUM(CASE WHEN pid.status != \'paid\' THEN pid.base_amount ELSE 0 END)'],
                'due_count'          => ['label' => 'Due Count',                    'expr' => 'COUNT(pid.id)'],
            ],
            default => [],
        };
    }

    // ══════════════════════════════════════════════════════════════════════════
    // DATE COLUMN per source
    // ══════════════════════════════════════════════════════════════════════════
    private function dateColumn(string $source, string $expenseDateMode = 'expense_date'): string
    {
        return match($source) {
            'rent_collections'  => 'rc.collection_date',
            'rent_revenues'     => 'rr.revenue_date',
            'property_expenses' => $expenseDateMode === 'payment_date' ? 'pep.payment_date' : 'pe.expense_date',
            'installment_dues'  => 'pid.due_date',
            default             => 'created_at',
        };
    }

    // ══════════════════════════════════════════════════════════════════════════
    // BASE QUERY per source
    // ══════════════════════════════════════════════════════════════════════════
    private function baseQuery(string $source, int $companyId, array $filters): \Illuminate\Database\Query\Builder
    {
        $start           = Carbon::parse($filters['start_date'])->startOfDay();
        $end             = Carbon::parse($filters['end_date'])->endOfDay();
        $expenseDateMode = $filters['expense_date_mode'] ?? 'expense_date';
        $dateCol         = $this->dateColumn($source, $expenseDateMode);

        // ── Cast all ID-based filters to integers (HTML sends strings) ────────
        $propertyIds         = array_map('intval', $filters['property_id']          ?? []);
        $propertyTypeIds     = array_map('intval', $filters['property_type_id']     ?? []);
        $expenseCategoryIds  = array_map('intval', $filters['expense_category_id']  ?? []);

        switch ($source) {

            case 'rent_collections':
                return DB::table('rent_collections as rc')
                    ->join('rent_contracts as rcon', 'rcon.id', '=', 'rc.rent_contract_id')
                    ->join('properties as p', 'p.id', '=', 'rcon.property_id')
                    ->leftJoin('property_units as pu', 'pu.id', '=', 'rcon.property_unit_id')
                    ->leftJoin('property_types as pt_unit', 'pt_unit.id', '=', 'pu.property_type_id')
                    ->leftJoin('property_types as pt_prop', 'pt_prop.id', '=', 'p.property_type_id')
                    ->leftJoin('customers as cu', 'cu.id', '=', 'rcon.customer_id')
                    ->where('rc.company_id', $companyId)
                    ->whereBetween('rc.collection_date', [$start, $end])
                    ->when(!empty($filters['governorate']),  fn($q) => $q->whereIn('p.governorate', $filters['governorate']))
                    ->when(!empty($propertyIds),             fn($q) => $q->whereIn('p.id', $propertyIds))
                    ->when(!empty($propertyTypeIds),         fn($q) => $q->where(function ($q) use ($propertyTypeIds) {
                        $q->whereIn('pt_unit.id', $propertyTypeIds)
                          ->orWhereIn('pt_prop.id', $propertyTypeIds);
                    }))
                    ->when(!empty($filters['revenue_type']), fn($q) => $q->whereIn('rcon.revenue_type', $filters['revenue_type']))
                    ->when(!empty($filters['status']),       fn($q) => $q->whereIn('rc.status', $filters['status']));

            case 'rent_revenues':
                return DB::table('rent_revenues as rr')
                    ->join('rent_contracts as rcon', 'rcon.id', '=', 'rr.rent_contract_id')
                    ->join('properties as p', 'p.id', '=', 'rcon.property_id')
                    ->leftJoin('property_units as pu', 'pu.id', '=', 'rcon.property_unit_id')
                    ->leftJoin('property_types as pt_unit', 'pt_unit.id', '=', 'pu.property_type_id')
                    ->leftJoin('property_types as pt_prop', 'pt_prop.id', '=', 'p.property_type_id')
                    ->leftJoin('customers as cu', 'cu.id', '=', 'rcon.customer_id')
                    ->where('rr.company_id', $companyId)
                    ->whereBetween('rr.revenue_date', [$start, $end])
                    ->when(!empty($filters['governorate']),  fn($q) => $q->whereIn('p.governorate', $filters['governorate']))
                    ->when(!empty($propertyIds),             fn($q) => $q->whereIn('p.id', $propertyIds))
                    ->when(!empty($propertyTypeIds),         fn($q) => $q->where(function ($q) use ($propertyTypeIds) {
                        $q->whereIn('pt_unit.id', $propertyTypeIds)
                          ->orWhereIn('pt_prop.id', $propertyTypeIds);
                    }))
                    ->when(!empty($filters['revenue_type']), fn($q) => $q->whereIn('rcon.revenue_type', $filters['revenue_type']));

            case 'property_expenses':
                // expense_date_mode = 'expense_date' → filter on pe.expense_date (when committed)
                // expense_date_mode = 'payment_date' → filter on pep.payment_date (when paid)
                $q = DB::table('property_expenses as pe')
                    ->join('properties as p', 'p.id', '=', 'pe.property_id')
                    ->join('expense_categories as ec', 'ec.id', '=', 'pe.expense_category_id')
                    ->join('expense_items as ei', 'ei.id', '=', 'pe.expense_item_id')
                    ->leftJoin('property_types as pt_unit', 'pt_unit.id', '=', DB::raw('NULL'))
                    ->leftJoin('property_types as pt_prop', 'pt_prop.id', '=', 'p.property_type_id')
                    ->leftJoin('property_expense_payments as pep', 'pep.property_expense_id', '=', 'pe.id')
                    ->where('pe.company_id', $companyId);

                // Apply date filter to the correct column based on user's mode choice
                if ($expenseDateMode === 'payment_date') {
                    // Only include expenses that have at least one payment in the period
                    $q->whereBetween('pep.payment_date', [$start, $end]);
                } else {
                    // Include all expenses committed (recorded) in the period, whether paid or not
                    $q->whereBetween('pe.expense_date', [$start, $end]);
                }

                return $q
                    ->when(!empty($filters['governorate']),       fn($q) => $q->whereIn('p.governorate', $filters['governorate']))
                    ->when(!empty($propertyIds),                  fn($q) => $q->whereIn('p.id', $propertyIds))
                    ->when(!empty($propertyTypeIds),              fn($q) => $q->where(function ($q) use ($propertyTypeIds) {
                        $q->whereIn('pt_prop.id', $propertyTypeIds);
                    }))
                    ->when(!empty($expenseCategoryIds),           fn($q) => $q->whereIn('pe.expense_category_id', $expenseCategoryIds))
                    ->when(!empty($filters['currency']),          fn($q) => $q->whereIn('pe.currency', $filters['currency']));

            case 'installment_dues':
                return DB::table('property_installment_dues as pid')
                    ->join('properties as p', 'p.id', '=', 'pid.property_id')
                    ->leftJoin('property_types as pt_prop', 'pt_prop.id', '=', 'p.property_type_id')
                    ->leftJoin('property_types as pt_unit', 'pt_unit.id', '=', DB::raw('NULL'))
                    ->where('pid.company_id', $companyId)
                    ->whereBetween('pid.due_date', [$start, $end])
                    ->when(!empty($filters['governorate']),  fn($q) => $q->whereIn('p.governorate', $filters['governorate']))
                    ->when(!empty($propertyIds),             fn($q) => $q->whereIn('p.id', $propertyIds))
                    ->when(!empty($propertyTypeIds),         fn($q) => $q->where(function ($q) use ($propertyTypeIds) {
                        $q->whereIn('pt_prop.id', $propertyTypeIds);
                    }))
                    ->when(!empty($filters['status']),       fn($q) => $q->whereIn('pid.status', $filters['status']))
                    ->when(!empty($filters['due_type']),     fn($q) => $q->whereIn('pid.due_type', $filters['due_type']));

            default:
                abort(422, 'Unknown data source');
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // RESOLVE DIMENSION EXPRESSION (handles date dimensions dynamically)
    // ══════════════════════════════════════════════════════════════════════════
    private function resolveDimExpr(string $key, string $source, string $expenseDateMode = 'expense_date'): string
    {
        $dateCol = $this->dateColumn($source, $expenseDateMode);

        return match($key) {
            'month'         => "DATE_FORMAT({$dateCol}, '%Y-%m')",
            'quarter'       => "CONCAT(YEAR({$dateCol}), '-Q', QUARTER({$dateCol}))",
            'year'          => "YEAR({$dateCol})",
            'tenant_name'   => 'cu.customer_name',
            'tenant_nature' => 'rcon.tenant_nature',
            'revenue_type'  => 'rcon.revenue_type',
            default         => $this->dimensionMap($source)[$key]['expr'] ?? $key,
        };
    }

    // ══════════════════════════════════════════════════════════════════════════
    // BUILD & EXECUTE QUERY
    // ══════════════════════════════════════════════════════════════════════════
    private function executeQuery(Company $company, array $config): array
    {
        $source          = $config['data_source'];
        $dimensions      = array_values(array_filter($config['dimensions'] ?? []));
        $measures        = $config['measures'] ?? [];
        $filters         = $config['filters'] ?? [];
        $expenseDateMode = $filters['expense_date_mode'] ?? 'expense_date';

        if (empty($dimensions) || empty($measures)) {
            return ['columns' => [], 'rows' => [], 'totals' => []];
        }

        $dimMap     = $this->dimensionMap($source);
        $measureMap = $this->measureMap($source);

        // ── Build SELECT ──────────────────────────────────────────────────────
        $selects   = [];
        $groupBys  = [];
        $colLabels = [];

        foreach ($dimensions as $dimKey) {
            if (!isset($dimMap[$dimKey])) continue;
            $expr        = $this->resolveDimExpr($dimKey, $source, $expenseDateMode);
            $alias       = 'dim_' . $dimKey;
            $selects[]   = DB::raw("{$expr} as `{$alias}`");
            $groupBys[]  = DB::raw($expr);
            $colLabels[] = ['key' => $alias, 'label' => $dimMap[$dimKey]['label'], 'type' => 'dimension'];
        }

        foreach ($measures as $mKey) {
            if (!isset($measureMap[$mKey])) continue;
            $expr        = $measureMap[$mKey]['expr'];
            $alias       = 'msr_' . $mKey;
            $selects[]   = DB::raw("{$expr} as `{$alias}`");
            $colLabels[] = ['key' => $alias, 'label' => $measureMap[$mKey]['label'], 'type' => 'measure'];
        }

        // ── Run query ─────────────────────────────────────────────────────────
        $query = $this->baseQuery($source, $company->id, $filters)
            ->select($selects)
            ->groupBy($groupBys)
            ->orderBy($groupBys[0]);

        $rows = $query->get()->map(fn($row) => (array) $row)->toArray();

        // ── Totals row ────────────────────────────────────────────────────────
        $totals = [];
        foreach ($colLabels as $col) {
            if ($col['type'] === 'measure') {
                $totals[$col['key']] = collect($rows)->sum($col['key']);
            } else {
                $totals[$col['key']] = null;
            }
        }

        return [
            'columns' => $colLabels,
            'rows'    => $rows,
            'totals'  => $totals,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FILTER OPTIONS (for dropdowns in builder)
    // ══════════════════════════════════════════════════════════════════════════
    private function filterOptions(Company $company): array
    {
        $governorates = DB::table('properties')
            ->where('company_id', $company->id)
            ->whereNotNull('governorate')
            ->distinct()
            ->orderBy('governorate')
            ->pluck('governorate');

        $properties = DB::table('properties')
            ->where('company_id', $company->id)
            ->where('is_active', 1)
            ->orderBy('property_name')
            ->get(['id', 'property_name']);

        $propertyTypes = DB::table('property_types')
            ->where('company_id', $company->id)
            ->orderBy('type_name')
            ->get(['id', 'type_name']);

        $expenseCategories = DB::table('expense_categories')
            ->where('company_id', $company->id)
            ->orderBy('category_name')
            ->get(['id', 'category_name']);

        return [
            'governorates'       => $governorates,
            'properties'         => $properties,
            'property_types'     => $propertyTypes,
            'expense_categories' => $expenseCategories,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CONTROLLER ACTIONS
    // ══════════════════════════════════════════════════════════════════════════

    // ── Builder page (new or load saved) ─────────────────────────────────────
    public function builder(Company $company, ?CustomReport $report = null)
    {
        $this->authorizeCompany($company);
        // Fix for audit finding C-2 — update()/destroy() below already
        // verified $report->company_id, but this GET (used to load a saved
        // report into the builder UI) did not, letting a user preview
        // another company's saved report definition by report ID.
        if ($report !== null) {
            abort_unless($report->company_id === $company->id, 404);
        }

        return Inertia::render('Reports/CustomReport', [
            'company'       => $company,
            'report'        => $report ?? null,
            'filterOptions' => $this->filterOptions($company),
        ]);
    }

    // ── Store new saved report ────────────────────────────────────────────────
    public function store(Request $request, Company $company)
    {
        $this->authorizeCompany($company);

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'data_source' => 'required|in:rent_collections,rent_revenues,property_expenses,installment_dues',
            'dimensions'  => 'required|array|min:1',
            'measures'    => 'required|array|min:1',
            'filters'     => 'required|array',
        ]);

        CustomReport::create([
            ...$validated,
            'company_id'  => $company->id,
            'created_by'  => auth()->id(),
            'last_run_at' => now(),
        ]);

        return back()->with('success', 'Report saved.');
    }

    // ── Update saved report ───────────────────────────────────────────────────
    public function update(Request $request, Company $company, CustomReport $report)
    {
        $this->authorizeCompany($company);
        abort_if($report->company_id !== $company->id, 403);

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'data_source' => 'required|in:rent_collections,rent_revenues,property_expenses,installment_dues',
            'dimensions'  => 'required|array|min:1',
            'measures'    => 'required|array|min:1',
            'filters'     => 'required|array',
        ]);

        $report->update([...$validated, 'last_run_at' => now()]);

        return back()->with('success', 'Report updated.');
    }

    // ── Delete saved report ───────────────────────────────────────────────────
    public function destroy(Company $company, CustomReport $report)
    {
        $this->authorizeCompany($company);
        abort_if($report->company_id !== $company->id, 403);

        $report->delete();

        return back()->with('success', 'Report deleted.');
    }

    // ── Run report (returns JSON) ─────────────────────────────────────────────
    public function run(Request $request, Company $company)
    {
        $this->authorizeCompany($company);

        // Validate only the fields that need validation rules.
        // Do NOT list filters.* sub-keys here — Laravel strips any filter keys
        // not explicitly declared, which silently removes all optional filters.
        $request->validate([
            'data_source'        => 'required|in:rent_collections,rent_revenues,property_expenses,installment_dues',
            'dimensions'         => 'required|array|min:1',
            'measures'           => 'required|array|min:1',
            'filters'            => 'required|array',
            'filters.start_date' => 'required|date',
            'filters.end_date'   => 'required|date|after_or_equal:filters.start_date',
            'report_id'          => 'nullable|integer',
        ]);

        // Read the full payload directly from the request so no filter keys are stripped.
        $payload = [
            'data_source' => $request->input('data_source'),
            'dimensions'  => $request->input('dimensions'),
            'measures'    => $request->input('measures'),
            'filters'     => $request->input('filters'),  // full filters object, nothing stripped
            'report_id'   => $request->input('report_id'),
        ];

        if (!empty($payload['report_id'])) {
            CustomReport::where('id', $payload['report_id'])
                ->where('company_id', $company->id)
                ->update(['last_run_at' => now()]);
        }

        $result = $this->executeQuery($company, $payload);

        return response()->json($result);
    }

    // ── Export to Excel ───────────────────────────────────────────────────────
    public function export(Request $request, Company $company)
    {
        $this->authorizeCompany($company);

        // Validate only required fields — do not list filters.* sub-keys
        // to avoid Laravel stripping optional filter values from the array.
        $request->validate([
            'data_source'        => 'required|in:rent_collections,rent_revenues,property_expenses,installment_dues',
            'dimensions'         => 'required|array|min:1',
            'measures'           => 'required|array|min:1',
            'filters'            => 'required|array',
            'filters.start_date' => 'required|date',
            'filters.end_date'   => 'required|date|after_or_equal:filters.start_date',
            'report_name'        => 'nullable|string|max:255',
        ]);

        // Read full payload from raw request so no filter keys are stripped.
        $payload = [
            'data_source' => $request->input('data_source'),
            'dimensions'  => $request->input('dimensions'),
            'measures'    => $request->input('measures'),
            'filters'     => $request->input('filters'),
        ];

        $result     = $this->executeQuery($company, $payload);
        $columns    = $result['columns'];
        $rows       = $result['rows'];
        $totals     = $result['totals'];
        $reportName = $request->input('report_name') ?? 'Custom Report';

        // ── Build spreadsheet ─────────────────────────────────────────────────
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Report');

        // Title row
        $sheet->setCellValue('A1', $reportName);
        $sheet->setCellValue('A2', 'Period: ' . $request->input('filters.start_date') . ' → ' . $request->input('filters.end_date'));
        $sheet->setCellValue('A3', 'Generated: ' . now()->format('Y-m-d H:i'));

        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($columns));
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->mergeCells("A3:{$lastCol}3");

        $sheet->getStyle('A1')->getFont()->setSize(14)->setBold(true)->getColor()->setRGB('BA7517');
        $sheet->getStyle('A2:A3')->getFont()->setSize(10)->getColor()->setRGB('94A3B8');
        $sheet->getStyle('A1:A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Header row (row 5)
        $headerRow = 5;
        foreach ($columns as $ci => $col) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci + 1);
            $sheet->setCellValue("{$colLetter}{$headerRow}", $col['label']);
            $sheet->getStyle("{$colLetter}{$headerRow}")->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0C1829']],
                'alignment' => ['horizontal' => $col['type'] === 'measure' ? Alignment::HORIZONTAL_RIGHT : Alignment::HORIZONTAL_LEFT],
                'borders'   => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '1490A8']]],
            ]);
        }

        // Data rows
        foreach ($rows as $ri => $row) {
            $excelRow = $headerRow + 1 + $ri;
            $bgColor  = $ri % 2 === 0 ? 'F8FAFC' : 'EFF6FF';
            foreach ($columns as $ci => $col) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci + 1);
                $val       = $row[$col['key']] ?? '';
                $sheet->setCellValue("{$colLetter}{$excelRow}", $val);
                $sheet->getStyle("{$colLetter}{$excelRow}")->applyFromArray([
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
                    'alignment' => ['horizontal' => $col['type'] === 'measure' ? Alignment::HORIZONTAL_RIGHT : Alignment::HORIZONTAL_LEFT],
                ]);
                if ($col['type'] === 'measure' && is_numeric($val)) {
                    $sheet->getStyle("{$colLetter}{$excelRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                }
            }
        }

        // Totals row
        $totalsRow = $headerRow + 1 + count($rows);
        foreach ($columns as $ci => $col) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci + 1);
            $val       = $totals[$col['key']] ?? '';
            if ($ci === 0 && $col['type'] === 'dimension') {
                $val = 'TOTAL';
            }
            $sheet->setCellValue("{$colLetter}{$totalsRow}", $val);
            $sheet->getStyle("{$colLetter}{$totalsRow}")->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['rgb' => 'BA7517']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0C1829']],
                'alignment' => ['horizontal' => $col['type'] === 'measure' ? Alignment::HORIZONTAL_RIGHT : Alignment::HORIZONTAL_LEFT],
                'borders'   => ['top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '1490A8']]],
            ]);
            if ($col['type'] === 'measure' && is_numeric($val)) {
                $sheet->getStyle("{$colLetter}{$totalsRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            }
        }

        // Auto-width
        foreach (range(1, count($columns)) as $ci) {
            $sheet->getColumnDimensionByColumn($ci)->setAutoSize(true);
        }

        // Stream
        $filename = str_replace(' ', '_', $reportName) . '_' . now()->format('Ymd_His') . '.xlsx';
        $writer   = new Xlsx($spreadsheet);

        return response()->stream(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'max-age=0',
        ]);
    }
}
