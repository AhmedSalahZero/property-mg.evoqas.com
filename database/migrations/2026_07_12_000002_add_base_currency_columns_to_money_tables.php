<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fix for audit finding C4 (continued).
 *
 * Adds base_amount / base_currency / fx_rate_used to every money-bearing table.
 * These are computed and stored ONCE, at the moment the record is created or
 * edited, using the FX rate in effect on that record's date — the standard
 * "transaction currency + functional currency" accounting pattern. All
 * dashboard/report aggregates should SUM(base_amount) instead of the raw
 * (transaction-currency) amount column, so mixed-currency data always rolls
 * up correctly into the company's base currency (companies.currency).
 *
 * base_amount is nullable: if no FX rate exists yet for a given currency/date,
 * it stays null rather than silently guessing 1:1 — the UI surfaces this so
 * the user knows to add the missing rate.
 */
return new class extends Migration
{
    private array $tables = [
        'rent_revenues'             => 'revenue_amount',
        'rent_collections'          => 'collection_amount',
        'property_expenses'         => 'expense_amount',
        'property_expense_payments' => 'amount',
        'property_installment_dues' => 'amount',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table => $afterColumn) {
            Schema::table($table, function (Blueprint $t) use ($afterColumn) {
                $t->decimal('base_amount', 18, 2)->nullable()->after($afterColumn);
                $t->string('base_currency', 10)->nullable()->after('base_amount');
                $t->decimal('fx_rate_used', 18, 6)->nullable()->after('base_currency');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table => $afterColumn) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['base_amount', 'base_currency', 'fx_rate_used']);
            });
        }
    }
};
