<?php

namespace App\Http\Controllers;

use App\Jobs\ImportPropertyExpensesJob;
use App\Models\Company;
use App\Models\Property;
use App\Models\PropertyExpense;
use App\Models\PropertyExpensePayment;
use App\Models\ExpenseItem;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PropertyExpenseController extends Controller
{
    // ═══════════════════════════════════════════════════════════════════
    // INDEX
    // ═══════════════════════════════════════════════════════════════════
    public function index(Company $company, Property $property)
    {
        $this->authorizeCompany($company);

        $expenses = PropertyExpense::where('company_id', $company->id)
            ->where('property_id', $property->id)
            ->with([
                'expenseCategory:id,category_name',
                'expenseItem:id,item_name',
                'payments',
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
        ]);

        $expense = PropertyExpense::create([
            'company_id'          => $company->id,
            'property_id'         => $property->id,
            'expense_category_id' => $data['expense_category_id'],
            'expense_item_id'     => $data['expense_item_id'],
            'expense_date'        => $data['expense_date'],
            'expense_amount'      => $data['expense_amount'],
            'currency'            => $data['currency'],
            'fx_rate'             => $data['fx_rate'] ?? null,
            'notes'               => $data['notes'] ?? null,
            'status'              => 'unpaid',
            'created_by'          => auth()->id(),
        ]);

        foreach ($data['payments'] ?? [] as $p) {
            PropertyExpensePayment::create([
                'company_id'          => $company->id,
                'property_expense_id' => $expense->id,
                'payment_date'        => $p['payment_date'],
                'amount'              => $p['amount'],
            ]);
        }

        $expense->recalculateStatus();

        return back()->with('success', 'Expense added successfully.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // UPDATE
    // ═══════════════════════════════════════════════════════════════════
    public function update(Request $request, Company $company, Property $property, PropertyExpense $expense)
    {
        $this->authorizeCompany($company);

        $data = $request->validate([
            'expense_category_id'       => 'required|exists:expense_categories,id',
            'expense_item_id'           => 'required|exists:expense_items,id',
            'expense_date'              => 'required|date',
            'expense_amount'            => 'required|numeric|min:0.01',
            'currency'                  => 'required|string|max:10',
            'fx_rate'                   => 'nullable|numeric|min:0',
            'notes'                     => 'nullable|string|max:1000',
        ]);

        $expense->update([
            'expense_category_id' => $data['expense_category_id'],
            'expense_item_id'     => $data['expense_item_id'],
            'expense_date'        => $data['expense_date'],
            'expense_amount'      => $data['expense_amount'],
            'currency'            => $data['currency'],
            'fx_rate'             => $data['fx_rate'] ?? null,
            'notes'               => $data['notes'] ?? null,
        ]);

        $expense->recalculateStatus();

        return back()->with('success', 'Expense updated successfully.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // DESTROY
    // ═══════════════════════════════════════════════════════════════════
    public function destroy(Company $company, Property $property, PropertyExpense $expense)
    {
        $this->authorizeCompany($company);
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

        $data = $request->validate([
            'payments'                => 'required|array|min:1',
            'payments.*.payment_date' => 'required|date',
            'payments.*.amount'       => 'required|numeric|min:0.01',
        ]);

        foreach ($data['payments'] as $p) {
            PropertyExpensePayment::create([
                'company_id'          => $company->id,
                'property_expense_id' => $expense->id,
                'payment_date'        => $p['payment_date'],
                'amount'              => $p['amount'],
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

        $requiredColumns = ['expense_category', 'expense_item', 'expense_date', 'expense_amount', 'currency'];
        foreach ($requiredColumns as $colName) {
            if (!isset($headerMap[$colName])) {
                return back()->withErrors(['file' => "Missing required column: {$colName}"]);
            }
        }

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
        $sheet->setCellValue('G2', now()->toDateString() . ':3000|' . now()->addMonth()->toDateString() . ':4000');
        $sheet->setCellValue('H2', 'Example with two initial payments');

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
    private function authorizeCompany(Company $company): void
    {
        $user = auth()->user();
        if (! $user->is_super_admin && $user->company_id !== $company->id) {
            abort(403);
        }
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