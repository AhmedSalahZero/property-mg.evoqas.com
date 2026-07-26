<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 of the "Record Sale" feature (confirmed July 2026) — the actual
 * receivable due-date schedule for a sale made on installments. Mirrors
 * property_installment_dues (money the company OWES a developer) but in
 * reverse: money a BUYER owes the company after a sale.
 *
 * One row per due payment (down payment or installment), same status
 * lifecycle as rent_collections: pending → collected (via markCollected())
 * or overdue (recalculated the same way property_installment_dues' status
 * is). No base_amount/base_currency columns — Cash Forecast converts these
 * live via CurrencyConversionService::latestRate(), the same way it already
 * treats rent_collections and property_installment_dues, rather than
 * freezing a rate at write time (these are still-open receivables, not
 * settled transactions).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_sale_dues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_sale_id')->constrained()->cascadeOnDelete();

            $table->enum('due_type', ['down_payment', 'installment'])->default('installment');
            $table->date('due_date');
            $table->decimal('amount', 18, 2);
            $table->string('currency', 10)->default('EGP');

            $table->enum('status', ['pending', 'collected', 'overdue'])->default('pending');
            $table->date('collected_date')->nullable();
            $table->text('notes')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'due_date']);
            $table->index(['property_sale_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_sale_dues');
    }
};
