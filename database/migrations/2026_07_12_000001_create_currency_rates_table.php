<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fix for audit finding C4 — the app supports contracts/expenses in six
 * currencies (EGP/USD/EUR/SAR/AED/QAR) but nowhere converts them to a common
 * currency before summing, so every dashboard/report total silently treats
 * 1 USD = 1 EGP whenever a foreign-currency record exists.
 *
 * This table stores one FX rate per (company, foreign currency, date) —
 * "units of the company's base currency per 1 unit of `currency`". The
 * company's base currency is `companies.currency` (already existed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currency_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('currency', 10);   // the foreign currency, e.g. 'USD'
            $table->date('rate_date');
            $table->decimal('rate', 18, 6);   // 1 unit of `currency` = this many units of the company's base currency
            $table->string('source', 20)->default('manual'); // manual | excel_import
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'currency', 'rate_date'], 'currency_rates_unique_per_day');
            $table->index(['company_id', 'currency', 'rate_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_rates');
    }
};
