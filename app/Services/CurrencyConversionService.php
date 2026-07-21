<?php

namespace App\Services;

use App\Models\CurrencyRate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Fix for audit finding C4 — central place all currency conversion goes
 * through. Every money-bearing record (rent revenue, rent collection, property
 * expense, expense payment, installment due) gets a base_amount computed by
 * this service at write time, using the FX rate closest to that record's date.
 *
 * "Base currency" = the owning company's companies.currency field (already existed).
 */
class CurrencyConversionService
{
    /**
     * Convert $amount (denominated in $currency) into the company's base
     * currency. Returns an array ready to be merged into any of the
     * base_amount/base_currency/fx_rate_used column sets.
     *
     * If no FX rate exists at all for that currency, base_amount comes back
     * null rather than silently assuming 1:1 — callers/UI should treat a null
     * base_amount as "needs an FX rate," not "zero."
     */
    public function convert(int $companyId, string $baseCurrency, float $amount, ?string $currency, $date): array
    {
        $currency     = strtoupper($currency ?: $baseCurrency);
        $baseCurrency = strtoupper($baseCurrency);

        if ($currency === $baseCurrency) {
            return [
                'base_amount'   => round($amount, 2),
                'base_currency' => $baseCurrency,
                'fx_rate_used'  => 1.0,
            ];
        }

        $rate = $this->findRate($companyId, $currency, $date);

        if ($rate === null) {
            return [
                'base_amount'   => null,
                'base_currency' => $baseCurrency,
                'fx_rate_used'  => null,
            ];
        }

        return [
            'base_amount'   => round($amount * $rate, 2),
            'base_currency' => $baseCurrency,
            'fx_rate_used'  => $rate,
        ];
    }

    /**
     * The most recently entered rate on file for $currency, regardless of
     * what date it's dated to — used for LIVE reporting conversion (per the
     * business decision: main-functional-currency dashboards/reports always
     * reflect the latest FX rate the user has entered, not the rate that was
     * in effect on the day a specific transaction happened). Contrast with
     * findRate($date), which is still used to compute the historical
     * base_amount stored on each row at write time (kept only as an audit
     * record of "what rate applied when this was recorded").
     */
    public function latestRate(int $companyId, string $currency): ?float
    {
        $currency = strtoupper($currency);

        $cacheKey = "fx:latest:{$companyId}:{$currency}";

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($companyId, $currency) {
            $rate = CurrencyRate::forCompany($companyId)
                ->where('currency', $currency)
                ->orderByDesc('rate_date')
                ->first();

            return $rate ? (float) $rate->rate : null;
        });
    }

    /**
     * Convert an amount from one non-base currency to another (e.g. a USD
     * contract's insurance deposit actually collected in SAR), by pivoting
     * through the company's base currency using the latest rate for each
     * leg. Returns null if either leg can't be priced (no rate on file).
     */
    public function convertBetween(int $companyId, string $baseCurrency, float $amount, string $fromCurrency, string $toCurrency): ?array
    {
        $baseCurrency = strtoupper($baseCurrency);
        $fromCurrency = strtoupper($fromCurrency);
        $toCurrency   = strtoupper($toCurrency);

        if ($fromCurrency === $toCurrency) {
            return ['amount' => round($amount, 2), 'rate' => 1.0];
        }

        // Leg 1: from -> base
        if ($fromCurrency === $baseCurrency) {
            $inBase = $amount;
        } else {
            $rate1 = $this->latestRate($companyId, $fromCurrency);
            if ($rate1 === null) return null;
            $inBase = $amount * $rate1;
        }

        // Leg 2: base -> to
        if ($toCurrency === $baseCurrency) {
            $result = $inBase;
            $effectiveRate = $fromCurrency === $baseCurrency ? 1.0 : $this->latestRate($companyId, $fromCurrency);
        } else {
            $rate2 = $this->latestRate($companyId, $toCurrency);
            if ($rate2 === null) return null;
            $result = $inBase / $rate2;
            $effectiveRate = round($result / max($amount, 0.0000001), 6);
        }

        return ['amount' => round($result, 2), 'rate' => $effectiveRate ?? null];
    }

    /**
     * Convert a set of amounts already grouped by their own currency (e.g.
     * the result of `SELECT currency, SUM(amount) ... GROUP BY currency`)
     * into the company's base currency, using the LATEST rate for each
     * currency — not the rate on any particular transaction date.
     *
     * This is the method Dashboard/Cash Forecast/Report "main functional
     * currency" views should call. Because it converts per currency GROUP
     * rather than per row, it stays fast even on a large portfolio — a
     * company typically deals in a handful of currencies, not thousands of
     * distinct rates, so this is a handful of cache-backed lookups per page
     * load rather than one per transaction.
     *
     * @param  array<string,float>  $amountsByCurrency  e.g. ['EGP' => 20000, 'USD' => 5000]
     * @return array{
     *   total: float,
     *   base_currency: string,
     *   breakdown: array<int, array{currency:string, original_amount:float, rate: float|null, converted_amount: float|null}>,
     *   unconverted_currencies: array<int,string>
     * }
     */
    public function convertGroupedAmounts(int $companyId, string $baseCurrency, array $amountsByCurrency): array
    {
        $baseCurrency = strtoupper($baseCurrency);
        $total        = 0.0;
        $breakdown    = [];
        $unconverted  = [];

        foreach ($amountsByCurrency as $currency => $amount) {
            $currency = strtoupper($currency);
            $amount   = (float) $amount;

            if ($currency === $baseCurrency) {
                $total += $amount;
                $breakdown[] = ['currency' => $currency, 'original_amount' => round($amount, 2), 'rate' => 1.0, 'converted_amount' => round($amount, 2)];
                continue;
            }

            $rate = $this->latestRate($companyId, $currency);

            if ($rate === null) {
                $unconverted[] = $currency;
                $breakdown[] = ['currency' => $currency, 'original_amount' => round($amount, 2), 'rate' => null, 'converted_amount' => null];
                continue;
            }

            $converted = round($amount * $rate, 2);
            $total += $converted;
            $breakdown[] = ['currency' => $currency, 'original_amount' => round($amount, 2), 'rate' => $rate, 'converted_amount' => $converted];
        }

        return [
            'total'                  => round($total, 2),
            'base_currency'          => $baseCurrency,
            'breakdown'              => $breakdown,
            'unconverted_currencies' => array_values(array_unique($unconverted)),
        ];
    }

    /**
     * Every currency actually in use somewhere in this company's data —
     * feeds the currency-picker dropdown on Dashboard/Cash Forecast/Reports.
     * Always includes the base currency itself even if nothing is
     * denominated in it yet, so the dropdown never comes back empty.
     */
    public function usedCurrencies(int $companyId, string $baseCurrency): array
    {
        $currencies = collect();

        $currencies = $currencies
            ->merge(\Illuminate\Support\Facades\DB::table('rent_contracts')->where('company_id', $companyId)->distinct()->pluck('contract_currency'))
            ->merge(\Illuminate\Support\Facades\DB::table('rent_contracts')->where('company_id', $companyId)->distinct()->pluck('collection_currency'))
            ->merge(\Illuminate\Support\Facades\DB::table('property_expenses')->where('company_id', $companyId)->distinct()->pluck('currency'))
            ->merge(\Illuminate\Support\Facades\DB::table('property_installment_plans')->where('company_id', $companyId)->distinct()->pluck('currency'))
            ->push($baseCurrency)
            ->filter()
            ->map(fn ($c) => strtoupper($c))
            ->unique()
            ->sort()
            ->values();

        // Base currency always first
        return $currencies->sortBy(fn ($c) => $c === strtoupper($baseCurrency) ? 0 : 1)->values()->all();
    }

    /**
     * Find the FX rate for $currency closest to $date.
     * Prefers the most recent rate on or before $date (i.e. the rate that was
     * actually in effect at the time). If nothing exists on/before $date
     * (e.g. the very first rate ever entered is dated after this transaction),
     * falls back to the earliest rate available after it, so a transaction
     * doesn't go unconverted just because rates started being tracked later.
     */
    public function findRate(int $companyId, string $currency, $date): ?float
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);
        $currency = strtoupper($currency);

        // Cache per (company, currency, date) within a single request/queue job —
        // schedule generation can ask for the same day's rate hundreds of times.
        $cacheKey = "fx:{$companyId}:{$currency}:{$date->toDateString()}";

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($companyId, $currency, $date) {
            $onOrBefore = CurrencyRate::forCompany($companyId)
                ->where('currency', $currency)
                ->where('rate_date', '<=', $date->toDateString())
                ->orderByDesc('rate_date')
                ->first();

            if ($onOrBefore) {
                return (float) $onOrBefore->rate;
            }

            $after = CurrencyRate::forCompany($companyId)
                ->where('currency', $currency)
                ->where('rate_date', '>', $date->toDateString())
                ->orderBy('rate_date')
                ->first();

            return $after ? (float) $after->rate : null;
        });
    }
}
