<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payments against a corporate_expenses row — same pattern as
 * property_expense_payments. Purely cash-basis history; never touches
 * allocation (allocation is accrual-based off expense_date, see the
 * corporate_expenses migration).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corporate_expense_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('corporate_expense_id')->constrained('corporate_expenses')->cascadeOnDelete();
            $table->date('payment_date');
            $table->decimal('amount', 15, 2);
            $table->decimal('base_amount', 18, 2)->nullable();
            $table->string('base_currency', 10)->nullable();
            $table->decimal('fx_rate_used', 18, 6)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corporate_expense_payments');
    }
};
