<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corporate Expenses — company-level costs (not tied to a single property)
 * that get spread across the portfolio via an area-weighted allocation
 * engine. See CorporateExpenseAllocationService for the methodology.
 *
 * allocation_scope drives WHICH units share the cost:
 *   - occupied                   → units occupied as of expense_date
 *   - all_include_not_delivered  → every unit, no exceptions
 *   - all_exclude_not_delivered  → occupied + vacant only, as of expense_date
 *   - custom                     → the exact set of units in
 *                                   corporate_expense_allocations for this
 *                                   expense (user hand-picked them)
 *
 * Allocation is accrual-based: everything is evaluated against
 * expense_date, never payment_date and never "today."
 */
return new class extends Migration
{
    public function up(): void
    {
		Schema::dropIfExists('corporate_expenses');
        Schema::create('corporate_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_category_id')->constrained('expense_categories')->cascadeOnDelete();
            $table->foreignId('expense_item_id')->constrained('expense_items')->cascadeOnDelete();
            $table->date('expense_date');
            $table->decimal('expense_amount', 15, 2);
            $table->string('currency', 10)->default('EGP');
            $table->decimal('base_amount', 18, 2)->nullable();
            $table->string('base_currency', 10)->nullable();
            $table->decimal('fx_rate_used', 18, 6)->nullable();
            $table->decimal('fx_rate', 12, 6)->nullable(); // manual override, same pattern as property_expenses
            $table->enum('allocation_scope', [
                'occupied',
                'all_include_not_delivered',
                'all_exclude_not_delivered',
                'custom',
            ]);
            $table->text('notes')->nullable();
            $table->enum('status', ['unpaid', 'partially_paid', 'fully_paid'])->default('unpaid');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corporate_expenses');
    }
};
