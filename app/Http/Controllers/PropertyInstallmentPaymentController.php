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
 * Portfolio-wide "Properties Installment Payment" page — the developer-
 * installment mirror of PropertyRentCollectionController (money going OUT
 * to developers instead of coming IN from tenants). Same shape: outstanding
 * dues only (pending + overdue), a forward month-bucket summary card, and
 * an inline "Mark Paid" action per row that reuses the existing per-
 * property endpoint (PropertyInstallmentController::markPaid).
 *
 * BUCKET FIX (confirmed July 2026 session #2): same root cause and same
 * fix as PropertyRentCollectionController — see that class's doc comment.
 * Buckets are computed from due_date vs today for every outstanding row,
 * never from the stored status column, so a due whose status hasn't been
 * auto-flipped to 'overdue' yet still lands in the correct bucket instead
 * of vanishing from every card total.
 *
 * There's no "tenant" dimension here — the second filter is due_type
 * (signing / reservation / installment / annual / delivery / maintenance /
 * variable) instead, since that's the natural second axis for this table.
 */
class PropertyInstallmentPaymentController extends Controller
{
    use AuthorizesCompany;

    public function index(Company $company)
    {
        $this->authorizeCompany($company);

        return Inertia::render('Properties/InstallmentPayments/Index', [
            'company' => $company,
        ]);
    }

    public function data(Request $request, Company $company)
    {
        $this->authorizeCompany($company);

        $filters = $request->validate([
            'property_id' => 'nullable|integer',
            'due_type'    => 'nullable|string|max:30',
            'page'        => 'nullable|integer|min:1',
            'per_page'    => 'nullable|integer|min:1|max:200',
        ]);

        $rows = $this->buildQuery($company, $filters)->get();

        $buckets = $this->computeBuckets($rows, 'due_date');

        $properties = DB::table('properties')
            ->where('company_id', $company->id)
            ->orderBy('property_name')
            ->get(['id', 'property_name']);

        // ── Pagination — same approach as Rent Collection: buckets use
        // the full filtered set, only the table itself is paginated.
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
            'due_types'         => ['signing', 'reservation', 'installment', 'annual', 'delivery', 'maintenance', 'variable'],
            'base_currency'     => strtoupper($company->currency ?: 'EGP'),
            'unconverted_count' => $rows->whereNull('base_amount')->count(),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // EXPORT — full filtered outstanding list (ignores pagination),
    // same colored style as the Rent Collection export.
    // ═══════════════════════════════════════════════════════════════════
    public function export(Request $request, Company $company)
    {
        $this->authorizeCompany($company);

        $filters = $request->validate([
            'property_id' => 'nullable|integer',
            'due_type'    => 'nullable|string|max:30',
        ]);

        $rows = $this->buildQuery($company, $filters)->get();
        $baseCurrency = strtoupper($company->currency ?: 'EGP');

        $columns = [
            ['key' => 'property_name', 'label' => 'Property',              'type' => 'dimension'],
            ['key' => 'due_type',      'label' => 'Type',                  'type' => 'dimension'],
            ['key' => 'due_date',      'label' => 'Due Date',              'type' => 'dimension'],
            ['key' => 'amount',        'label' => 'Amount',                'type' => 'measure'],
            ['key' => 'currency',      'label' => 'Currency',              'type' => 'dimension'],
            ['key' => 'base_amount',   'label' => "Amount ({$baseCurrency})", 'type' => 'measure'],
            ['key' => 'status',        'label' => 'Status',                'type' => 'dimension'],
        ];

        $exportRows = $rows->map(fn ($r) => [
            'property_name' => $r->property_name,
            'due_type'      => $this->typeLabel($r->due_type),
            'due_date'      => $r->due_date,
            'amount'        => (float) $r->amount,
            'currency'      => $r->currency,
            'base_amount'   => $r->base_amount !== null ? (float) $r->base_amount : null,
            'status'        => $r->status,
        ])->values()->all();

        $totals = [
            'property_name' => 'TOTAL',
            'base_amount'   => round($rows->sum('base_amount'), 2),
        ];

        return $this->streamColoredExcel(
            'Properties Installment Payment — Outstanding',
            $columns,
            $exportRows,
            $totals
        );
    }

    // ═══════════════════════════════════════════════════════════════════
    // Shared filtered query — outstanding (pending + overdue) installment
    // dues across the whole company.
    // ═══════════════════════════════════════════════════════════════════
    private function buildQuery(Company $company, array $filters)
    {
        $query = DB::table('property_installment_dues as pid')
            ->join('properties as p', 'p.id', '=', 'pid.property_id')
            ->where('pid.company_id', $company->id)
            ->whereIn('pid.status', ['pending', 'overdue']);

        if (!empty($filters['property_id'])) {
            $query->where('pid.property_id', $filters['property_id']);
        }
        if (!empty($filters['due_type'])) {
            $query->where('pid.due_type', $filters['due_type']);
        }

        return $query->select(
                'pid.id',
                'pid.property_id',
                'pid.due_date',
                'pid.due_type',
                'pid.amount',
                'pid.base_amount',
                'pid.currency',
                'pid.status',
                DB::raw('p.property_name'),
            )
            ->orderBy('pid.due_date')
            ->orderBy('p.property_name');
    }

    // Same date-vs-today bucketing as PropertyRentCollectionController —
    // duplicated rather than shared across controllers to keep each one
    // self-contained; see that class's doc comment for the full rationale.
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

    private function typeLabel(string $t): string
    {
        $map = [
            'signing' => 'Contract Signing', 'reservation' => 'Reservation',
            'installment' => 'Installment', 'annual' => 'Annual',
            'delivery' => 'Delivery', 'maintenance' => 'Maintenance', 'variable' => 'Payment',
        ];
        return $map[$t] ?? $t;
    }

    // Same colored-Excel builder as PropertyRentCollectionController —
    // duplicated for the same self-contained-controller reason above.
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
