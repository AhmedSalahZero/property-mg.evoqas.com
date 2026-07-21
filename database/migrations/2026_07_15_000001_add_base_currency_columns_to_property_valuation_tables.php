<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fix for audit Findings 1 / 3 / 4 (July 2026 cross-audit) — root cause.
 *
 * The original base-currency migration (2026_07_12_000002) added
 * base_amount / base_currency / fx_rate_used to every TRANSACTION table
 * (rent_revenues, rent_collections, property_expenses,
 * property_expense_payments, property_installment_dues), but not to the
 * three tables that hold property VALUATIONS: properties, property_units,
 * and property_market_values. That gap is what let a foreign-currency
 * property's market value / acquisition cost reach the Keep-or-Sell engine
 * unconverted while its revenue/expense figures were already converted
 * (Finding 1), and forced the Dashboard's Portfolio tab to recompute the
 * conversion live on every page load instead of storing it once (Finding 3).
 *
 * `properties` and `property_units` each carry TWO money columns sharing
 * one `currency` field (acquisition_cost, book_value), so each gets its own
 * pair of base_amount columns plus one shared base_currency/fx_rate_used
 * (both figures are in the same source currency, so one FX rate covers
 * both). `property_market_values` has a single money column
 * (market_value) and inherits its currency from the parent
 * property/unit, so it gets the standard single base_amount set.
 *
 * Same rule as every other base-currency column in this app: computed and
 * stored ONCE at write time (acquisition_cost/book_value use the rate in
 * effect on acquisition_date; market_value uses the rate in effect on its
 * own value_date), nullable, and never guessed at — if no FX rate exists
 * yet for a given currency/date, it stays null and the UI surfaces that
 * rather than assuming 1:1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->decimal('acquisition_cost_base_amount', 18, 2)->nullable()->after('acquisition_cost');
            $table->decimal('book_value_base_amount', 18, 2)->nullable()->after('book_value');
            $table->string('base_currency', 10)->nullable()->after('book_value_base_amount');
            $table->decimal('fx_rate_used', 18, 6)->nullable()->after('base_currency');
        });

        Schema::table('property_units', function (Blueprint $table) {
            $table->decimal('acquisition_cost_base_amount', 18, 2)->nullable()->after('acquisition_cost');
            $table->decimal('book_value_base_amount', 18, 2)->nullable()->after('book_value');
            $table->string('base_currency', 10)->nullable()->after('book_value_base_amount');
            $table->decimal('fx_rate_used', 18, 6)->nullable()->after('base_currency');
        });

        Schema::table('property_market_values', function (Blueprint $table) {
            $table->decimal('base_amount', 18, 2)->nullable()->after('market_value');
            $table->string('base_currency', 10)->nullable()->after('base_amount');
            $table->decimal('fx_rate_used', 18, 6)->nullable()->after('base_currency');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['acquisition_cost_base_amount', 'book_value_base_amount', 'base_currency', 'fx_rate_used']);
        });

        Schema::table('property_units', function (Blueprint $table) {
            $table->dropColumn(['acquisition_cost_base_amount', 'book_value_base_amount', 'base_currency', 'fx_rate_used']);
        });

        Schema::table('property_market_values', function (Blueprint $table) {
            $table->dropColumn(['base_amount', 'base_currency', 'fx_rate_used']);
        });
    }
};
