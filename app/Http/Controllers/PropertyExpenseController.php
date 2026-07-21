<?php

namespace App\Http\Controllers;

use App\Jobs\ImportPropertyExpensesJob;
use App\Models\Company;
use App\Models\Property;
use App\Models\PropertyExpense;
use App\Models\PropertyExpensePayment;
use App\Models\ExpenseItem;
use App\Models\ExpenseCategory;
use App\Services\CurrencyConversionService;
use App\Services\ExpensePaymentScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Http\Controllers\Concerns\AuthorizesCompany;

class PropertyExpenseController extends Controller
{
    use AuthorizesCompany;

    // ═══════════════════════════════════════════════════════════════════
    // INDEX
    // ═══════════════════════════════════════════════════════════════════
    public function index(Company $company, Property $property)
    {
        $this->authorizeCompany($company);
        $this->authorizeProperty($company, $property);

        $expenses = PropertyExpense::where('company_id', $company->id)
            ->where('property_id', $property->id)
            ->with([
                'expenseCategory:id,category_name',
                'expenseItem:id,item_name',
                'payments',
                'paymentSchedule',
            ])
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get()
            ->map(function ($e) {
                return [
                    'id'                  => $e->id,
                    'expense_category'    => $e->expenseCategory?->category_name,
                    'expense_item'        => $e->expenseItem?->item_name,
                    'expense_date'        => $e->expense_date->format('Y-m-d'),
                    'expense_amount'      => (float) $e->expense_amount,
                    'currency'            => $e->currency,
                    'fx_rate'             => $e->fx_rate ? (float) $e->fx_rate : null,
                    'notes'               => $e->notes,
                    'status'              => $e->status,
                    'total_paid'          => $e->totalPaid(),
                    'balance'             => $e->balance(),
                    'payments'            => $e->payments->map(fn($p) => [
                        'id'           => $p->id,
                        'payment_date' => $p->payment_date->format('Y-m-d'),
                        'amount'       => (float) $p->amount,
                    ])->values()->all(),
                    'payment_schedule'    => $e->paymentSchedule->map(fn($s) => [
                        'id'              => $s->id,
                        'percentage'      => (float) $s->percentage,
                        'amount'          => (float) $s->amount,
                        'forecasted_date' => $s->forecasted_date->format('Y-m-d'),
                        'payment_term'    => $s->payment_term,
                    ])->values()->all(),
                ];
            });

        // Load expense categories with their items for the form selectors
        $expenseCategories = ExpenseCategory::where('company_id', $company->id)
            ->with(['items' => fn($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get(['id', 'category_name']);

        return Inertia::render('Properties/Expenses/Index', [
            'company'           => $company,
            'property'          => $property->load([
                'propertyCategory:id,category_name',
                'propertyType:id,type_name',
            ]),
            'expenses'          => $expenses,
            'expenseCategories' => $expenseCategories,
            'currencyOptions'   => $this->currencyOptions(),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // STORE
    // ═══════════════════════════════════════════════════════════════════
    public function store(Request $request, Company $company, Property $property)
    {
        $this->authorizeCompany($company);
        $this->authorizeProperty($company, $property);

        $data = $request->validate([
            'expense_category_id'       => 'required|exists:expense_categories,id',
            'expense_item_id'           => 'required|exists:expense_items,id',
            'expense_date'              => 'required|date',
            'expense_amount'            => 'required|numeric|min:0.01',
            'currency'                  => 'required|string|max:10',
            'fx_rate'                   => 'nullable|numeric|min:0',
            'notes'                     => 'nullable|string|max:1000',
            'payments'                  => 'nullable|array',
            'payments.*.payment_date'   => 'required|date',
            'payments.*.amount'         => 'required|numeric|min:0.01',
            // Payment Schedule repeater — % / forecasted date (or a
            // built-in term) per row. Required: this feature replaces
            // relying on expense_date alone as Cash Forecast's only signal,
            // so every new expense needs an explicit forecasted schedule.
            'payment_schedule'                        => 'required|array|min:1',
            'payment_schedule.*.percentage'            => 'required|numeric|min:0.01|max:100',
            'payment_schedule.*.forecasted_date'       => 'nullable|date',
            'payment_schedule.*.payment_term'          => 'nullable|string|in:cash,net_30,net_45,net_60,net_75,net_90,net_120,net_150,net_180',
        ]);

        // Fix for audit finding F-2 pattern, applied consistently here too —
        // validating the schedule BEFORE creating anything means a rejected
        // schedule (e.g. percentages don't total 100%) never leaves behind
        // a half-created expense with no schedule at all.
        $scheduleService = app(ExpensePaymentScheduleService::class);
        try {
            $cleanSchedule = $scheduleService->validateAndBuildRows(
                $data['payment_schedule'],
                (float) $data['expense_amount'],
                \Carbon\Carbon::parse($data['expense_date'])
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['payment_schedule' => $e->getMessage()])->withInput();
        }

        $conversion = $this->convertToBase($company, (float) $data['expense_amount'], $data['currency'], $data['expense_date'], $data['fx_rate'] ?? null);

        $expense = DB::transaction(function () use ($company, $property, $data, $conversion, $scheduleService, $cleanSchedule) {
            $expense = PropertyExpense::create([
                'company_id'          => $company->id,
                'property_id'         => $property->id,
                'expense_category_id' => $data['expense_category_id'],
                'expense_item_id'     => $data['expense_item_id'],
                'expense_date'        => $data['expense_date'],
                'expense_amount'      => $data['expense_amount'],
                'currency'            => $data['currency'],
                'base_amount'         => $conversion['base_amount'],
                'base_currency'       => $conversion['base_currency'],
                'fx_rate_used'        => $conversion['fx_rate_used'],
                'fx_rate'             => $data['fx_rate'] ?? null,
                'notes'               => $data['notes'] ?? null,
                'status'              => 'unpaid',
                'created_by'          => auth()->id(),
            ]);

            foreach ($data['payments'] ?? [] as $p) {
                $paymentConversion = $this->convertToBase($company, (float) $p['amount'], $data['currency'], $p['payment_date'], $data['fx_rate'] ?? null);

                PropertyExpensePayment::create([
                    'company_id'          => $company->id,
                    'property_expense_id' => $expense->id,
                    'payment_date'        => $p['payment_date'],
                    'amount'              => $p['amount'],
                    'base_amount'         => $paymentConversion['base_amount'],
                    'base_currency'       => $paymentConversion['base_currency'],
                    'fx_rate_used'        => $paymentConversion['fx_rate_used'],
                ]);
            }

            $scheduleService->replaceSchedule($expense, $cleanSchedule);

            $expense->recalculateStatus();

            return $expense;
        });

        return back()->with('success', 'Expense added successfully.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // UPDATE
    // ═══════════════════════════════════════════════════════════════════
    public function update(Request $request, Company $company, Property $property, PropertyExpense $expense)
    {
        $this->authorizeCompany($company);
        $this->authorizeProperty($company, $property);
        $this->authorizeExpense($property, $expense);

        $data = $request->validate([
            'expense_category_id'       => 'required|exists:expense_categories,id',
            'expense_item_id'           => 'required|exists:expense_items,id',
            'expense_date'              => 'required|date',
            'expense_amount'            => 'required|numeric|min:0.01',
            'currency'                  => 'required|string|max:10',
            'fx_rate'                   => 'nullable|numeric|min:0',
            'notes'                     => 'nullable|string|max:1000',
            'payment_schedule'                        => 'required|array|min:1',
            'payment_schedule.*.percentage'            => 'required|numeric|min:0.01|max:100',
            'payment_schedule.*.forecasted_date'       => 'nullable|date',
            'payment_schedule.*.payment_term'          => 'nullable|string|in:cash,net_30,net_45,net_60,net_75,net_90,net_120,net_150,net_180',
        ]);

        $scheduleService = app(ExpensePaymentScheduleService::class);
        try {
            $cleanSchedule = $scheduleService->validateAndBuildRows(
                $data['payment_schedule'],
                (float) $data['expense_amount'],
                \Carbon\Carbon::parse($data['expense_date'])
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['payment_schedule' => $e->getMessage()])->withInput();
        }

        $conversion = $this->convertToBase($company, (float) $data['expense_amount'], $data['currency'], $data['expense_date'], $data['fx_rate'] ?? null);

        DB::transaction(function () use ($expense, $data, $conversion, $scheduleService, $cleanSchedule) {
            $expense->update([
                'expense_category_id' => $data['expense_category_id'],
                'expense_item_id'     => $data['expense_item_id'],
                'expense_date'        => $data['expense_date'],
                'expense_amount'      => $data['expense_amount'],
                'currency'            => $data['currency'],
                'base_amount'         => $conversion['base_amount'],
                'base_currency'       => $conversion['base_currency'],
                'fx_rate_used'        => $conversion['fx_rate_used'],
                'fx_rate'             => $data['fx_rate'] ?? null,
                'notes'               => $data['notes'] ?? null,
            ]);

            $scheduleService->replaceSchedule($expense, $cleanSchedule);

            $expense->recalculateStatus();
        });

        return back()->with('success', 'Expense updated successfully.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // DESTROY
    // ═══════════════════════════════════════════════════════════════════
    public function destroy(Company $company, Property $property, PropertyExpense $expense)
    {
        $this->authorizeCompany($company);
        $this->authorizeProperty($company, $property);
        $this->authorizeExpense($property, $expense);
        $expense->payments()->delete();
        $expense->delete();
        return back()->with('success', 'Expense deleted.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // ADD PAYMENT
    // ═══════════════════════════════════════════════════════════════════
    public function addPayment(Request $request, Company $company, Property $property, PropertyExpense $expense)
    {
        $this->authorizeCompany($company);
        $this->authorizeProperty($company, $property);
        $this->authorizeExpense($property, $expense);

        $data = $request->validate([
            'payments'                => 'required|array|min:1',
            'payments.*.payment_date' => 'required|date',
            'payments.*.amount'       => 'required|numeric|min:0.01',
        ]);

        foreach ($data['payments'] as $p) {
            $conversion = $this->convertToBase($company, (float) $p['amount'], $expense->currency, $p['payment_date'], $expense->fx_rate ? (float) $expense->fx_rate : null);

            PropertyExpensePayment::create([
                'company_id'          => $company->id,
                'property_expense_id' => $expense->id,
                'payment_date'        => $p['payment_date'],
                'amount'              => $p['amount'],
                'base_amount'         => $conversion['base_amount'],
                'base_currency'       => $conversion['base_currency'],
                'fx_rate_used'        => $conversion['fx_rate_used'],
            ]);
        }

        $expense->recalculateStatus();

        return back()->with('success', 'Payment recorded successfully.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // DELETE PAYMENT
    // ═══════════════════════════════════════════════════════════════════
    public function deletePayment(Company $company, Property $property, PropertyExpense $expense, PropertyExpensePayment $payment)
    {
        $this->authorizeCompany($company);
        $this->authorizeProperty($company, $property);
        $this->authorizeExpense($property, $expense);
        abort_unless($payment->property_expense_id === $expense->id, 404);
        $payment->delete();
        $expense->recalculateStatus();
        return back()->with('success', 'Payment removed.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // IMPORT EXPENSES EXCEL (QUEUE)
    // ═══════════════════════════════════════════════════════════════════
    public function import(Request $request, Company $company, Property $property)
    {
        $this->authorizeCompany($company);
        $this->authorizeProperty($company, $property);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        $file = $request->file('file');
        $reader = IOFactory::createReaderForFile($file->getRealPath());
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($file->getRealPath())->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        if (count($rows) < 2) {
            return back()->withErrors(['file' => 'Excel file must contain header + at least one data row.']);
        }

        $headerMap = [];
        foreach ($rows[1] as $col => $name) {
            $key = strtolower(trim((string) $name));
            if ($key !== '') $headerMap[$key] = $col;
        }

        $requiredColumns = ['expense_category', 'expense_item', 'expense_date', 'expense_amount', 'currency', 'payment_term'];
        foreach ($requiredColumns as $colName) {
            if (!isset($headerMap[$colName])) {
                return back()->withErrors(['file' => "Missing required column: {$colName}"]);
            }
        }

        $allowedTerms = ['cash', 'net_30', 'net_45', 'net_60', 'net_75', 'net_90', 'net_120', 'net_150', 'net_180'];

        $allowedCurrencies = $this->currencyOptions();
        $catMap = ExpenseCategory::where('company_id', $company->id)
            ->get(['id', 'category_name'])
            ->keyBy(fn ($c) => strtolower(trim($c->category_name)));

        $itemMap = ExpenseItem::where('company_id', $company->id)
            ->where('is_active', true)
            ->get(['id', 'expense_category_id', 'item_name'])
            ->groupBy(fn ($i) => strtolower(trim($i->item_name)));

        $validRows = 0;
        for ($r = 2; $r <= count($rows); $r++) {
            $row = $rows[$r] ?? [];
            $empty = true;
            foreach ($row as $v) {
                if (trim((string) $v) !== '') { $empty = false; break; }
            }
            if ($empty) continue;
            $validRows++;

            $categoryName = strtolower(trim((string)($row[$headerMap['expense_category']] ?? '')));
            $itemName = strtolower(trim((string)($row[$headerMap['expense_item']] ?? '')));
            $rawDate = $row[$headerMap['expense_date']] ?? null;
            $amountRaw = trim((string)($row[$headerMap['expense_amount']] ?? ''));
            $currency = strtoupper(trim((string)($row[$headerMap['currency']] ?? '')));

            if ($categoryName === '' || $itemName === '' || $amountRaw === '' || $currency === '' || $rawDate === null || $rawDate === '') {
                return back()->withErrors(['file' => "Row {$r}: all required fields must be filled."]);
            }

            $category = $catMap[$categoryName] ?? null;
            if (!$category) {
                return back()->withErrors(['file' => "Row {$r}: expense_category not found in company settings."]);
            }

            $item = $itemMap[$itemName]?->firstWhere('expense_category_id', $category->id);
            if (!$item) {
                return back()->withErrors(['file' => "Row {$r}: expense_item is invalid for selected category."]);
            }

            if (!is_numeric($amountRaw) || (float) $amountRaw <= 0) {
                return back()->withErrors(['file' => "Row {$r}: expense_amount must be a number greater than 0."]);
            }

            if (!in_array($currency, $allowedCurrencies, true)) {
                return back()->withErrors(['file' => "Row {$r}: currency must be one of " . implode(', ', $allowedCurrencies)]);
            }

            $paymentTerm = strtolower(trim((string)($row[$headerMap['payment_term']] ?? '')));
            if (!in_array($paymentTerm, $allowedTerms, true)) {
                return back()->withErrors(['file' => "Row {$r}: payment_term must be one of " . implode(', ', $allowedTerms)]);
            }

            if (is_numeric($rawDate)) {
                try {
                    ExcelDate::excelToDateTimeObject((float) $rawDate)->format('Y-m-d');
                } catch (\Throwable $e) {
                    return back()->withErrors(['file' => "Row {$r}: expense_date is invalid."]);
                }
            } else {
                if (strtotime((string) $rawDate) === false) {
                    return back()->withErrors(['file' => "Row {$r}: expense_date is invalid."]);
                }
            }

            if (isset($headerMap['fx_rate'])) {
                $fxRaw = trim((string)($row[$headerMap['fx_rate']] ?? ''));
                if ($fxRaw !== '' && (!is_numeric($fxRaw) || (float) $fxRaw < 0)) {
                    return back()->withErrors(['file' => "Row {$r}: fx_rate must be a positive number or empty."]);
                }
            }

            if (isset($headerMap['initial_payments'])) {
                $paymentsRaw = trim((string)($row[$headerMap['initial_payments']] ?? ''));
                if ($paymentsRaw !== '') {
                    try {
                        $this->parseInitialPayments($paymentsRaw);
                    } catch (\InvalidArgumentException $e) {
                        return back()->withErrors(['file' => "Row {$r}: " . $e->getMessage()]);
                    }
                }
            }
        }

        if ($validRows === 0) {
            return back()->withErrors(['file' => 'No valid data rows found.']);
        }

        $storedPath = $file->store('imports/property-expenses');

        ImportPropertyExpensesJob::dispatch(
            $company->id,
            $property->id,
            auth()->id(),
            $storedPath
        );

        return back()->with('success', 'Excel uploaded successfully. Import is queued and will run in background.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // DOWNLOAD EXPENSES TEMPLATE
    // ═══════════════════════════════════════════════════════════════════
    public function downloadTemplate(Company $company, Property $property)
    {
        $this->authorizeCompany($company);
        $this->authorizeProperty($company, $property);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Expenses Template');

        $headers = [
            'expense_category',
            'expense_item',
            'expense_date',
            'expense_amount',
            'currency',
            'fx_rate',
            'payment_term',
            'initial_payments',
            'notes',
        ];

        foreach ($headers as $i => $header) {
            $sheet->setCellValueByColumnAndRow($i + 1, 1, $header);
        }

        $sheet->setCellValue('A2', 'General & Admin');
        $sheet->setCellValue('B2', 'Consultancy Expenses');
        $sheet->setCellValue('C2', now()->toDateString());
        $sheet->setCellValue('D2', '7000');
        $sheet->setCellValue('E2', 'EGP');
        $sheet->setCellValue('F2', '1');
        $sheet->setCellValue('G2', 'net_30');
        $sheet->setCellValue('H2', now()->toDateString() . ':3000|' . now()->addMonth()->toDateString() . ':4000');
        $sheet->setCellValue('I2', 'Example with two initial payments');

        // payment_term dropdown — same built-in terms as the on-screen
        // repeater (see ExpensePaymentScheduleService). Every imported row
        // gets a single 100% schedule row dated expense_date + this term's
        // days (see ImportPropertyExpensesJob), so Cash Forecast has a real
        // forecasted date for bulk-imported expenses too, not just ones
        // entered by hand.
        $termOptions = ['cash', 'net_30', 'net_45', 'net_60', 'net_75', 'net_90', 'net_120', 'net_150', 'net_180'];
        $validation = $sheet->getCell('G2')->getDataValidation();
        $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
        $validation->setAllowBlank(false);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setShowDropDown(true);
        $validation->setPromptTitle('Payment Term');
        $validation->setPrompt('Pick a built-in payment term for this expense.');
        $validation->setErrorTitle('Invalid term');
        $validation->setError('Please pick a value from the dropdown list.');
        $validation->setFormula1('"' . implode(',', $termOptions) . '"');
        for ($row = 2; $row <= 200; $row++) {
            $sheet->getCell("G{$row}")->setDataValidation(clone $validation);
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'property_expenses_template.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════════
    /**
     * Fix for audit finding C4 — convert an amount into the company's base
     * currency (companies.currency) at write time, so mixed-currency expenses
     * never get summed 1:1 with EGP elsewhere in the app.
     *
     * If the expense itself carries a manually-entered $manualRate (the
     * existing "fx_rate" field on the form — previously captured but never
     * actually used anywhere), that takes precedence, since it reflects the
     * rate the user actually observed/paid for this specific transaction.
     * Otherwise falls back to the company's currency_rates table for the
     * given date.
     */
    private function convertToBase(Company $company, float $amount, string $currency, $date, ?float $manualRate = null): array
    {
        $base     = strtoupper($company->currency ?: 'EGP');
        $currency = strtoupper($currency);

        if ($currency === $base) {
            return ['base_amount' => round($amount, 2), 'base_currency' => $base, 'fx_rate_used' => 1.0];
        }

        if ($manualRate && $manualRate > 0) {
            return ['base_amount' => round($amount * $manualRate, 2), 'base_currency' => $base, 'fx_rate_used' => $manualRate];
        }

        return app(CurrencyConversionService::class)->convert($company->id, $base, $amount, $currency, $date);
    }

    /**
     * Fix for audit finding C-2 — see the same fix in PropertyController /
     * RentContractController. authorizeCompany() alone doesn't confirm
     * {property}/{expense}/{payment} (all resolved by Laravel with no
     * company filter) actually belong to the URL's {company} chain.
     */
    private function authorizeProperty(Company $company, Property $property): void
    {
        abort_unless($property->company_id === $company->id, 404);
    }

    private function authorizeExpense(Property $property, PropertyExpense $expense): void
    {
        abort_unless($expense->property_id === $property->id, 404);
    }

    private function currencyOptions(): array
    {
        return ['EGP', 'USD', 'EUR', 'GBP', 'SAR', 'AED'];
    }

    private function parseInitialPayments(string $raw): array
    {
        $entries = preg_split('/[|;]/', $raw);
        $payments = [];

        foreach ($entries as $entry) {
            $entry = trim($entry);
            if ($entry === '') continue;

            if (!str_contains($entry, ':')) {
                throw new \InvalidArgumentException('initial_payments format must be date:amount|date:amount');
            }

            [$datePart, $amountPart] = array_map('trim', explode(':', $entry, 2));
            if ($datePart === '' || $amountPart === '') {
                throw new \InvalidArgumentException('initial_payments contains empty date or amount.');
            }

            if (strtotime($datePart) === false) {
                throw new \InvalidArgumentException("invalid payment date '{$datePart}'.");
            }

            if (!is_numeric($amountPart) || (float) $amountPart <= 0) {
                throw new \InvalidArgumentException("invalid payment amount '{$amountPart}'.");
            }

            $payments[] = [
                'payment_date' => date('Y-m-d', strtotime($datePart)),
                'amount' => (float) $amountPart,
            ];
        }

        return $payments;
    }
}