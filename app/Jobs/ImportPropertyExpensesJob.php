<?php

namespace App\Jobs;

use App\Models\ExpenseCategory;
use App\Models\ExpenseItem;
use App\Models\PropertyExpense;
use App\Models\PropertyExpensePayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ImportPropertyExpensesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public int $companyId,
        public int $propertyId,
        public int $userId,
        public string $filePath
    ) {}

    public function handle(): void
    {
        $fullPath = Storage::path($this->filePath);
        $reader = IOFactory::createReaderForFile($fullPath);
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($fullPath)->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        if (count($rows) < 2) {
            return;
        }

        $headerRow = $rows[1];
        $headerMap = [];
        foreach ($headerRow as $col => $name) {
            $key = strtolower(trim((string) $name));
            if ($key !== '') {
                $headerMap[$key] = $col;
            }
        }

        $catMap = ExpenseCategory::where('company_id', $this->companyId)
            ->get(['id', 'category_name'])
            ->keyBy(fn ($c) => strtolower(trim($c->category_name)));

        $itemMap = ExpenseItem::where('company_id', $this->companyId)
            ->where('is_active', true)
            ->get(['id', 'expense_category_id', 'item_name'])
            ->groupBy(fn ($i) => strtolower(trim($i->item_name)));

        $required = ['expense_category', 'expense_item', 'expense_date', 'expense_amount', 'currency'];

        $imported = 0;
        for ($r = 2; $r <= count($rows); $r++) {
            $row = $rows[$r] ?? [];
            $empty = true;
            foreach ($row as $v) {
                if (trim((string) $v) !== '') { $empty = false; break; }
            }
            if ($empty) continue;

            $vals = [];
            foreach ($required as $colName) {
                $col = $headerMap[$colName] ?? null;
                $vals[$colName] = $col ? trim((string)($row[$col] ?? '')) : '';
            }

            $categoryKey = strtolower(trim((string) $vals['expense_category']));
            $itemKey = strtolower(trim((string) $vals['expense_item']));

            $cat = $catMap[$categoryKey] ?? null;
            if (!$cat) continue;

            $item = $itemMap[$itemKey]?->firstWhere('expense_category_id', $cat->id);
            if (!$item) continue;

            $excelDateCell = $headerMap['expense_date'] ?? null;
            $rawDate = $excelDateCell ? ($row[$excelDateCell] ?? null) : null;
            $date = null;
            if (is_numeric($rawDate)) {
                $date = ExcelDate::excelToDateTimeObject((float) $rawDate)->format('Y-m-d');
            } elseif (!empty($rawDate)) {
                $date = date('Y-m-d', strtotime((string) $rawDate));
            }
            if (!$date) continue;

            $amount = (float) $vals['expense_amount'];
            if ($amount <= 0) continue;

            $currency = strtoupper($vals['currency']);
            $fxRate = null;
            if (!empty($headerMap['fx_rate'])) {
                $fxRaw = trim((string)($row[$headerMap['fx_rate']] ?? ''));
                $fxRate = $fxRaw === '' ? null : (float) $fxRaw;
            }
            $notes = !empty($headerMap['notes']) ? trim((string)($row[$headerMap['notes']] ?? '')) : null;
            $initialPaymentsRaw = !empty($headerMap['initial_payments']) ? trim((string)($row[$headerMap['initial_payments']] ?? '')) : '';

            $expense = PropertyExpense::create([
                'company_id'          => $this->companyId,
                'property_id'         => $this->propertyId,
                'expense_category_id' => $cat->id,
                'expense_item_id'     => $item->id,
                'expense_date'        => $date,
                'expense_amount'      => $amount,
                'currency'            => $currency,
                'fx_rate'             => $fxRate,
                'notes'               => $notes ?: null,
                'status'              => PropertyExpense::STATUS_UNPAID,
                'created_by'          => $this->userId,
            ]);

            foreach ($this->parseInitialPayments($initialPaymentsRaw) as $p) {
                PropertyExpensePayment::create([
                    'company_id'          => $this->companyId,
                    'property_expense_id' => $expense->id,
                    'payment_date'        => $p['payment_date'],
                    'amount'              => $p['amount'],
                ]);
            }

            $expense->recalculateStatus();
            $imported++;
        }

        Storage::delete($this->filePath);
        Log::info('Property expenses import completed', [
            'company_id' => $this->companyId,
            'property_id' => $this->propertyId,
            'imported_rows' => $imported,
        ]);
    }

    private function parseInitialPayments(string $raw): array
    {
        if ($raw === '') return [];

        $entries = preg_split('/[|;]/', $raw);
        $payments = [];

        foreach ($entries as $entry) {
            $entry = trim($entry);
            if ($entry === '' || !str_contains($entry, ':')) continue;

            [$datePart, $amountPart] = array_map('trim', explode(':', $entry, 2));
            if ($datePart === '' || $amountPart === '') continue;
            if (strtotime($datePart) === false) continue;
            if (!is_numeric($amountPart) || (float) $amountPart <= 0) continue;

            $payments[] = [
                'payment_date' => date('Y-m-d', strtotime($datePart)),
                'amount' => (float) $amountPart,
            ];
        }

        return $payments;
    }
}
