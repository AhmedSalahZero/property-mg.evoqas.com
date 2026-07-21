<?php

namespace App\Jobs;

use App\Models\ExpenseCategory;
use App\Models\ExpenseItem;
use App\Models\PropertyExpense;
use App\Models\PropertyExpensePayment;
use App\Models\Company;
use App\Services\CurrencyConversionService;
use App\Services\ExpensePaymentScheduleService;
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
            $paymentTerm = !empty($headerMap['payment_term']) ? strtolower(trim((string)($row[$headerMap['payment_term']] ?? ''))) : 'cash';

            // Fix for audit C4 — convert to the company's base currency, same
            // rules as the single-entry form: prefer a manually-provided
            // fx_rate column, else fall back to the currency_rates table.
            $baseCurrency = strtoupper($this->company()->currency ?: 'EGP');
            $conversion   = $this->convertToBase($baseCurrency, $amount, $currency, $date, $fxRate);

            $expense = PropertyExpense::create([
                'company_id'          => $this->companyId,
                'property_id'         => $this->propertyId,
                'expense_category_id' => $cat->id,
                'expense_item_id'     => $item->id,
                'expense_date'        => $date,
                'expense_amount'      => $amount,
                'currency'            => $currency,
                'base_amount'         => $conversion['base_amount'],
                'base_currency'       => $conversion['base_currency'],
                'fx_rate_used'        => $conversion['fx_rate_used'],
                'fx_rate'             => $fxRate,
                'notes'               => $notes ?: null,
                'status'              => PropertyExpense::STATUS_UNPAID,
                'created_by'          => $this->userId,
            ]);

            foreach ($this->parseInitialPayments($initialPaymentsRaw) as $p) {
                $paymentConversion = $this->convertToBase($baseCurrency, $p['amount'], $currency, $p['payment_date'], $fxRate);

                PropertyExpensePayment::create([
                    'company_id'          => $this->companyId,
                    'property_expense_id' => $expense->id,
                    'payment_date'        => $p['payment_date'],
                    'amount'              => $p['amount'],
                    'base_amount'         => $paymentConversion['base_amount'],
                    'base_currency'       => $paymentConversion['base_currency'],
                    'fx_rate_used'        => $paymentConversion['fx_rate_used'],
                ]);
            }

            // Fix — bulk-imported expenses need a forecasted schedule too,
            // same as ones entered by hand, so Cash Forecast has a real date
            // to place them on instead of falling back to expense_date
            // alone. A single payment_term per Excel row (rather than a
            // full split schedule) is the confirmed scope for import — one
            // 100% row, dated expense_date + that term's days.
            $scheduleService = app(ExpensePaymentScheduleService::class);
            $forecastedDate  = $scheduleService->dateForTerm($paymentTerm, \Carbon\Carbon::parse($date));
            $scheduleService->replaceSchedule($expense, [[
                'percentage'      => 100.0,
                'amount'          => $amount,
                'forecasted_date' => $forecastedDate->toDateString(),
                'payment_term'    => $paymentTerm,
                'sort_order'      => 0,
            ]]);

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

    private ?Company $companyCache = null;

    private function company(): Company
    {
        return $this->companyCache ??= Company::findOrFail($this->companyId);
    }

    private function convertToBase(string $base, float $amount, string $currency, string $date, ?float $manualRate): array
    {
        $currency = strtoupper($currency);

        if ($currency === $base) {
            return ['base_amount' => round($amount, 2), 'base_currency' => $base, 'fx_rate_used' => 1.0];
        }

        if ($manualRate && $manualRate > 0) {
            return ['base_amount' => round($amount * $manualRate, 2), 'base_currency' => $base, 'fx_rate_used' => $manualRate];
        }

        return app(CurrencyConversionService::class)->convert($this->companyId, $base, $amount, $currency, $date);
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
