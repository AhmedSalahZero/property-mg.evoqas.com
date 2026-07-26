<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Property;
use App\Models\PropertyMarketValue;
use App\Models\PropertyUnit;
use App\Services\CurrencyConversionService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * One-time (re-runnable) backfill for audit Findings 1 / 3 / 4 (July 2026
 * cross-audit) — the property-valuation counterpart to
 * BackfillBaseCurrencyAmounts (which covers the five transaction tables
 * from the original C4 fix). Every properties/property_units/
 * property_market_values row created before the
 * 2026_07_15_000001_add_base_currency_columns_to_property_valuation_tables
 * migration has these columns as NULL — this command computes them using
 * whatever FX rates exist in currency_rates at the time it's run.
 *
 * Safe to re-run any time (e.g. after importing more historical FX rates)
 * — it only ever touches rows where the relevant base_amount column IS
 * NULL, so it never overwrites an already-computed conversion. Run it once
 * after deploying the migration:
 *
 *   php artisan property:backfill-valuation-fx
 *   php artisan property:backfill-valuation-fx --company=3   (single company)
 */
class BackfillPropertyValuationBaseAmounts extends Command
{
    protected $signature = 'property:backfill-valuation-fx {--company= : Only backfill a single company ID}';

    protected $description = 'Compute base-currency amounts for properties/property_units/property_market_values rows created before the valuation FX fix.';

    public function handle(CurrencyConversionService $fx): int
    {
        $companies = Company::query()
            ->when($this->option('company'), fn ($q, $id) => $q->where('id', $id))
            ->get(['id', 'currency']);

        foreach ($companies as $company) {
            $base = strtoupper($company->currency ?: 'EGP');
            $this->info("Company #{$company->id} ({$base}):");

            $this->backfillProperties($company, $base, $fx);
            $this->backfillPropertyUnits($company, $base, $fx);
            $this->backfillMarketValues($company, $base, $fx);
        }

        $this->info('Done.');

        return self::SUCCESS;
    }

    /**
     * Rows here have TWO money columns (acquisition_cost, book_value)
     * sharing one currency and one acquisition_date — both are converted
     * using the same rate, same as the write-time logic in
     * PropertyController::propertyValuationConversion().
     */
    private function backfillProperties(Company $company, string $base, CurrencyConversionService $fx): void
    {
        $count = 0;
        Property::where('company_id', $company->id)
            ->where(function ($q) {
                $q->whereNotNull('acquisition_cost')->orWhereNotNull('book_value');
            })
            ->where(function ($q) {
                $q->whereNull('acquisition_cost_base_amount')->orWhereNull('book_value_base_amount');
            })
            ->chunkById(500, function ($rows) use (&$count, $company, $base, $fx) {
                foreach ($rows as $row) {
                    $this->applyValuationConversion($row, $company, $base, $fx);
                    $count++;
                }
            });
        $this->line("  properties: {$count} row(s) backfilled.");
    }

    private function backfillPropertyUnits(Company $company, string $base, CurrencyConversionService $fx): void
    {
        $count = 0;
        PropertyUnit::where('company_id', $company->id)
            ->where(function ($q) {
                $q->whereNotNull('acquisition_cost')->orWhereNotNull('book_value');
            })
            ->where(function ($q) {
                $q->whereNull('acquisition_cost_base_amount')->orWhereNull('book_value_base_amount');
            })
            ->chunkById(500, function ($rows) use (&$count, $company, $base, $fx) {
                foreach ($rows as $row) {
                    $this->applyValuationConversion($row, $company, $base, $fx);
                    $count++;
                }
            });
        $this->line("  property_units: {$count} row(s) backfilled.");
    }

    /**
     * @param  Property|PropertyUnit  $row
     */
    private function applyValuationConversion($row, Company $company, string $base, CurrencyConversionService $fx): void
    {
        $currency = $row->currency ?: 'EGP';
        $date     = $this->parseMonthYearOrToday($row->acquisition_date);

        $update = ['base_currency' => strtoupper($base)];

        if ($row->acquisition_cost !== null) {
            $conversion = $fx->convert($company->id, $base, (float) $row->acquisition_cost, $currency, $date);
            $update['acquisition_cost_base_amount'] = $conversion['base_amount'];
            $update['fx_rate_used']                 = $conversion['fx_rate_used'];
        }

        if ($row->book_value !== null) {
            $conversion = $fx->convert($company->id, $base, (float) $row->book_value, $currency, $date);
            $update['book_value_base_amount'] = $conversion['base_amount'];
            $update['fx_rate_used']           = $update['fx_rate_used'] ?? $conversion['fx_rate_used'];
        }

        $row->update($update);
    }

    /**
     * Each market value repeater row is its own dated valuation event, so
     * it's converted using the rate in effect on its own value_date —
     * matching PropertyController::marketValueConversion(). Currency is
     * inherited from the parent property/unit (property_market_values has
     * no currency column of its own).
     */
    private function backfillMarketValues(Company $company, string $base, CurrencyConversionService $fx): void
    {
        $count = 0;
        PropertyMarketValue::where('company_id', $company->id)
            ->whereNull('base_amount')
            ->with(['property:id,currency', 'propertyUnit:id,currency'])
            ->chunkById(500, function ($rows) use (&$count, $company, $base, $fx) {
                foreach ($rows as $row) {
                    $currency = $row->propertyUnit?->currency ?: ($row->property?->currency ?: 'EGP');
                    $date     = $this->parseMonthYearOrToday($row->value_date);
                    $conversion = $fx->convert($company->id, $base, (float) $row->market_value, $currency, $date);
                    $row->update($conversion);
                    $count++;
                }
            });
        $this->line("  property_market_values: {$count} row(s) backfilled.");
    }

    private function parseMonthYearOrToday(?string $value): Carbon
    {
        if (empty($value)) {
            return Carbon::today();
        }
        $value = trim($value);
        try {
            if (preg_match('#^\d{4}-\d{1,2}$#', $value)) {
                return Carbon::createFromFormat('Y-m', $value)->startOfMonth();
            }
            return Carbon::createFromFormat('m/Y', $value)->startOfMonth();
        } catch (\Exception $e) {
            return Carbon::today();
        }
    }
}
