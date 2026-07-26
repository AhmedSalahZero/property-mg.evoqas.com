<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use App\Http\Controllers\Concerns\AuthorizesCompany;

/**
 * Portfolio-wide "Properties Rent Collection" page (confirmed July 2026
 * session) — sits in the sidebar right after Properties. Unlike the
 * existing Reports > Rent Collections page (a date-range history report),
 * this is an ACTION screen: it only ever shows outstanding collections
 * (pending + overdue, never already-collected rows) across every property
 * in the company, with a month-bucket summary card up top and a "Collect"
 * action inline per row — no need to open each contract individually to
 * mark a payment received.
 *
 * BUCKET FIX (confirmed July 2026 session #2): the buckets originally
 * trusted the stored `status` column to already say 'overdue' for past-due
 * rows. In practice a row's status only flips from 'pending' to 'overdue'
 * when the daily MarkOverdueRecords job runs — if that hasn't happened
 * recently (or a row is old test data), a genuinely past-due row can sit
 * there as 'pending' indefinitely. Because the old Overdue bucket filtered
 * on status = 'overdue' AND the forward buckets only looked at the next 4
 * calendar months, a stale 'pending' row with a collection_date from over
 * a year ago fell into NEITHER bucket — it vanished from every card total
 * while still sitting visibly in the table. Bucketing is now done purely
 * from collection_date vs today for every outstanding (pending OR
 * overdue) row, never from the stored status label, so the cards are
 * always correct regardless of whether that background job has run.
 *
 * The bucket card mirrors the Overdue Aging Buckets methodology already
 * used elsewhere in this app, but forward-looking instead of backward.
 * Bucket sums use base_amount (company base currency, set at write time
 * by CurrencyConversionService) so mixed-currency collections combine
 * correctly — rows still awaiting an FX rate (base_amount null) are
 * excluded from the buckets and flagged via unconverted_count, same
 * convention as Reports > Rent Collections.
 *
 * The "Collect" action reuses the existing per-contract endpoint
 * (RentContractController::markCollected) rather than duplicating it —
 * this page just needs to know which property/contract/collection a row
 * belongs to in order to build that URL, which the join below provides.
 */
class PropertyRentCollectionController extends Controller
{
    use AuthorizesCompany;

    public function index(Company $company)
    {
        $this->authorizeCompany($company);

        return Inertia::render('Properties/RentCollections/Index', [
            'company' => $company,
        ]);
    }

    public function data(Request $request, Company $company)
    {
        $this->authorizeCompany($company);

        $filters = $request->validate([
            'property_id' => 'nullable|integer',
            'tenant'      => 'nullable|string|max:255',
            'page'        => 'nullable|integer|min:1',
            'per_page'    => 'nullable|integer|min:1|max:200',
        ]);

        $rows = $this->buildQuery($company, $filters)->get();

        $buckets = $this->computeBuckets($rows, 'collection_date');

        // Property list for the filter dropdown — every property in the
        // company, not just ones with an outstanding collection right now.
        $properties = DB::table('properties')
            ->where('company_id', $company->id)
            ->orderBy('property_name')
            ->get(['id', 'property_name']);

        // ── Pagination ───────────────────────────────────────────────────
        // Buckets need the FULL filtered set above; only the table itself
        // is paginated, sliced in PHP from the same already-fetched rows
        // so there's no second query and the bucket totals always match
        // what filtering produced regardless of which page is shown.
        $perPage = $filters['per_page'] ?? 25;
        $page    = $filters['page'] ?? 1;
        $total   = $rows->count();
        $paged   = $rows->forPage($page, $perPage)->values();

        return response()->json([
            'rows'              => $paged,
            'pagination'        => [
                'current_page' => (int) $page,
                'per_page'     => (int) $perPage,
                'total'        => $total,
                'last_page'    => max(1, (int) ceil($total / $perPage)),
            ],
            'buckets'           => $buckets['sums'],
            'bucket_labels'     => $buckets['labels'],
            'properties'        => $properties,
            'base_currency'     => strtoupper($company->currency ?: 'EGP'),
            'unconverted_count' => $rows->whereNull('base_amount')->count(),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // EXPORT — full filtered outstanding list (ignores pagination),
    // colored to match the Custom Report Builder's export style: gold
    // title, dark navy header row, alternating row colors, right-aligned
    // #,##0.00 numbers, gold TOTAL row.
    // ═══════════════════════════════════════════════════════════════════
    public function export(Request $request, Company $company)
    {
        $this->authorizeCompany($company);

        $filters = $request->validate([
            'property_id' => 'nullable|integer',
            'tenant'      => 'nullable|string|max:255',
        ]);

        $rows = $this->buildQuery($company, $filters)->get();
        $baseCurrency = strtoupper($company->currency ?: 'EGP');

        $columns = [
            ['key' => 'property_name',    'label' => 'Property / Unit', 'type' => 'dimension'],
            ['key' => 'tenant_name',      'label' => 'Tenant',          'type' => 'dimension'],
            ['key' => 'period_from',      'label' => 'Period From',     'type' => 'dimension'],
            ['key' => 'period_to',        'label' => 'Period To',       'type' => 'dimension'],
            ['key' => 'collection_date',  'label' => 'Collection Date', 'type' => 'dimension'],
            ['key' => 'collection_amount','label' => 'Amount',          'type' => 'measure'],
            ['key' => 'currency',         'label' => 'Currency',        'type' => 'dimension'],
            ['key' => 'base_amount',      'label' => "Amount ({$baseCurrency})", 'type' => 'measure'],
            ['key' => 'status',           'label' => 'Status',          'type' => 'dimension'],
        ];

        $exportRows = $rows->map(fn ($r) => [
            'property_name'     => $r->unit_label ?? $r->property_name,
            'tenant_name'       => $r->tenant_name,
            'period_from'       => $r->period_from,
            'period_to'         => $r->period_to,
            'collection_date'   => $r->collection_date,
            'collection_amount' => (float) $r->collection_amount,
            'currency'          => $r->currency,
            'base_amount'       => $r->base_amount !== null ? (float) $r->base_amount : null,
            'status'            => $r->status,
        ])->values()->all();

        $totals = [
            'property_name' => 'TOTAL',
            'base_amount'   => round($rows->sum('base_amount'), 2),
        ];

        return $this->streamColoredExcel(
            'Properties Rent Collection — Outstanding',
            $columns,
            $exportRows,
            $totals
        );
    }

    // ═══════════════════════════════════════════════════════════════════
    // Shared filtered query — outstanding (pending + overdue) collections
    // across the whole company, joined for display fields.
    // ═══════════════════════════════════════════════════════════════════
    private function buildQuery(Company $company, array $filters)
    {
        $query = DB::table('rent_collections as rc')
            ->join('rent_contracts as con', 'con.id', '=', 'rc.rent_contract_id')
            ->join('customers as cu', 'cu.id', '=', 'con.customer_id')
            ->join('properties as p', 'p.id', '=', 'con.property_id')
            ->leftJoin('property_units as pu', 'pu.id', '=', 'con.property_unit_id')
            ->where('rc.company_id', $company->id)
            ->whereIn('rc.status', ['pending', 'overdue']);

        if (!empty($filters['property_id'])) {
            $query->where('con.property_id', $filters['property_id']);
        }
        if (!empty($filters['tenant'])) {
            $query->where('cu.customer_name', 'like', '%' . $filters['tenant'] . '%');
        }

        return $query->select(
                'rc.id',
                'rc.rent_contract_id',
                'con.property_id',
                'rc.collection_date',
                'rc.period_from',
                'rc.period_to',
                'rc.collection_amount',
                'rc.base_amount',
                'rc.currency',
                'rc.status',
                DB::raw('cu.customer_name as tenant_name'),
                DB::raw('p.property_name'),
                DB::raw('COALESCE(pu.unit_name, p.property_name) as unit_label'),
            )
            ->orderBy('rc.collection_date')
            ->orderBy('p.property_name');
    }

    // ═══════════════════════════════════════════════════════════════════
    // Date-based bucketing (see class doc comment for why this can't
    // trust the stored status column). $dateField is the column name on
    // each row to bucket by ('collection_date' here, 'due_date' on the
    // installment-payments equivalent).
    // ═══════════════════════════════════════════════════════════════════
    private function computeBuckets($rows, string $dateField): array
    {
        $today = Carbon::today();

        $ranges = [
            'this_month'    => [$today->copy(), $today->copy()->endOfMonth()],
            'next_month'    => [$today->copy()->addMonthNoOverflow()->startOfMonth(),   $today->copy()->addMonthNoOverflow()->endOfMonth()],
            'plus_2_months' => [$today->copy()->addMonthsNoOverflow(2)->startOfMonth(), $today->copy()->addMonthsNoOverflow(2)->endOfMonth()],
            'plus_3_months' => [$today->copy()->addMonthsNoOverflow(3)->startOfMonth(), $today->copy()->addMonthsNoOverflow(3)->endOfMonth()],
        ];

        $sums = [
            // Anything dated before today, regardless of its stored
            // status — this is what actually fixes the wrong Overdue sum.
            'overdue' => round(
                $rows->filter(fn ($r) => $r->{$dateField} < $today->toDateString())->sum('base_amount'),
                2
            ),
        ];
        $labels = ['overdue' => 'Overdue'];

        foreach ($ranges as $key => [$start, $end]) {
            $sums[$key] = round(
                $rows->filter(fn ($r) => $r->{$dateField} >= $start->toDateString() && $r->{$dateField} <= $end->toDateString())
                    ->sum('base_amount'),
                2
            );
            $labels[$key] = $start->format('M Y');
        }

        return ['sums' => $sums, 'labels' => $labels];
    }

    // ═══════════════════════════════════════════════════════════════════
    // Colored Excel export — same visual style as
    // Reports\CustomReportController::export() (gold title, navy header,
    // alternating rows, gold TOTAL row), factored out here since both new
    // portfolio pages need it.
    // ═══════════════════════════════════════════════════════════════════
    private function streamColoredExcel(string $title, array $columns, array $rows, array $totals)
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Report');

        $sheet->setCellValue('A1', $title);
        $sheet->setCellValue('A2', 'Generated: ' . now()->format('Y-m-d H:i'));

        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($columns));
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->mergeCells("A2:{$lastCol}2");

        $sheet->getStyle('A1')->getFont()->setSize(14)->setBold(true)->getColor()->setRGB('BA7517');
        $sheet->getStyle('A2')->getFont()->setSize(10)->getColor()->setRGB('94A3B8');
        $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $headerRow = 4;
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

        $totalsRow = $headerRow + 1 + count($rows);
        foreach ($columns as $ci => $col) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci + 1);
            $val       = $totals[$col['key']] ?? '';
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

        foreach (range(1, count($columns)) as $ci) {
            $sheet->getColumnDimensionByColumn($ci)->setAutoSize(true);
        }

        $filename = str_replace(' ', '_', $title) . '_' . now()->format('Ymd_His') . '.xlsx';
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
