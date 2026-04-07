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
use App\Http\Controllers\RentContractController;
use App\Http\Controllers\PropertyInstallmentController;
use App\Http\Controllers\PropertyExpenseController;
use App\Http\Controllers\PropertyDashboardController;
use App\Http\Controllers\CashForecastController;
use App\Http\Controllers\KeepOrSellController;

// ══════════════════════════════════════════════════════
// PUBLIC — Welcome / Login redirect
// ══════════════════════════════════════════════════════
Route::get('/keep-or-sell/share/{token}', [KeepOrSellController::class, 'share'])->name('keep-or-sell.share');
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
Route::middleware(['auth', 'verified'])->group(function () {

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

        // ── Properties ─────────────────────────────────────────────────
        // URL:  /companies/{company}/properties/...
        // Name: company.properties.*
        Route::prefix('properties')->name('properties.')->group(function () {
            Route::get('/',                   [PropertyController::class, 'index'])->name('index');
            Route::get('/create',             [PropertyController::class, 'create'])->name('create');
            Route::post('/',                  [PropertyController::class, 'store'])->name('store');
            Route::get('/dashboard',          [PropertyDashboardController::class, 'index'])->name('dashboard');
            Route::get('/dashboard/data',     [PropertyDashboardController::class, 'data'])->name('dashboard.data');
            Route::get('/cash-forecast',      [CashForecastController::class, 'index'])->name('cash-forecast');
            Route::get('/cash-forecast/data', [CashForecastController::class, 'data'])->name('cash-forecast.data');

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
            Route::get('/{property}/edit',    [PropertyController::class, 'edit'])->name('edit');
            Route::put('/{property}',         [PropertyController::class, 'update'])->name('update');
            Route::get('/{property}',         [PropertyController::class, 'show'])->name('show');
            Route::delete('/{property}',      [PropertyController::class, 'destroy'])->name('destroy');

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
                Route::delete('/{contract}',            [RentContractController::class, 'destroy'])->name('destroy');
            });

            // ── Due Installments ─────────────────────────────────────────────
            // URL:  /companies/{company}/properties/{property}/installments/...
            // Name: company.properties.installments.*
            Route::prefix('/{property}/installments')->name('installments.')->group(function () {
                Route::get('/',                              [PropertyInstallmentController::class, 'load'])     ->name('load');
                Route::post('/',                             [PropertyInstallmentController::class, 'save'])     ->name('save');
                Route::patch('/{due}/mark-paid',             [PropertyInstallmentController::class, 'markPaid'])->name('mark-paid');
                Route::post('/import',                       [PropertyInstallmentController::class, 'import'])  ->name('import');
            });

            // ── Property Expenses ────────────────────────────────────────────
            // URL:  /companies/{company}/properties/{property}/expenses/...
            // Name: company.properties.expenses.*
            Route::prefix('/{property}/expenses')->name('expenses.')->group(function () {
                Route::get('/',                                          [PropertyExpenseController::class, 'index'])        ->name('index');
                Route::post('/',                                         [PropertyExpenseController::class, 'store'])        ->name('store');
                Route::put('/{expense}',                                 [PropertyExpenseController::class, 'update'])       ->name('update');
                Route::delete('/{expense}',                              [PropertyExpenseController::class, 'destroy'])      ->name('destroy');
                Route::post('/{expense}/payments',                       [PropertyExpenseController::class, 'addPayment'])   ->name('payments.store');
                Route::delete('/{expense}/payments/{payment}',           [PropertyExpenseController::class, 'deletePayment'])->name('payments.destroy');
            });

        }); // end properties

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