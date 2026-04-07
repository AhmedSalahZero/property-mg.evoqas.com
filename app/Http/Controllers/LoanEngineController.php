<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\LoanEngine;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class LoanEngineController extends Controller
{
    // ─────────────────────────────────────────────────────────────────
    // Show UI
    // ─────────────────────────────────────────────────────────────────

    public function index()
    {
        return Inertia::render('LoanEngine/Index');
    }

    // ─────────────────────────────────────────────────────────────────
    // Calculate (JSON)
    // ─────────────────────────────────────────────────────────────────

    public function calculate(Request $request)
    {
        $validated = $this->validateParams($request);
        $params    = $this->normaliseParams($validated);

        try {
            $result = LoanEngine::generate($params);
            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Export to Excel
    // ─────────────────────────────────────────────────────────────────

    public function export(Request $request)
    {
        $validated = $this->validateParams($request);
        $params    = $this->normaliseParams($validated);
        $result    = LoanEngine::generate($params);

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Amortisation Schedule');

        // ── Colour palette ────────────────────────────────────────────
        $NAVY      = '0B1120';
        $BLUE_HDR  = '1E3A5F';
        $BLUE_LBL  = '1a2e4a';
        $YELLOW    = 'FACC15';
        $GREEN     = '10B981';
        $GRACE_BG  = '2D2A1A';
        $LAST_BG   = '1A2D1F';
        $WHITE     = 'FFFFFF';
        $LIGHT_ROW = '111827';
        $ALT_ROW   = '0F1923';
        $BORDER    = '2d4163';

        // ── Helper: apply style to range ─────────────────────────────
        $style = fn(string $range) => $sheet->getStyle($range);

        // ══════════════════════════════════════════════════════════════
        // SECTION 1 — Loan Parameters (rows 1–14)
        // ══════════════════════════════════════════════════════════════
        $p          = $result['params'];
        $isExpanded = !empty($p['is_expanded']);
        $isStepped  = !empty($p['is_stepped']);
        $isVariable = !empty($p['is_variable']);

        $sheet->mergeCells('A1:D1');
        $sheet->setCellValue('A1', 'FINVERO — LOAN AMORTISATION SCHEDULE');
        $style('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => $YELLOW]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        $labelRows = [
            3  => ['Principal (EGP)',       number_format($p['principal'], 2)],
            4  => ['Annual Rate',           round($p['annual_rate'] * 100, 4) . '%'],
            5  => ['Term',                  $p['term_months'] . ' months'],
            6  => ['Interval',              ucfirst(str_replace('_', '-', $p['installment_interval']))],
            7  => ['Total Periods',         $p['total_periods']],
            8  => ['Grace Periods',         $p['grace_periods']],
            9  => ['Disbursement Date',     $p['disbursement_date']],
            10 => ['Payment Timing',        ucfirst($p['payment_timing'])],
            11 => ['Schedule Type',         ucfirst(str_replace('_', ' ', $p['schedule_type']))],
            12 => ['PMT / Base PMT (EGP)',    number_format($p['pmt_base'] ?? $p['pmt'] ?? 0, 2)],
        ];

        foreach ($labelRows as $row => [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $value);
            $style("A{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => '7fa8c9']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $BLUE_LBL]],
            ]);
            $style("B{$row}")->applyFromArray([
                'font' => ['color' => ['rgb' => $WHITE]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $NAVY]],
            ]);
        }

        // ══════════════════════════════════════════════════════════════
        // SECTION 2 — Summary (rows 3–12 cols D–E)
        // ══════════════════════════════════════════════════════════════
        $s = $result['summary'];
        $summaryRows = [
            3  => ['SUMMARY', ''],
            4  => ['Original Principal',   number_format($s['original_principal'], 2)],
            5  => ['Total Interest',       number_format($s['total_interest'], 2)],
            6  => ['Total Principal Paid', number_format($s['total_principal_paid'], 2)],
            7  => ['Total Installments',   number_format($s['total_installments'], 2)],
            8  => ['Number of Periods',    $s['periods']],
        ];

        foreach ($summaryRows as $row => [$label, $value]) {
            $sheet->setCellValue("D{$row}", $label);
            $sheet->setCellValue("E{$row}", $value);
        }

        $style('D3')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => $YELLOW]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $BLUE_HDR]],
        ]);

        // ══════════════════════════════════════════════════════════════
        // SECTION 3 — Schedule Table
        // Columns differ: monthly vs expanded (quarterly/semi-annual)
        // ══════════════════════════════════════════════════════════════
        $headerRow = 15;
        $ACCRUAL_BG = '1A1A0A';

        if ($isExpanded) {
            // Monthly + quarterly/semi-annual expanded columns
            $headers = [
                'A' => '#', 'B' => 'Date', 'C' => 'Days',
                'D' => 'Annual Rate', 'E' => 'Period Rate',
                'F' => 'Opening Balance',
                'G' => 'Monthly Interest', 'H' => 'Interest Payment',
                'I' => 'Principal', 'J' => 'Installment',
                'K' => 'Closing Balance', 'L' => 'Note',
            ];
            $lastCol   = 'L';
            $interestTotalCol = 'H';
            $principalCol     = 'I';
            $installmentCol   = 'J';
            $closingCol       = 'K';
            $noteCol          = 'L';
            $numCols          = ['F','G','H','I','J','K'];
        } else {
            $headers = [
                'A' => '#', 'B' => 'Period', 'C' => 'Days',
                'D' => 'Annual Rate', 'E' => 'Period Rate',
                'F' => 'Opening Balance',
                'G' => 'Interest',
                'H' => 'Principal', 'I' => 'Installment',
                'J' => 'Closing Balance', 'K' => 'Note',
            ];
            $lastCol          = 'K';
            $interestTotalCol = 'G';
            $principalCol     = 'H';
            $installmentCol   = 'I';
            $closingCol       = 'J';
            $noteCol          = 'K';
            $numCols          = ['F','G','H','I','J'];
        }

        foreach ($headers as $col => $label) {
            $sheet->setCellValue("{$col}{$headerRow}", $label);
        }

        $style("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => $WHITE], 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $BLUE_HDR]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $BORDER]]],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(20);

        // Data rows
        $dataStart = $headerRow + 1;
        $numFmt    = NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2;

        foreach ($result['schedule'] as $i => $row) {
            $r      = $dataStart + $i;
            $isDisb = ($row['row_type'] === 'disbursement');
            $isAccr = ($row['row_type'] === 'accrual');

            $bg = match(true) {
                $isDisb         => '0d1f3a',
                $isAccr         => $ACCRUAL_BG,
                $row['is_grace']=> $GRACE_BG,
                $row['is_last'] => $LAST_BG,
                $i % 2 === 0    => $LIGHT_ROW,
                default         => $ALT_ROW,
            };

            // Row number / label
            $sheet->setCellValue("A{$r}", $isDisb ? 'D' : $row['month_num']);
            $sheet->setCellValue("B{$r}", $row['period_label']);
            $sheet->setCellValue("C{$r}", $isDisb ? '—' : $row['days_in_period']);
            $sheet->setCellValue("D{$r}", $isDisb ? '—' : $row['annual_rate']);
            $sheet->setCellValue("E{$r}", $isDisb ? '—' : $row['period_rate']);
            $sheet->setCellValue("F{$r}", $row['opening_balance']);

            if ($isExpanded) {
                $sheet->setCellValue("G{$r}", $isDisb ? 0 : $row['monthly_interest']);
                $sheet->setCellValue("H{$r}", $isDisb ? 0 : $row['interest_payment']);
                $sheet->setCellValue("I{$r}", $row['principal']);
                $sheet->setCellValue("J{$r}", $isDisb ? $row['disbursement'] : $row['installment']);
                $sheet->setCellValue("K{$r}", $row['closing_balance']);
                $sheet->setCellValue("L{$r}", $row['note']);
            } else {
                $sheet->setCellValue("G{$r}", $isDisb ? 0 : $row['interest']);
                $sheet->setCellValue("H{$r}", $row['principal']);
                $sheet->setCellValue("I{$r}", $isDisb ? $row['disbursement'] : $row['installment']);
                $sheet->setCellValue("J{$r}", $row['closing_balance']);
                $sheet->setCellValue("K{$r}", $row['note']);
            }

            // Number formats
            foreach ($numCols as $col) {
                $sheet->getStyle("{$col}{$r}")->getNumberFormat()->setFormatCode($numFmt);
            }

            // Row styling
            $style("A{$r}:{$lastCol}{$r}")->applyFromArray([
                'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
                'font'    => ['color' => ['rgb' => $WHITE], 'size' => 9],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => $BORDER]]],
            ]);

            // Italic for accrual rows
            if ($isAccr) {
                $style("A{$r}:{$lastCol}{$r}")->getFont()->setItalic(true);
            }

            // Yellow highlight on adjusted installment
            if (!empty($row['is_adjusted'])) {
                $sheet->getStyle("{$installmentCol}{$r}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => $YELLOW]],
                ]);
            }

            $sheet->getRowDimension($r)->setRowHeight(15);
        }

        // Totals row
        $totalRow = $dataStart + count($result['schedule']);
        $sheet->setCellValue("A{$totalRow}", 'TOTALS');
        $sheet->setCellValue("{$interestTotalCol}{$totalRow}", $result['summary']['total_interest']);
        $sheet->setCellValue("{$principalCol}{$totalRow}", $result['summary']['total_principal_paid']);
        $sheet->setCellValue("{$installmentCol}{$totalRow}", $result['summary']['total_installments']);

        foreach ([$interestTotalCol, $principalCol, $installmentCol] as $col) {
            $sheet->getStyle("{$col}{$totalRow}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2);
        }

        $style("A{$totalRow}:{$lastCol}{$totalRow}")->applyFromArray([
            'font'    => ['bold' => true, 'color' => ['rgb' => $YELLOW], 'size' => 10],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $BLUE_HDR]],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => $YELLOW]]],
        ]);

        // ── Column widths ─────────────────────────────────────────────
        if ($isExpanded) {
            $widths = ['A'=>5,'B'=>16,'C'=>5,'D'=>10,'E'=>10,'F'=>18,'G'=>16,'H'=>16,'I'=>16,'J'=>18,'K'=>18,'L'=>26];
        } else {
            $widths = ['A'=>5,'B'=>16,'C'=>5,'D'=>10,'E'=>10,'F'=>18,'G'=>16,'H'=>16,'I'=>18,'J'=>18,'K'=>26];
        }
        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // ── Freeze panes ──────────────────────────────────────────────
        $sheet->freezePane("A{$dataStart}");

        // ── Output ────────────────────────────────────────────────────
        $interval = $p['installment_interval'] ?? 'monthly';
        $filename = 'LoanSchedule_' . $p['disbursement_date'] . '_' . ucfirst($interval) . '.xlsx';

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // Shared: validate + normalise
    // ─────────────────────────────────────────────────────────────────

    private function validateParams(Request $request): array
    {
        return $request->validate([
            'principal'            => 'required|numeric|min:1',
            'annual_rate'          => 'required|numeric|min:0',
            'term_months'          => 'required|integer|min:1|max:360',
            'disbursement_date'    => 'required|date',
            'payment_timing'       => 'required|in:end,begin',
            'installment_interval' => 'required|in:monthly,quarterly,semi_annual',
            'schedule_type'        => 'required|in:normal,variable,step_up,step_down,grace_no_cap,grace_cap,step_up_grace,step_down_grace',
            'grace_months'         => 'nullable|integer|min:0',
            // Step-up / step-down installment parameters
            'step_pct'             => 'nullable|numeric|min:0|max:100',
            'step_interval'        => 'nullable|in:semi_annual,annual',
            // CBE corridor changes
            'corridor_changes'                     => 'nullable|array',
            'corridor_changes.*.date'              => 'required_with:corridor_changes|date',
            'corridor_changes.*.corridor_rate'     => 'required_with:corridor_changes|numeric|min:0',
            'corridor_changes.*.margin'            => 'required_with:corridor_changes|numeric|min:0',
        ]);
    }

    private function normaliseParams(array $validated): array
    {
        // Percentages → decimals
        $validated['annual_rate'] = $validated['annual_rate'] / 100;

        if (!empty($validated['step_pct'])) {
            $validated['step_pct'] = $validated['step_pct'] / 100;
        }

        if (!empty($validated['corridor_changes'])) {
            foreach ($validated['corridor_changes'] as &$change) {
                $change['corridor_rate'] = $change['corridor_rate'] / 100;
                $change['margin']        = $change['margin'] / 100;
            }
        }

        return $validated;
    }
}