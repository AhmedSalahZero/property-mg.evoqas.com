<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CorporateExpense;
use App\Models\CorporateExpenseAllocation;
use App\Models\CorporateExpensePayment;
use App\Models\ExpenseCategory;
use App\Models\ExpenseItem;
use App\Services\CorporateExpenseAllocationService;
use App\Services\CurrencyConversionService;
use App\Services\ExpensePaymentScheduleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Http\Controllers\Concerns\AuthorizesCompany;

class CorporateExpenseController extends Controller
{
    use AuthorizesCompany;

    public function __construct(
        private CorporateExpenseAllocationService $allocator
    ) {}

    // ═══════════════════════════════════════════════════════════════════
    // INDEX
    //
    // Fix for the scaling concern raised in the July 2026 session: at
    // ~100 units, a single expense's allocation snapshot is ~100 rows. A
    // company adding 20 expenses/month was shipping the FULL allocation
    // breakdown for every expense on every single page load — thousands of
    // rows the user never looks at unless they expand that specific row.
    //
    // Two changes here:
    //   1. 'allocations' relation is no longer eager-loaded — only
    //      'allocations_count' (a cheap COUNT, not the rows themselves).
    //      The actual breakdown is fetched on demand by allocations() below,
    //      the moment the user expands a row (see Index.vue toggleExpand()).
    //   2. Results are scoped to one calendar month (default: current
    //      month, same UX pattern as Cash Forecast's period picker) and
    //      paginated on top of that, so neither the row count nor the
    //      payload size grows unbounded as the company accumulates years
    //      of corporate expense history.
    // ═══════════════════════════════════════════════════════════════════
    public function index(Request $request, Company $company)
    {
        $this->authorizeCompany($company);

        $month = $request->query('month');
        try {
            $monthStart = $month ? Carbon::createFromFormat('Y-m', $month)->startOfMonth() : now()->startOfMonth();
        } catch (\Throwable $e) {
            $monthStart = now()->startOfMonth();
        }
        $month = $monthStart->format('Y-m');
        $monthEnd = $monthStart->copy()->endOfMonth();

        $baseQuery = CorporateExpense::where('company_id', $company->id)
            ->whereBetween('expense_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);

        $paginated = (clone $baseQuery)
            ->with(['expenseCategory:id,category_name', 'expenseItem:id,item_name', 'payments', 'paymentSchedule'])
            ->withCount('allocations')
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $expenses = collect($paginated->items())->map(fn ($e) => $this->presentExpense($e));

        // Summary totals cover the WHOLE month, not just the current page —
        // computed as separate lightweight aggregates rather than reduced
        // client-side over a possibly-partial (paginated) expense list.
        $monthTotals = (clone $baseQuery)->selectRaw('COALESCE(SUM(expense_amount),0) as committed, COUNT(*) as cnt')->first();
        $monthPaid   = CorporateExpensePayment::where('company_id', $company->id)
            ->whereHas('corporateExpense', fn ($q) => $q->whereBetween('expense_date', [$monthStart->toDateString(), $monthEnd->toDateString()]))
            ->sum('amount');

        $expenseCategories = ExpenseCategory::where('company_id', $company->id)
            ->with(['items' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get(['id', 'category_name']);

        // Full unit picker list for the Custom Selection scope — no date
        // filter applied here, the picker shows the live portfolio; the
        // occupancy/delivery snapshot is only computed once a date+scope
        // combination is evaluated via the preview endpoint below.
        $unitPicker = $this->allocator
            ->allPortfolioSlots($company->id, Carbon::today())
            ->map(fn ($s) => [
                'key'    => $s['key'],
                'label'  => $s['label'],
                'area'   => $s['area'],
                'status' => $s['status'],
            ])
            ->values();

        return Inertia::render('Properties/CorporateExpenses/Index', [
            'company'           => $company,
            'expenses'          => $expenses,
            'pagination'        => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'total'        => $paginated->total(),
            ],
            'monthSummary'      => [
                'month'           => $month,
                'total_committed' => round((float) $monthTotals->committed, 2),
                'total_paid'      => round((float) $monthPaid, 2),
                'total_outstanding' => round((float) $monthTotals->committed - (float) $monthPaid, 2),
                'count'           => (int) $monthTotals->cnt,
            ],
            'expenseCategories' => $expenseCategories,
            'scopeOptions'      => CorporateExpense::scopeLabels(),
            'unitPicker'        => $unitPicker,
            'currencyOptions'   => $this->currencyOptions(),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // ALLOCATION BREAKDOWN — fetched on demand when a row is expanded,
    // never eager-loaded on index() (see the note above index()).
    // ═══════════════════════════════════════════════════════════════════
    public function allocations(Company $company, CorporateExpense $expense)
    {
        $this->authorizeCompany($company);
        $this->authorizeExpense($company, $expense);

        $rows = $expense->allocations()
            ->orderByDesc('allocated_amount')
            ->get()
            ->map(fn ($a) => [
                'id'                 => $a->id,
                'unit_label'         => $a->unit_label,
                'area'               => (float) $a->area,
                'eligibility_status' => $a->eligibility_status,
                'allocation_pct'     => (float) $a->allocation_pct,
                'allocated_amount'   => (float) $a->allocated_amount,
            ]);

        return response()->json(['rows' => $rows]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // ALLOCATION PREVIEW — run before saving, so the user can see who's
    // eligible and what each unit's share will be before committing.
    // ═══════════════════════════════════════════════════════════════════
    public function previewAllocation(Request $request, Company $company)
    {
        $this->authorizeCompany($company);

        $data = $request->validate([
            'expense_date'     => 'required|date',
            'expense_amount'   => 'required|numeric|min:0.01',
            'allocation_scope' => 'required|in:occupied,all_include_not_delivered,all_exclude_not_delivered,custom',
            'custom_unit_keys' => 'required_if:allocation_scope,custom|array',
        ]);

        $rows = $this->buildAllocationRows(
            $company->id,
            $data['allocation_scope'],
            Carbon::parse($data['expense_date']),
            (float) $data['expense_amount'],
            $data['custom_unit_keys'] ?? []
        );

        return response()->json([
            'rows'  => $rows->values(),
            'count' => $rows->count(),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // STORE
    // ═══════════════════════════════════════════════════════════════════
    public function store(Request $request, Company $company)
    {
        $this->authorizeCompany($company);

        $data = $request->validate([
            'expense_category_id'     => 'required|exists:expense_categories,id',
            'expense_item_id'         => 'required|exists:expense_items,id',
            'expense_date'            => 'required|date',
            'expense_amount'          => 'required|numeric|min:0.01',
            'currency'                => 'required|string|max:10',
            'fx_rate'                 => 'nullable|numeric|min:0',
            'allocation_scope'        => 'required|in:occupied,all_include_not_delivered,all_exclude_not_delivered,custom',
            'custom_unit_keys'        => 'required_if:allocation_scope,custom|array',
            'notes'                   => 'nullable|string|max:1000',
            'payments'                => 'nullable|array',
            'payments.*.payment_date' => 'required|date',
            'payments.*.amount'       => 'required|numeric|min:0.01',
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
                Carbon::parse($data['expense_date'])
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['payment_schedule' => $e->getMessage()])->withInput();
        }

        $allocationRows = $this->buildAllocationRows(
            $company->id,
            $data['allocation_scope'],
            Carbon::parse($data['expense_date']),
            (float) $data['expense_amount'],
            $data['custom_unit_keys'] ?? []
        );

        if ($allocationRows->isEmpty()) {
            return back()->withErrors(['allocation_scope' => 'No eligible units found for this scope and date — nothing to allocate against.']);
        }

        DB::transaction(function () use ($company, $data, $allocationRows, $scheduleService, $cleanSchedule) {
            $conversion = $this->convertToBase($company, (float) $data['expense_amount'], $data['currency'], $data['expense_date'], $data['fx_rate'] ?? null);

            $expense = CorporateExpense::create([
                'company_id'           => $company->id,
                'expense_category_id'  => $data['expense_category_id'],
                'expense_item_id'      => $data['expense_item_id'],
                'expense_date'         => $data['expense_date'],
                'expense_amount'       => $data['expense_amount'],
                'currency'             => $data['currency'],
                'base_amount'          => $conversion['base_amount'],
                'base_currency'        => $conversion['base_currency'],
                'fx_rate_used'         => $conversion['fx_rate_used'],
                'fx_rate'              => $data['fx_rate'] ?? null,
                'allocation_scope'     => $data['allocation_scope'],
                'notes'                => $data['notes'] ?? null,
                'status'               => 'unpaid',
                'created_by'           => auth()->id(),
            ]);

            $this->saveAllocationRows($expense, $allocationRows);

            foreach ($data['payments'] ?? [] as $p) {
                $this->createPayment($company, $expense, $p['payment_date'], $p['amount']);
            }

            $scheduleService->replaceSchedule($expense, $cleanSchedule);

            $expense->recalculateStatus();
        });

        return back()->with('success', 'Corporate expense added and allocated successfully.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // UPDATE
    // Re-runs the allocation engine against the (possibly new) date/amount/
    // scope — allocation is a snapshot recomputed at save time, it does not
    // carry independent payment history the way rent_collections does, so
    // there is nothing to "reconcile" here the way C1/C2 required.
    // ═══════════════════════════════════════════════════════════════════
    public function update(Request $request, Company $company, CorporateExpense $expense)
    {
        $this->authorizeCompany($company);
        $this->authorizeExpense($company, $expense);

        $data = $request->validate([
            'expense_category_id' => 'required|exists:expense_categories,id',
            'expense_item_id'     => 'required|exists:expense_items,id',
            'expense_date'        => 'required|date',
            'expense_amount'      => 'required|numeric|min:0.01',
            'currency'            => 'required|string|max:10',
            'fx_rate'             => 'nullable|numeric|min:0',
            'allocation_scope'    => 'required|in:occupied,all_include_not_delivered,all_exclude_not_delivered,custom',
            'custom_unit_keys'    => 'required_if:allocation_scope,custom|array',
            'notes'               => 'nullable|string|max:1000',
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
                Carbon::parse($data['expense_date'])
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['payment_schedule' => $e->getMessage()])->withInput();
        }

        $allocationRows = $this->buildAllocationRows(
            $company->id,
            $data['allocation_scope'],
            Carbon::parse($data['expense_date']),
            (float) $data['expense_amount'],
            $data['custom_unit_keys'] ?? []
        );

        if ($allocationRows->isEmpty()) {
            return back()->withErrors(['allocation_scope' => 'No eligible units found for this scope and date — nothing to allocate against.']);
        }

        DB::transaction(function () use ($company, $expense, $data, $allocationRows, $scheduleService, $cleanSchedule) {
            $conversion = $this->convertToBase($company, (float) $data['expense_amount'], $data['currency'], $data['expense_date'], $data['fx_rate'] ?? null);

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
                'allocation_scope'    => $data['allocation_scope'],
                'notes'               => $data['notes'] ?? null,
            ]);

            $expense->allocations()->delete();
            $this->saveAllocationRows($expense, $allocationRows);
            $scheduleService->replaceSchedule($expense, $cleanSchedule);
            $expense->recalculateStatus();
        });

        return back()->with('success', 'Corporate expense updated and re-allocated successfully.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // DESTROY
    // ═══════════════════════════════════════════════════════════════════
    public function destroy(Company $company, CorporateExpense $expense)
    {
        $this->authorizeCompany($company);
        $this->authorizeExpense($company, $expense);
        $expense->payments()->delete();
        $expense->allocations()->delete();
        // Fix — the forecasted payment schedule (a separate table from
        // actual payments — see CorporateExpense::paymentSchedule()) was
        // never deleted here, so the Cash Forecast kept counting future
        // expected outflows for expenses that had already been deleted.
        // This is very likely the exact "still in the db" symptom reported.
        $expense->paymentSchedule()->delete();
        $expense->delete();
        return back()->with('success', 'Corporate expense deleted.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // PAYMENTS
    // ═══════════════════════════════════════════════════════════════════
    public function addPayment(Request $request, Company $company, CorporateExpense $expense)
    {
        $this->authorizeCompany($company);
        $this->authorizeExpense($company, $expense);

        $data = $request->validate([
            'payments'                => 'required|array|min:1',
            'payments.*.payment_date' => 'required|date',
            'payments.*.amount'       => 'required|numeric|min:0.01',
        ]);

        foreach ($data['payments'] as $p) {
            $this->createPayment($company, $expense, $p['payment_date'], $p['amount']);
        }

        $expense->recalculateStatus();

        return back()->with('success', 'Payment recorded successfully.');
    }

    public function deletePayment(Company $company, CorporateExpense $expense, CorporateExpensePayment $payment)
    {
        $this->authorizeCompany($company);
        $this->authorizeExpense($company, $expense);
        abort_unless($payment->corporate_expense_id === $expense->id, 404);
        $payment->delete();
        $expense->recalculateStatus();
        return back()->with('success', 'Payment removed.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // EXCEL IMPORT — 2-step flow:
    //   1) importPreview()  — parse + validate rows, return JSON, nothing saved
    //   2) importSave()     — user has now picked ONE allocation scope for
    //                         the whole batch; create every expense + its
    //                         own allocation snapshot (each row keeps its
    //                         own expense_date, so eligibility is still
    //                         evaluated per-row, not for the batch as a whole)
    // ═══════════════════════════════════════════════════════════════════
    public function importPreview(Request $request, Company $company)
    {
        $this->authorizeCompany($company);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        $file   = $request->file('file');
        $reader = IOFactory::createReaderForFile($file->getRealPath());
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($file->getRealPath())->getActiveSheet();
        $rows  = $sheet->toArray(null, true, true, true);

        if (count($rows) < 2) {
            return response()->json(['valid' => [], 'invalid' => [['row' => 0, 'error' => 'File has no data rows.']]]);
        }

        $headerMap = [];
        foreach ($rows[1] as $col => $name) {
            $key = strtolower(trim((string) $name));
            if ($key !== '') $headerMap[$key] = $col;
        }

        $required = ['expense_category', 'expense_item', 'expense_date', 'expense_amount', 'currency', 'payment_term'];
        foreach ($required as $col) {
            if (!isset($headerMap[$col])) {
                return response()->json(['valid' => [], 'invalid' => [['row' => 1, 'error' => "Missing required column: {$col}"]]]);
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

        $valid = [];
        $invalid = [];

        for ($r = 2; $r <= count($rows); $r++) {
            $row = $rows[$r] ?? [];
            $empty = true;
            foreach ($row as $v) {
                if (trim((string) $v) !== '') { $empty = false; break; }
            }
            if ($empty) continue;

            $categoryName = strtolower(trim((string)($row[$headerMap['expense_category']] ?? '')));
            $itemName     = strtolower(trim((string)($row[$headerMap['expense_item']] ?? '')));
            $rawDate      = $row[$headerMap['expense_date']] ?? null;
            $amountRaw    = trim((string)($row[$headerMap['expense_amount']] ?? ''));
            $currency     = strtoupper(trim((string)($row[$headerMap['currency']] ?? '')));
            $notes        = isset($headerMap['notes']) ? trim((string)($row[$headerMap['notes']] ?? '')) : null;
            $paymentTerm  = strtolower(trim((string)($row[$headerMap['payment_term']] ?? '')));

            $error = null;
            $category = $catMap[$categoryName] ?? null;
            $item     = $category ? $itemMap[$itemName]?->firstWhere('expense_category_id', $category->id) : null;
            $date     = null;

            if ($categoryName === '' || $itemName === '' || $amountRaw === '' || $currency === '' || !$rawDate) {
                $error = 'All required fields must be filled.';
            } elseif (!$category) {
                $error = 'expense_category not found in company settings.';
            } elseif (!$item) {
                $error = 'expense_item is invalid for the selected category.';
            } elseif (!is_numeric($amountRaw) || (float) $amountRaw <= 0) {
                $error = 'expense_amount must be a number greater than 0.';
            } elseif (!in_array($currency, $allowedCurrencies, true)) {
                $error = 'currency must be one of ' . implode(', ', $allowedCurrencies);
            } elseif (!in_array($paymentTerm, $allowedTerms, true)) {
                $error = 'payment_term must be one of ' . implode(', ', $allowedTerms);
            } else {
                try {
                    $date = is_numeric($rawDate)
                        ? ExcelDate::excelToDateTimeObject((float) $rawDate)->format('Y-m-d')
                        : Carbon::parse($rawDate)->toDateString();
                } catch (\Throwable $e) {
                    $error = 'expense_date is invalid.';
                }
            }

            if ($error) {
                $invalid[] = ['row' => $r, 'error' => $error];
                continue;
            }

            $valid[] = [
                'row'                  => $r,
                'expense_category_id'  => $category->id,
                'expense_category'     => $category->category_name,
                'expense_item_id'      => $item->id,
                'expense_item'         => $item->item_name,
                'expense_date'         => $date,
                'expense_amount'       => (float) $amountRaw,
                'currency'             => $currency,
                'payment_term'         => $paymentTerm,
                'notes'                => $notes ?: null,
            ];
        }

        return response()->json(['valid' => $valid, 'invalid' => $invalid]);
    }

    public function importSave(Request $request, Company $company)
    {
        $this->authorizeCompany($company);

        $data = $request->validate([
            'rows'                     => 'required|array|min:1',
            'rows.*.expense_category_id' => 'required|exists:expense_categories,id',
            'rows.*.expense_item_id'     => 'required|exists:expense_items,id',
            'rows.*.expense_date'        => 'required|date',
            'rows.*.expense_amount'      => 'required|numeric|min:0.01',
            'rows.*.currency'            => 'required|string|max:10',
            'rows.*.payment_term'        => 'required|string|in:cash,net_30,net_45,net_60,net_75,net_90,net_120,net_150,net_180',
            'rows.*.notes'               => 'nullable|string|max:1000',
            'allocation_scope'         => 'required|in:occupied,all_include_not_delivered,all_exclude_not_delivered,custom',
            'custom_unit_keys'         => 'required_if:allocation_scope,custom|array',
        ]);

        $created = 0;
        $skipped = [];
        $scheduleService = app(ExpensePaymentScheduleService::class);

        DB::transaction(function () use ($company, $data, &$created, &$skipped, $scheduleService) {
            foreach ($data['rows'] as $i => $row) {
                // Each row keeps its OWN expense_date, so eligibility (occupied /
                // not-delivered) is evaluated per-row even though the scope
                // choice itself is shared across the whole batch.
                $allocationRows = $this->buildAllocationRows(
                    $company->id,
                    $data['allocation_scope'],
                    Carbon::parse($row['expense_date']),
                    (float) $row['expense_amount'],
                    $data['custom_unit_keys'] ?? []
                );

                if ($allocationRows->isEmpty()) {
                    $skipped[] = ['row' => $row['row'] ?? ($i + 1), 'error' => 'No eligible units for this scope on this date.'];
                    continue;
                }

                $conversion = $this->convertToBase($company, (float) $row['expense_amount'], $row['currency'], $row['expense_date'], null);

                $expense = CorporateExpense::create([
                    'company_id'          => $company->id,
                    'expense_category_id' => $row['expense_category_id'],
                    'expense_item_id'     => $row['expense_item_id'],
                    'expense_date'        => $row['expense_date'],
                    'expense_amount'      => $row['expense_amount'],
                    'currency'            => $row['currency'],
                    'base_amount'         => $conversion['base_amount'],
                    'base_currency'       => $conversion['base_currency'],
                    'fx_rate_used'        => $conversion['fx_rate_used'],
                    'allocation_scope'    => $data['allocation_scope'],
                    'notes'               => $row['notes'] ?? null,
                    'status'              => 'unpaid',
                    'created_by'          => auth()->id(),
                ]);

                $this->saveAllocationRows($expense, $allocationRows);

                $forecastedDate = $scheduleService->dateForTerm($row['payment_term'], Carbon::parse($row['expense_date']));
                $scheduleService->replaceSchedule($expense, [[
                    'percentage'      => 100.0,
                    'amount'          => (float) $row['expense_amount'],
                    'forecasted_date' => $forecastedDate->toDateString(),
                    'payment_term'    => $row['payment_term'],
                    'sort_order'      => 0,
                ]]);

                $created++;
            }
        });

        return response()->json([
            'message' => "{$created} corporate expense(s) imported and allocated.",
            'created' => $created,
            'skipped' => $skipped,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // TEMPLATE
    // ═══════════════════════════════════════════════════════════════════
    public function downloadTemplate(Company $company)
    {
        $this->authorizeCompany($company);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Corporate Expenses Template');

        $headers = ['expense_category', 'expense_item', 'expense_date', 'expense_amount', 'currency', 'payment_term', 'notes'];
        foreach ($headers as $i => $header) {
            $sheet->setCellValueByColumnAndRow($i + 1, 1, $header);
        }

        $sheet->setCellValue('A2', 'General & Admin');
        $sheet->setCellValue('B2', 'Security Services');
        $sheet->setCellValue('C2', now()->toDateString());
        $sheet->setCellValue('D2', '15000');
        $sheet->setCellValue('E2', 'EGP');
        $sheet->setCellValue('F2', 'net_30');
        $sheet->setCellValue('G2', 'Monthly security guard contract');

        // payment_term dropdown — same built-in terms as Direct Expenses'
        // template and the on-screen repeater (see
        // ExpensePaymentScheduleService). Every imported row gets a single
        // 100% schedule row dated expense_date + this term's days (see
        // importSave() above).
        $termOptions = ['cash', 'net_30', 'net_45', 'net_60', 'net_75', 'net_90', 'net_120', 'net_150', 'net_180'];
        $validation = $sheet->getCell('F2')->getDataValidation();
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
            $sheet->getCell("F{$row}")->setDataValidation(clone $validation);
        }

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'corporate_expenses_template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Run the allocation engine end-to-end for a given scope/date/amount and
     * return rows shaped for saving (each row still carries property_id /
     * unit_id / label / area / status / allocation_pct / allocated_amount).
     */
    private function buildAllocationRows(int $companyId, string $scope, Carbon $expenseDate, float $amount, array $customKeys)
    {
        $eligible = $this->allocator->eligibleUnits($companyId, $scope, $expenseDate, $customKeys);
        return $this->allocator->allocate($amount, $eligible);
    }

    private function saveAllocationRows(CorporateExpense $expense, $allocationRows): void
    {
        // Per-unit base_amount is derived from the expense's own base_amount
        // (already FX-converted by convertToBase() above) times this row's
        // allocation %, rather than re-converting each row independently.
        $toInsert = [];
        foreach ($allocationRows as $row) {
            $baseAllocated = $expense->base_amount !== null
                ? round((float) $expense->base_amount * ((float) $row['allocation_pct'] / 100), 2)
                : null;

            $toInsert[] = [
                'company_id'             => $expense->company_id,
                'corporate_expense_id'   => $expense->id,
                'property_id'            => $row['property_id'],
                'property_unit_id'       => $row['unit_id'],
                'unit_label'             => $row['label'],
                'area'                   => $row['area'],
                'eligibility_status'     => $row['status'],
                'allocation_pct'         => $row['allocation_pct'],
                'allocated_amount'       => $row['allocated_amount'],
                'allocated_base_amount'  => $baseAllocated,
                'created_at'             => now(),
                'updated_at'             => now(),
            ];
        }

        if (!empty($toInsert)) {
            CorporateExpenseAllocation::insert($toInsert);
        }
    }

    private function createPayment(Company $company, CorporateExpense $expense, string $date, float $amount): void
    {
        $conversion = $this->convertToBase($company, $amount, $expense->currency, $date, $expense->fx_rate ? (float) $expense->fx_rate : null);

        CorporateExpensePayment::create([
            'company_id'            => $company->id,
            'corporate_expense_id'  => $expense->id,
            'payment_date'          => $date,
            'amount'                => $amount,
            'base_amount'           => $conversion['base_amount'],
            'base_currency'         => $conversion['base_currency'],
            'fx_rate_used'          => $conversion['fx_rate_used'],
        ]);
    }

    private function presentExpense(CorporateExpense $e): array
    {
        return [
            'id'                => $e->id,
            'expense_category'  => $e->expenseCategory?->category_name,
            'expense_item'      => $e->expenseItem?->item_name,
            'expense_date'      => $e->expense_date->format('Y-m-d'),
            'expense_amount'    => (float) $e->expense_amount,
            'currency'          => $e->currency,
            'allocation_scope'  => $e->allocation_scope,
            'notes'             => $e->notes,
            'status'            => $e->status,
            'total_paid'        => $e->totalPaid(),
            'balance'           => $e->balance(),
            'payments'          => $e->payments->map(fn ($p) => [
                'id'           => $p->id,
                'payment_date' => $p->payment_date->format('Y-m-d'),
                'amount'       => (float) $p->amount,
            ])->values()->all(),
            'payment_schedule'  => $e->paymentSchedule->map(fn ($s) => [
                'id'              => $s->id,
                'percentage'      => (float) $s->percentage,
                'amount'          => (float) $s->amount,
                'forecasted_date' => $s->forecasted_date->format('Y-m-d'),
                'payment_term'    => $s->payment_term,
            ])->values()->all(),
            // Count only — the actual allocation rows are fetched on demand
            // by GET .../corporate-expenses/{expense}/allocations when the
            // user expands this row (see allocations() above). Keeps the
            // index payload flat regardless of portfolio size.
            'allocations_count' => $e->allocations_count ?? $e->allocations()->count(),
        ];
    }

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
     * Fix for audit finding C-2 — authorizeCompany() alone doesn't confirm
     * {expense} (resolved by Laravel with no company filter) belongs to the
     * URL's {company}. See the same fix in PropertyController.
     */
    private function authorizeExpense(Company $company, CorporateExpense $expense): void
    {
        abort_unless($expense->company_id === $company->id, 404);
    }

    private function currencyOptions(): array
    {
        return ['EGP', 'USD', 'EUR', 'GBP', 'SAR', 'AED'];
    }
}
