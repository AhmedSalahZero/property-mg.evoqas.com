<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment Schedule repeater for Direct (Property) Expenses.
 *
 * Each row is one forecasted installment of an expense: a % of the total
 * expense_amount, the amount that % works out to (snapshotted at save
 * time), a forecasted payment date, and which built-in payment term (if
 * any) was used to generate that date — kept for display/audit only, never
 * read back to recompute anything.
 *
 * This table holds the PLAN, not payment history — actual cash paid still
 * lives in property_expense_payments, completely unchanged. The Cash
 * Forecast combines "how much has really been paid so far" (from
 * property_expense_payments) with "the plan" (this table) to work out how
 * much of each row is still outstanding and on what date — see
 * ExpensePaymentScheduleService. Because these rows never carry a "paid"
 * status of their own, editing an expense's schedule can safely just
 * delete-and-replace the whole set on every save (unlike rent collections
 * or installment dues, there's no history here that a wholesale replace
 * could destroy).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_expense_payment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_expense_id')->constrained()->cascadeOnDelete();

            $table->decimal('percentage', 5, 2);   // e.g. 40.00
            $table->decimal('amount', 15, 2);      // percentage% of expense_amount, snapshotted at save time
            $table->date('forecasted_date');
            $table->string('payment_term', 20)->nullable(); // 'cash','net_30',...,'net_180', or null if hand-picked
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['company_id', 'forecasted_date'], 'pe_pay_sched_company_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_expense_payment_schedules');
    }
};