<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserTaskController;
use App\Http\Controllers\StatisticaController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\CompanySettingsController;
use App\Http\Controllers\LoanEngineController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\PropertySaleController;
use App\Http\Controllers\RentContractController;
use App\Http\Controllers\PropertyInstallmentController;
use App\Http\Controllers\PropertyExpenseController;
use App\Http\Controllers\CorporateExpenseController;
use App\Http\Controllers\PropertyReportController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\ProvinceController;
use App\Http\Controllers\PropertyOwnerController;
use App\Http\Controllers\PropertyDashboardController;
use App\Http\Controllers\PropertyRentCollectionController;
use App\Http\Controllers\PropertyInstallmentPaymentController;
use App\Http\Controllers\CashForecastController;
use App\Http\Controllers\KeepOrSellController;
use App\Http\Controllers\InvestmentDecisionController;
use App\Http\Controllers\CompanySubscriptionStatusController;
use App\Http\Controllers\CurrencyRateController;
use App\Http\Controllers\CompanyReportController;
use App\Http\Controllers\Reports\TenantLedgerController;
use App\Http\Controllers\Reports\RentCollectionsController;
use App\Http\Controllers\Reports\InstallmentsController;
use App\Http\Controllers\Reports\AnnualSummaryController;
use App\Http\Controllers\Reports\RentBenchmarkController;
use App\Http\Controllers\Reports\ExpenseReportController;
use App\Http\Controllers\Reports\CustomReportController;

// ══════════════════════════════════════════════════════
// PUBLIC — Welcome / Login redirect
// ══════════════════════════════════════════════════════
Route::get('/keep-or-sell/share/{token}', [KeepOrSellController::class, 'share'])->name('keep-or-sell.share');
Route::get('/investment-decision/share/{token}', [InvestmentDecisionController::class, 'shareAnalysis'])->name('investment-decision.share');
Route::get('/', function () {
    if (auth()->check()) {
        $user = auth()->user();
        if ($user->is_super_admin) {
            return redirect()->route('companies.index');
        }
        return redirect()->route('company.properties.dashboard', $user->company_id);
    }
    return redirect()->route('login');
});

// ══════════════════════════════════════════════════════
// AUTHENTICATED ROUTES
// ══════════════════════════════════════════════════════
Route::middleware(['auth', 'verified', 'subscription.active'])->group(function () {

    // ── Profile ────────────────────────────────────────
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/',    [ProfileController::class, 'edit'])->name('edit');
        Route::patch('/',  [ProfileController::class, 'update'])->name('update');
        Route::delete('/', [ProfileController::class, 'destroy'])->name('destroy');
    });

    // ── Theme Toggle ───────────────────────────────────
    Route::post('/theme', function () {
        $theme = request('theme', 'dark');
        auth()->user()->update(['theme' => $theme]);
        return response()->json(['theme' => $theme]);
    })->name('theme.toggle');

    Route::get('/subscription/status', CompanySubscriptionStatusController::class)
        ->name('subscription.status');

    // ══════════════════════════════════════════════════════
    // SUPER ADMIN — Company Management
    // ══════════════════════════════════════════════════════
    Route::prefix('companies')->name('companies.')->group(function () {
        Route::get('/',                              [CompanyController::class, 'index'])->name('index');
        Route::get('/create',                        [CompanyController::class, 'create'])->name('create');
        Route::post('/',                             [CompanyController::class, 'store'])->name('store');
        Route::get('/{company}',                     [CompanyController::class, 'show'])->name('show');
        Route::get('/{company}/edit',                [CompanyController::class, 'edit'])->name('edit');
        Route::put('/{company}',                     [CompanyController::class, 'update'])->name('update');
        Route::delete('/{company}',                  [CompanyController::class, 'destroy'])->name('destroy');
        Route::patch('/{company}/toggle-active',     [CompanyController::class, 'toggleActive'])->name('toggle-active');
        Route::patch('/{company}/modules',           [CompanyController::class, 'updateModules'])->name('update-modules');
    });

    // ══════════════════════════════════════════════════════
    // SUPER ADMIN — User Management
    // ══════════════════════════════════════════════════════
    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/',                              [UserController::class, 'index'])->name('index');
        Route::get('/create',                        [UserController::class, 'create'])->name('create');
        Route::post('/',                             [UserController::class, 'store'])->name('store');
        Route::get('/{user}/edit',                   [UserController::class, 'edit'])->name('edit');
        Route::put('/{user}',                        [UserController::class, 'update'])->name('update');
        Route::delete('/{user}',                     [UserController::class, 'destroy'])->name('destroy');
        Route::patch('/{user}/toggle-active',        [UserController::class, 'toggleActive'])->name('toggle-active');
    });

    // ══════════════════════════════════════════════════════
    // ALERTS
    // ══════════════════════════════════════════════════════
    Route::prefix('alerts')->name('alerts.')->group(function () {
        Route::get('/',                              [AlertController::class, 'index'])->name('index');
        Route::patch('/{alert}/read',                [AlertController::class, 'markRead'])->name('mark-read');
        Route::post('/mark-all-read',                [AlertController::class, 'markAllRead'])->name('mark-all-read');
        Route::delete('/{alert}',                    [AlertController::class, 'destroy'])->name('destroy');
        Route::get('/unread-count',                  [AlertController::class, 'unreadCount'])->name('unread-count');
    });

    // ══════════════════════════════════════════════════════
    // USER TASKS (personal — not company scoped)
    // ══════════════════════════════════════════════════════
    Route::prefix('tasks')->name('tasks.')->group(function () {
        Route::get('/badge-count',                   [UserTaskController::class, 'badgeCount'])->name('badge');
        Route::get('/',                              [UserTaskController::class, 'index'])->name('index');
        Route::post('/',                             [UserTaskController::class, 'store'])->name('store');
        Route::put('/{task}',                        [UserTaskController::class, 'update'])->name('update');
        Route::patch('/{task}/status',               [UserTaskController::class, 'updateStatus'])->name('status');
        Route::delete('/{task}',                     [UserTaskController::class, 'destroy'])->name('destroy');
    });

    // ── Loan Calculator (not company-scoped) ───────────
    Route::prefix('loan-engine')->name('loan-engine.')->group(function () {
        Route::get('/',                              [LoanEngineController::class, 'index'])->name('index');
        Route::post('/calculate',                    [LoanEngineController::class, 'calculate'])->name('calculate');
        Route::post('/export',                       [LoanEngineController::class, 'export'])->name('export');
    });

    // ══════════════════════════════════════════════════════
    // COMPANY-SCOPED ROUTES
    // URL:  /companies/{company}/...
    // Name: company.*
    // ══════════════════════════════════════════════════════
    Route::prefix('companies/{company}')->name('company.')->group(function () {

        // ── Tags (company-scoped, reusable) ─────────────────────────────
        Route::get('tags/search', [TagController::class, 'search'])->name('tags.search');
        Route::post('tags', [TagController::class, 'store'])->name('tags.store');
        Route::put('tags/{tag}', [TagController::class, 'update'])->name('tags.update');
        Route::delete('tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');

        // ── Provinces / Districts (company-scoped managed list) ─────────
        Route::get('provinces', [ProvinceController::class, 'index'])->name('provinces.index');
        Route::post('provinces', [ProvinceController::class, 'store'])->name('provinces.store');
        Route::put('provinces/{province}', [ProvinceController::class, 'update'])->name('provinces.update');
        Route::delete('provinces/{province}', [ProvinceController::class, 'destroy'])->name('provinces.destroy');

        // ── Property Owners (company-scoped managed list) ───────────────
        Route::get('property-owners', [PropertyOwnerController::class, 'index'])->name('property-owners.index');
        Route::post('property-owners', [PropertyOwnerController::class, 'store'])->name('property-owners.store');
        Route::put('property-owners/{propertyOwner}', [PropertyOwnerController::class, 'update'])->name('property-owners.update');
        Route::delete('property-owners/{propertyOwner}', [PropertyOwnerController::class, 'destroy'])->name('property-owners.destroy');

        // ── Properties ─────────────────────────────────────────────────
        // URL:  /companies/{company}/properties/...
        // Name: company.properties.*
        Route::prefix('properties')->name('properties.')->group(function () {
            Route::get('/',                   [PropertyController::class, 'index'])->name('index');
            Route::get('/create',             [PropertyController::class, 'create'])->name('create');
            Route::post('/',                  [PropertyController::class, 'store'])->name('store');
            Route::get('/dashboard',          [PropertyDashboardController::class, 'index'])->name('dashboard');
            Route::get('/dashboard/data',     [PropertyDashboardController::class, 'data'])->name('dashboard.data');

            // ── Properties Rent Collection (portfolio-wide) ─────────────────────
            // URL:  /companies/{company}/properties/rent-collections/...
            // Name: company.properties.rent-collections.*
            // Same static-before-wildcard rule as Corporate Expenses above —
            // 'rent-collections' is a single path segment and MUST stay above
            // '/{property}' or Laravel will try to bind it as a property id.
            Route::prefix('rent-collections')->name('rent-collections.')->group(function () {
                Route::get('/',       [PropertyRentCollectionController::class, 'index'])->name('index');
                Route::get('/data',   [PropertyRentCollectionController::class, 'data'])->name('data');
                Route::get('/export', [PropertyRentCollectionController::class, 'export'])->name('export');
            });

            // ── Properties Installment Payment (portfolio-wide) ─────────────────
            // URL:  /companies/{company}/properties/installment-payments/...
            // Name: company.properties.installment-payments.*
            Route::prefix('installment-payments')->name('installment-payments.')->group(function () {
                Route::get('/',       [PropertyInstallmentPaymentController::class, 'index'])->name('index');
                Route::get('/data',   [PropertyInstallmentPaymentController::class, 'data'])->name('data');
                Route::get('/export', [PropertyInstallmentPaymentController::class, 'export'])->name('export');
            });

            Route::get('/cash-forecast',      [CashForecastController::class, 'index'])->name('cash-forecast');
            Route::get('/cash-forecast/data', [CashForecastController::class, 'data'])->name('cash-forecast.data');
            // Fix for audit finding H-4 — persist the manually-entered
            // Salaries/New Hirings/Other Collections/Other Payments rows
            // that used to live only in the page's in-memory state.
            Route::get('/cash-forecast/manual-rows',  [CashForecastController::class, 'manualRows'])->name('cash-forecast.manual-rows');
            Route::post('/cash-forecast/manual-rows', [CashForecastController::class, 'saveManualRows'])->name('cash-forecast.manual-rows.save');

            // ── Keep or Sell ─────────────────────────────────────────────────
            // URL:  /companies/{company}/properties/keep-or-sell/...
            // Name: company.properties.keep-or-sell.*
            Route::prefix('keep-or-sell')->name('keep-or-sell.')->group(function () {
                Route::get('/',                                  [KeepOrSellController::class, 'index'])              ->name('index');
                Route::get('/unit-data',                         [KeepOrSellController::class, 'unitData'])           ->name('unit-data');
                Route::post('/compute',                          [KeepOrSellController::class, 'compute'])            ->name('compute');
                Route::post('/',                                 [KeepOrSellController::class, 'store'])              ->name('store');
                Route::get('/{analysis}',                        [KeepOrSellController::class, 'show'])               ->name('show');
                Route::patch('/{analysis}/recommendation',       [KeepOrSellController::class, 'updateRecommendation'])->name('update-recommendation');
                Route::delete('/{analysis}',                     [KeepOrSellController::class, 'destroy'])            ->name('destroy');
                Route::post('/{analysis}/generate-token',        [KeepOrSellController::class, 'generateToken'])      ->name('generate-token');
            });

            // ── Corporate Expenses ─────────────────────────────────────────────
            // Company-level (not per-property) expenses, spread across the
            // portfolio by the area-weighted allocation engine — see
            // App\Services\CorporateExpenseAllocationService.
            // URL:  /companies/{company}/properties/corporate-expenses/...
            // Name: company.properties.corporate-expenses.*
            //
            // IMPORTANT — this entire group must stay ABOVE the wildcard
            // '/{property}' routes below (tags/edit/update/show/destroy).
            // 'corporate-expenses' is a single path segment, exactly the
            // same shape as '/{property}', so if this group were placed
            // AFTER Route::get('/{property}', ...PropertyController::show),
            // Laravel would match GET /properties/corporate-expenses against
            // THAT route first — treating "corporate-expenses" as a property
            // ID, failing route-model binding, and returning a plain 404
            // "Not Found" (not a 403 — this happens for EVERY user, not just
            // super-admin; super-admin only surfaced it first because that's
            // who happened to click it first). This is the exact same
            // static-route-after-wildcard bug already hit once before by
            // Currency Rates and Property Installments — see the notes on
            // those two groups. Keep any future company-wide (non-{property})
            // static route up here with Dashboard/Cash Forecast/Keep-or-Sell,
            // never below this point.
            Route::prefix('corporate-expenses')->name('corporate-expenses.')->group(function () {
                Route::get('/',                                [CorporateExpenseController::class, 'index'])            ->name('index');
                Route::get('/template',                         [CorporateExpenseController::class, 'downloadTemplate']) ->name('template');
                Route::post('/preview-allocation',              [CorporateExpenseController::class, 'previewAllocation'])->name('preview-allocation');
                Route::post('/import-preview',                  [CorporateExpenseController::class, 'importPreview'])   ->name('import-preview');
                Route::post('/import-save',                     [CorporateExpenseController::class, 'importSave'])      ->name('import-save');
                Route::post('/',                                [CorporateExpenseController::class, 'store'])            ->name('store');
                Route::put('/{expense}',                        [CorporateExpenseController::class, 'update'])           ->name('update');
                Route::delete('/{expense}',                     [CorporateExpenseController::class, 'destroy'])          ->name('destroy');
                Route::get('/{expense}/allocations',            [CorporateExpenseController::class, 'allocations'])     ->name('allocations');
                Route::post('/{expense}/payments',              [CorporateExpenseController::class, 'addPayment'])      ->name('payments.store');
                Route::delete('/{expense}/payments/{payment}',  [CorporateExpenseController::class, 'deletePayment'])   ->name('payments.destroy');
            });

            // ── Investment Decision Tool ("Buy or Not Buy") ─────────────────────
            // URL:  /companies/{company}/properties/investment-decision/...
            // Name: company.properties.investment-decision.*
            //
            // IMPORTANT — same rule as Corporate Expenses above: this entire
            // group must stay ABOVE the wildcard '/{property}' routes below.
            // 'investment-decision' is a single path segment, the same shape
            // as '/{property}' — placed after the wildcard, Laravel would
            // try to bind "investment-decision" as a property ID and 404.
            Route::prefix('investment-decision')->name('investment-decision.')->group(function () {
                Route::get('/',                       [InvestmentDecisionController::class, 'index'])   ->name('index');
                Route::get('/create',                 [InvestmentDecisionController::class, 'create'])  ->name('create');
                Route::post('/',                      [InvestmentDecisionController::class, 'store'])   ->name('store');
                Route::get('/{prospect}/edit',         [InvestmentDecisionController::class, 'edit'])    ->name('edit');
                Route::put('/{prospect}',              [InvestmentDecisionController::class, 'update'])  ->name('update');
                Route::delete('/{prospect}',           [InvestmentDecisionController::class, 'destroy']) ->name('destroy');
                Route::get('/{prospect}/workspace',    [InvestmentDecisionController::class, 'workspace'])->name('workspace');
                Route::patch('/{prospect}/status',      [InvestmentDecisionController::class, 'updateStatus'])->name('update-status');
                Route::post('/{prospect}/compute',     [InvestmentDecisionController::class, 'compute']) ->name('compute');

                // ── Phase 4 — saved/shareable snapshots ─────────────────
                Route::get('/{prospect}/analyses',                              [InvestmentDecisionController::class, 'analysesIndex'])              ->name('analyses.index');
                Route::post('/{prospect}/analyses',                             [InvestmentDecisionController::class, 'storeAnalysis'])               ->name('analyses.store');
                Route::get('/{prospect}/analyses/{analysis}',                   [InvestmentDecisionController::class, 'showAnalysis'])                ->name('analyses.show');
                Route::patch('/{prospect}/analyses/{analysis}/recommendation',  [InvestmentDecisionController::class, 'updateAnalysisRecommendation'])->name('analyses.update-recommendation');
                Route::delete('/{prospect}/analyses/{analysis}',                [InvestmentDecisionController::class, 'destroyAnalysis'])             ->name('analyses.destroy');
                Route::post('/{prospect}/analyses/{analysis}/generate-token',   [InvestmentDecisionController::class, 'generateAnalysisToken'])       ->name('analyses.generate-token');
            });

            Route::get('/{property}/tags',    [TagController::class, 'forProperty'])->name('tags.index');
            Route::put('/{property}/tags',    [TagController::class, 'sync'])->name('tags.sync');
            Route::get('/{property}/edit',    [PropertyController::class, 'edit'])->name('edit');
            Route::put('/{property}',         [PropertyController::class, 'update'])->name('update');
            Route::get('/{property}',         [PropertyController::class, 'show'])->name('show');
            Route::delete('/{property}',      [PropertyController::class, 'destroy'])->name('destroy');

            // ── Record Sale (Phase 1, confirmed July 2026) ──────────────────
            // URL:  /companies/{company}/properties/{property}/sell...
            // Name: company.properties.sell / .units.sell / .sell-whole
            // The GET "form" routes render the dedicated Sell page (moved out
            // of the Properties Index modal); the POST routes underneath are
            // unchanged and still do the actual save.
            Route::get('/{property}/sell',               [PropertySaleController::class, 'sellUnitForm'])->name('sell.form');
            Route::post('/{property}/sell',              [PropertySaleController::class, 'sellUnit'])->name('sell');
            Route::get('/{property}/units/{unit}/sell',  [PropertySaleController::class, 'sellChildUnitForm'])->name('units.sell.form');
            Route::post('/{property}/units/{unit}/sell', [PropertySaleController::class, 'sellChildUnit'])->name('units.sell');
            Route::get('/{property}/sell-whole',          [PropertySaleController::class, 'sellWholeForm'])->name('sell-whole.form');
            Route::post('/{property}/sell-whole',        [PropertySaleController::class, 'sellWhole'])->name('sell-whole');
            Route::post('/sales/{sale}/dues/{due}/collect', [PropertySaleController::class, 'markDueCollected'])->name('sales.dues.collect');

            // ── Rent Contracts ───────────────────────────────────────────────
            // URL:  /companies/{company}/properties/{property}/contracts/...
            // Name: company.properties.contracts.*
            Route::prefix('/{property}/contracts')->name('contracts.')->group(function () {
                Route::get('/',                         [RentContractController::class, 'index'])->name('index');
                Route::get('/create',                   [RentContractController::class, 'create'])->name('create');
                Route::post('/',                        [RentContractController::class, 'store'])->name('store');
                Route::get('/{contract}/edit',          [RentContractController::class, 'edit'])->name('edit');
                Route::put('/{contract}',               [RentContractController::class, 'update'])->name('update');
                Route::get('/{contract}',               [RentContractController::class, 'show'])->name('show');
                Route::get('/{contract}/renew',         [RentContractController::class, 'renew'])->name('renew');
                Route::post('/{contract}/terminate',    [RentContractController::class, 'terminate'])->name('terminate');
                Route::patch('/{contract}/collections/{collection}/collected', [RentContractController::class, 'markCollected'])->name('collections.collected');
                Route::patch('/{contract}/collections/{collection}/uncollect', [RentContractController::class, 'markUncollected'])->name('collections.uncollect');
                Route::delete('/{contract}/collections/{collection}', [RentContractController::class, 'deleteCollection'])->name('collections.destroy');
                Route::delete('/{contract}',            [RentContractController::class, 'destroy'])->name('destroy');
            });

            // ── Due Installments ─────────────────────────────────────────────
            // URL:  /companies/{company}/properties/{property}/installments/...
            // Name: company.properties.installments.*
            Route::prefix('/{property}/installments')->name('installments.')->group(function () {
                Route::get('/',                              [PropertyInstallmentController::class, 'index'])    ->name('index');
                Route::get('/data',                          [PropertyInstallmentController::class, 'load'])     ->name('load');
                Route::post('/',                             [PropertyInstallmentController::class, 'save'])     ->name('save');
                Route::patch('/{due}/mark-paid',             [PropertyInstallmentController::class, 'markPaid'])->name('mark-paid');
                Route::patch('/{due}/mark-unpaid',           [PropertyInstallmentController::class, 'markUnpaid'])->name('mark-unpaid');
                Route::delete('/{due}',                      [PropertyInstallmentController::class, 'deleteDue'])->name('delete-due');
                Route::post('/import',                       [PropertyInstallmentController::class, 'import'])  ->name('import');
            });

            // ── Property Expenses ────────────────────────────────────────────
            // URL:  /companies/{company}/properties/{property}/expenses/...
            // Name: company.properties.expenses.*
            Route::prefix('/{property}/expenses')->name('expenses.')->group(function () {
                Route::get('/',                                          [PropertyExpenseController::class, 'index'])        ->name('index');
                Route::get('/template',                                  [PropertyExpenseController::class, 'downloadTemplate'])->name('template');
                Route::post('/',                                         [PropertyExpenseController::class, 'store'])        ->name('store');
                Route::post('/import',                                   [PropertyExpenseController::class, 'import'])       ->name('import');
                Route::put('/{expense}',                                 [PropertyExpenseController::class, 'update'])       ->name('update');
                Route::delete('/{expense}',                              [PropertyExpenseController::class, 'destroy'])      ->name('destroy');
                Route::post('/{expense}/payments',                       [PropertyExpenseController::class, 'addPayment'])   ->name('payments.store');
                Route::delete('/{expense}/payments/{payment}',           [PropertyExpenseController::class, 'deletePayment'])->name('payments.destroy');
            });

            // ── Property Reports ─────────────────────────────────────────────
            // URL:  /companies/{company}/properties/{property}/reports/...
            // Name: company.properties.reports.*
            Route::prefix('/{property}/reports')->name('reports.')->group(function () {
                Route::get('/',                    [PropertyReportController::class, 'index'])->name('index');
                Route::get('/rent-expenses',      [PropertyReportController::class, 'rentExpenses'])->name('rent-expenses');
                Route::get('/rent-expenses/data', [PropertyReportController::class, 'rentExpensesData'])->name('rent-expenses.data');
                Route::get('/rent-expenses/detail', [PropertyReportController::class, 'rentExpensesDetail'])->name('rent-expenses.detail');
            });

        }); // end properties

        // ── Company Reports ────────────────────────────────────────────
        // URL:  /companies/{company}/reports/...
        // Name: company.reports.*
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/',                               [CompanyReportController::class, 'index'])           ->name('index');

            Route::get('/tenant-ledger',                  [TenantLedgerController::class, 'index'])            ->name('tenant-ledger');
            Route::get('/tenant-ledger/data',             [TenantLedgerController::class, 'data'])             ->name('tenant-ledger.data');

            Route::get('/rent-collections',               [RentCollectionsController::class, 'index'])         ->name('rent-collections');
            Route::get('/rent-collections/data',          [RentCollectionsController::class, 'data'])          ->name('rent-collections.data');

            Route::get('/installments',                   [InstallmentsController::class, 'index'])            ->name('installments');
            Route::get('/installments/data',              [InstallmentsController::class, 'data'])             ->name('installments.data');

            Route::get('/annual-summary',                 [AnnualSummaryController::class, 'index'])           ->name('annual-summary');
            Route::get('/annual-summary/data',            [AnnualSummaryController::class, 'data'])            ->name('annual-summary.data');

            Route::get('/rent-benchmark',                 [RentBenchmarkController::class, 'index'])           ->name('rent-benchmark');
            Route::get('/rent-benchmark/data',            [RentBenchmarkController::class, 'data'])            ->name('rent-benchmark.data');

            Route::get('/expense-report',                 [ExpenseReportController::class, 'index'])           ->name('expense-report');
            Route::get('/expense-report/data',            [ExpenseReportController::class, 'data'])            ->name('expense-report.data');

            // ── Exchange Rates — moved here from Company Settings (July
            // 2026), confirmed request. Same audit-C4 fix history as
            // before, just relocated. NOTE: static routes (template/
            // export/import) are intentionally placed above the
            // parameterized '/{rate}' route below — this project has
            // already been bitten once by Laravel matching a static path
            // against a wildcard route defined earlier (see the Corporate
            // Expenses session log entry), so template/export/import must
            // stay ahead of destroy('/currency-rates/{rate}').
            // URL:  /companies/{company}/reports/currency-rates/...
            // Name: company.reports.currency-rates.*
            Route::prefix('currency-rates')->name('currency-rates.')->group(function () {
                Route::get('/',                    [CurrencyRateController::class, 'index'])           ->name('index');
                Route::get('/template',            [CurrencyRateController::class, 'downloadTemplate'])->name('template');
                Route::get('/export',              [CurrencyRateController::class, 'export'])          ->name('export');
                Route::post('/import',             [CurrencyRateController::class, 'import'])          ->name('import');
                Route::post('/from-statistica',    [CurrencyRateController::class, 'importFromStatistica'])->name('from-statistica');
                Route::post('/',                   [CurrencyRateController::class, 'store'])           ->name('store');
                Route::delete('/{rate}',           [CurrencyRateController::class, 'destroy'])         ->name('destroy');
            });

            // ── Custom Report Builder ──────────────────────────────────
            // URL:  /companies/{company}/reports/custom/...
            // Name: company.reports.custom.*
            Route::prefix('custom')->name('custom.')->group(function () {
                Route::get('/builder',              [CustomReportController::class, 'builder'])  ->name('builder');
                Route::get('/{report}/edit',        [CustomReportController::class, 'builder'])  ->name('edit');
                Route::post('/',                    [CustomReportController::class, 'store'])    ->name('store');
                Route::put('/{report}',             [CustomReportController::class, 'update'])   ->name('update');
                Route::delete('/{report}',          [CustomReportController::class, 'destroy'])  ->name('destroy');
                Route::post('/run',                 [CustomReportController::class, 'run'])      ->name('run');
                Route::post('/export',              [CustomReportController::class, 'export'])   ->name('export');
            });
        });

        // ── Projects & Tasks ───────────────────────────
        // Module: projects_tasks
        Route::prefix('projects')->name('projects.')->group(function () {
            Route::get('/',                              [ProjectController::class, 'index'])->name('index');
            Route::post('/',                             [ProjectController::class, 'store'])->name('store');
            Route::post('/cost-rates',                   [ProjectController::class, 'saveCostRates'])->name('cost-rates');
            Route::get('/{project}',                     [ProjectController::class, 'show'])->name('show');
            Route::put('/{project}',                     [ProjectController::class, 'update'])->name('update');
            Route::delete('/{project}',                  [ProjectController::class, 'destroy'])->name('destroy');
            Route::get('/{project}/refresh',             [ProjectController::class, 'refresh'])->name('refresh');
            Route::post('/{project}/tasks',              [ProjectController::class, 'storeTask'])->name('store-task');
            Route::put('/{project}/tasks/{task}',        [ProjectController::class, 'updateTask'])->name('update-task');
            Route::delete('/{project}/tasks/{task}',     [ProjectController::class, 'destroyTask'])->name('destroy-task');
            Route::post('/{project}/tasks/{task}/logs',  [ProjectController::class, 'storeLog'])->name('store-log');
            Route::delete('/{project}/tasks/{task}/logs/{log}', [ProjectController::class, 'destroyLog'])->name('destroy-log');
            Route::post('/{project}/tasks/reorder',      [ProjectController::class, 'reorderTasks'])->name('reorder-tasks');
            Route::post('/{project}/expenses',           [ProjectController::class, 'storeExpense'])->name('store-expense');
            Route::delete('/{project}/expenses/{expense}',[ProjectController::class, 'destroyExpense'])->name('destroy-expense');
        });

        // ── Statistica ─────────────────────────────────
        // Module: statistica
        Route::prefix('statistica')->name('statistica.')->group(function () {
            Route::get('/',                              [StatisticaController::class, 'index'])->name('index');
            Route::post('/',                             [StatisticaController::class, 'store'])->name('store');
            Route::get('/compare',                       [StatisticaController::class, 'compare'])->name('compare');
            Route::get('/template',                      [StatisticaController::class, 'downloadTemplate'])->name('template');
            Route::get('/{seriesId}',                    [StatisticaController::class, 'show'])->name('show');
            Route::put('/{seriesId}',                    [StatisticaController::class, 'update'])->name('update');
            Route::delete('/{seriesId}',                 [StatisticaController::class, 'destroy'])->name('destroy');
            Route::post('/{seriesId}/entries',           [StatisticaController::class, 'storeEntry'])->name('store-entry');
            Route::put('/{seriesId}/entries/{entryId}',  [StatisticaController::class, 'updateEntry'])->name('update-entry');
            Route::post('/{seriesId}/bulk-delete',       [StatisticaController::class, 'bulkDeleteEntries'])->name('bulk-delete-entries');
            Route::delete('/{seriesId}/entries/{entryId}',[StatisticaController::class, 'destroyEntry'])->name('destroy-entry');
            Route::post('/{seriesId}/import',            [StatisticaController::class, 'importCsv'])->name('import');
        });

        // ── Company Settings ───────────────────────────
        // URL:  /companies/{company}/settings/...
        // Name: company.settings.*
        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('/', [CompanySettingsController::class, 'index'])->name('index');

            // ── Tenants ───────────────────────────────────────────────
            Route::post('/tenants',              [CompanySettingsController::class, 'storeTenant'])  ->name('tenants.store');
            Route::put('/tenants/{customer}',    [CompanySettingsController::class, 'updateTenant']) ->name('tenants.update');
            Route::delete('/tenants/{customer}', [CompanySettingsController::class, 'destroyTenant'])->name('tenants.destroy');

            // ── Manpower — Departments ────────────────────────────────
            Route::post('/departments',                  [CompanySettingsController::class, 'storeDepartment'])  ->name('departments.store');
            Route::put('/departments/{department}',      [CompanySettingsController::class, 'updateDepartment']) ->name('departments.update');
            Route::delete('/departments/{department}',   [CompanySettingsController::class, 'destroyDepartment'])->name('departments.destroy');

            // ── Manpower — Titles ─────────────────────────────────────
            Route::post('/titles',               [CompanySettingsController::class, 'storeTitle'])  ->name('titles.store');
            Route::put('/titles/{title}',        [CompanySettingsController::class, 'updateTitle']) ->name('titles.update');
            Route::delete('/titles/{title}',     [CompanySettingsController::class, 'destroyTitle'])->name('titles.destroy');

            // ── Costs & Expenses — Categories ─────────────────────────
            Route::post('/expense-categories',                 [CompanySettingsController::class, 'storeExpenseCategory'])  ->name('expense-categories.store');
            Route::put('/expense-categories/{category}',       [CompanySettingsController::class, 'updateExpenseCategory']) ->name('expense-categories.update');
            Route::delete('/expense-categories/{category}',    [CompanySettingsController::class, 'destroyExpenseCategory'])->name('expense-categories.destroy');

            // ── Costs & Expenses — Items ──────────────────────────────
            Route::post('/expense-categories/{category}/items', [CompanySettingsController::class, 'storeExpenseItem'])  ->name('expense-items.store');
            Route::put('/expense-items/{item}',                 [CompanySettingsController::class, 'updateExpenseItem']) ->name('expense-items.update');
            Route::delete('/expense-items/{item}',              [CompanySettingsController::class, 'destroyExpenseItem'])->name('expense-items.destroy');

            // ── Fixed Assets ──────────────────────────────────────────
            Route::post('/fixed-assets',           [CompanySettingsController::class, 'storeFixedAsset'])  ->name('fixed-assets.store');
            Route::put('/fixed-assets/{asset}',    [CompanySettingsController::class, 'updateFixedAsset']) ->name('fixed-assets.update');
            Route::delete('/fixed-assets/{asset}', [CompanySettingsController::class, 'destroyFixedAsset'])->name('fixed-assets.destroy');

            // ── Property Categories ───────────────────────────────────
            Route::post('/property-categories',                    [CompanySettingsController::class, 'storePropertyCategory'])  ->name('property-categories.store');
            Route::put('/property-categories/{category}',          [CompanySettingsController::class, 'updatePropertyCategory']) ->name('property-categories.update');
            Route::delete('/property-categories/{category}',       [CompanySettingsController::class, 'destroyPropertyCategory'])->name('property-categories.destroy');

            // ── Property Types ────────────────────────────────────────
            Route::post('/property-categories/{category}/types',   [CompanySettingsController::class, 'storePropertyType'])  ->name('property-types.store');
            Route::put('/property-types/{type}',                   [CompanySettingsController::class, 'updatePropertyType']) ->name('property-types.update');
            Route::delete('/property-types/{type}',                [CompanySettingsController::class, 'destroyPropertyType'])->name('property-types.destroy');

        }); // end settings

    }); // end company-scoped routes

}); // end auth middleware

require __DIR__.'/auth.php';