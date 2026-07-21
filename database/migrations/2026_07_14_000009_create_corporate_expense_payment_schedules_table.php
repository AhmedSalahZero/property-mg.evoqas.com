<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment Schedule repeater for Corporate Expenses — identical shape to
 * property_expense_payment_schedules (see that migration's docblock for the
 * full reasoning). Kept as a separate table rather than a shared/polymorphic
 * one to match how this app already keeps Direct and Corporate expenses
 * fully parallel everywhere else (separate expense tables, separate payment
 * tables, separate everything) rather than introducing the first
 * polymorphic relation in the codebase for this one feature.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corporate_expense_payment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('corporate_expense_id')->constrained()->cascadeOnDelete();

            $table->decimal('percentage', 5, 2);
            $table->decimal('amount', 15, 2);
            $table->date('forecasted_date');
            $table->string('payment_term', 20)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['company_id', 'forecasted_date'], 'ce_pay_sched_company_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corporate_expense_payment_schedules');
    }
};