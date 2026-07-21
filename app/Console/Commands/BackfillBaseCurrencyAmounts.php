<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\PropertyExpense;
use App\Models\PropertyExpensePayment;
use App\Models\PropertyInstallmentDue;
use App\Models\RentCollection;
use App\Models\RentRevenue;
use App\Services\CurrencyConversionService;
use Illuminate\Console\Command;

/**
 * One-time (re-runnable) backfill for audit finding C4.
 *
 * Fix for audit finding C3‑B: this file previously lived at
 * app/Commands/BackfillBaseCurrencyAmounts.php, outside the
 * app/Console/Commands directory Laravel's command auto-discovery scans —
 * so 'property:backfill-fx' was never actually reachable via `php artisan`.
 * Moved here to fix that; no logic below was changed. After deploying this
 * fix, run `php artisan property:backfill-fx` once (per company, or for
 * all companies) to compute base_amount for every historical row that
 * predates the C4 currency-conversion fix.
 *
 * Every row created before the base_amount/base_currency/fx_rate_used columns
 * existed has them as NULL. This command computes them for any row still
 * missing a base_amount, using whatever FX rates exist in currency_rates at
 * the time this command is run.
 *
 * Safe to re-run any time (e.g. after importing more historical FX rates) —
 * it only ever touches rows where base_amount IS NULL, so it never overwrites
 * an already-computed conversion.
 */
class BackfillBaseCurrencyAmounts extends Command
{
    protected $signature = 'property:backfill-fx {--company= : Only backfill a single company ID}';

    protected $description = 'Compute base_amount/base_currency/fx_rate_used for existing rows created before FX conversion existed.';

    public function handle(CurrencyConversionService $fx): int
    {
        $companies = Company::query()
            ->when($this->option('company'), fn ($q, $id) => $q->where('id', $id))
            ->get(['id', 'currency']);

        foreach ($companies as $company) {
            $base = strtoupper($company->currency ?: 'EGP');
            $this->info("Company #{$company->id} ({$base}):");

            $this->backfillRentRevenues($company, $base, $fx);
            $this->backfillRentCollections($company, $base, $fx);
            $this->backfillExpenses($company, $base, $fx);
            $this->backfillExpensePayments($company, $base, $fx);
            $this->backfillInstallmentDues($company, $base, $fx);
        }

        $this->info('Done.');

        return self::SUCCESS;
    }

    private function backfillRentRevenues(Company $company, string $base, CurrencyConversionService $fx): void
    {
        $count = 0;
        RentRevenue::where('company_id', $company->id)->whereNull('base_amount')
            ->chunkById(500, function ($rows) use (&$count, $company, $base, $fx) {
                foreach ($rows as $row) {
                    $conversion = $fx->convert($company->id, $base, (float) $row->revenue_amount, $row->currency, $row->revenue_date);
                    $row->update($conversion);
                    $count++;
                }
            });
        $this->line("  rent_revenues: {$count} row(s) backfilled.");
    }

    private function backfillRentCollections(Company $company, string $base, CurrencyConversionService $fx): void
    {
        $count = 0;
        RentCollection::where('company_id', $company->id)->whereNull('base_amount')
            ->chunkById(500, function ($rows) use (&$count, $company, $base, $fx) {
                foreach ($rows as $row) {
                    $conversion = $fx->convert($company->id, $base, (float) $row->collection_amount, $row->currency, $row->collection_date);
                    $row->update($conversion);
                    $count++;
                }
            });
        $this->line("  rent_collections: {$count} row(s) backfilled.");
    }

    private function backfillExpenses(Company $company, string $base, CurrencyConversionService $fx): void
    {
        $count = 0;
        PropertyExpense::where('company_id', $company->id)->whereNull('base_amount')
            ->chunkById(500, function ($rows) use (&$count, $company, $base, $fx) {
                foreach ($rows as $row) {
                    $manualRate = $row->fx_rate ? (float) $row->fx_rate : null;
                    $conversion = ($manualRate && strtoupper($row->currency) !== $base)
                        ? ['base_amount' => round($row->expense_amount * $manualRate, 2), 'base_currency' => $base, 'fx_rate_used' => $manualRate]
                        : $fx->convert($company->id, $base, (float) $row->expense_amount, $row->currency, $row->expense_date);
                    $row->update($conversion);
                    $count++;
                }
            });
        $this->line("  property_expenses: {$count} row(s) backfilled.");
    }

    private function backfillExpensePayments(Company $company, string $base, CurrencyConversionService $fx): void
    {
        $count = 0;
        PropertyExpensePayment::where('company_id', $company->id)->whereNull('base_amount')
            ->with('expense:id,currency,fx_rate')
            ->chunkById(500, function ($rows) use (&$count, $company, $base, $fx) {
                foreach ($rows as $row) {
                    $currency   = $row->expense?->currency ?: $base;
                    $manualRate = $row->expense?->fx_rate ? (float) $row->expense->fx_rate : null;
                    $conversion = ($manualRate && strtoupper($currency) !== $base)
                        ? ['base_amount' => round($row->amount * $manualRate, 2), 'base_currency' => $base, 'fx_rate_used' => $manualRate]
                        : $fx->convert($company->id, $base, (float) $row->amount, $currency, $row->payment_date);
                    $row->update($conversion);
                    $count++;
                }
            });
        $this->line("  property_expense_payments: {$count} row(s) backfilled.");
    }

    private function backfillInstallmentDues(Company $company, string $base, CurrencyConversionService $fx): void
    {
        $count = 0;
        PropertyInstallmentDue::where('company_id', $company->id)->whereNull('base_amount')
            ->chunkById(500, function ($rows) use (&$count, $company, $base, $fx) {
                foreach ($rows as $row) {
                    $conversion = $fx->convert($company->id, $base, (float) $row->amount, $row->currency, $row->due_date);
                    $row->update($conversion);
                    $count++;
                }
            });
        $this->line("  property_installment_dues: {$count} row(s) backfilled.");
    }
}