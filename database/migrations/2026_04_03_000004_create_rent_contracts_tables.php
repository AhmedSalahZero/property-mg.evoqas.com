<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Rent Contracts ────────────────────────────────────────────────────
        Schema::create('rent_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            // ── Linked asset (unit or standalone property) ────────────────────
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_unit_id')->nullable()->constrained('property_units')->nullOnDelete();

            // ── Revenue type ──────────────────────────────────────────────────
            $table->enum('revenue_type', ['direct_rent', 'management_fee'])->default('direct_rent');
            $table->decimal('management_fee_rate', 5, 2)->nullable(); // % of contract rent — only for management_fee

            // ── Tenant ────────────────────────────────────────────────────────
            $table->enum('tenant_nature', ['individual', 'corporate']);
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();

            // ── Contract dates ────────────────────────────────────────────────
            $table->date('start_date');
            $table->date('end_date');

            // ── Financials ────────────────────────────────────────────────────
            $table->string('contract_currency', 10)->default('EGP');
            $table->decimal('monthly_rent_amount', 15, 2);
            $table->decimal('variable_revenue_pct', 5, 2)->nullable();   // info only
            $table->decimal('min_monthly_rent', 15, 2)->nullable();       // if set, overrides monthly_rent_amount as basis
            $table->string('collection_currency', 10)->default('EGP');
            $table->unsignedTinyInteger('collection_interval_months')->default(1); // 1,2,3,4,6,12
            $table->unsignedTinyInteger('insurance_months')->default(0);
            $table->decimal('insurance_amount', 15, 2)->default(0);       // auto-calculated: basis_rent × insurance_months
            $table->decimal('annual_increase_rate', 5, 2)->default(0);   // %

            // ── Renewal tracking ──────────────────────────────────────────────
            $table->foreignId('renewed_from_contract_id')->nullable()->constrained('rent_contracts')->nullOnDelete();

            // ── Status ────────────────────────────────────────────────────────
            $table->enum('status', ['running', 'expired', 'terminated'])->default('running');
            $table->date('terminated_date')->nullable();
            $table->text('termination_notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // ── Rent Revenues — one row per month ────────────────────────────────
        Schema::create('rent_revenues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rent_contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('revenue_date');          // first day of the month
            $table->string('period_label', 10);    // e.g. "03/2026"
            $table->decimal('monthly_rent', 15, 2);     // rent basis for this month (after increase applied)
            $table->decimal('revenue_amount', 15, 2);   // = monthly_rent for direct_rent; = monthly_rent × fee_rate for management_fee
            $table->string('currency', 10)->default('EGP');
            $table->unsignedSmallInteger('year_number')->default(1); // which contract year (1, 2, 3…)
            $table->timestamps();
        });

        // ── Rent Collections — one row per collection interval ───────────────
        Schema::create('rent_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rent_contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('collection_date');        // date the collection is due
            $table->date('period_from');            // first day of covered period
            $table->date('period_to');              // last day of covered period
            $table->decimal('monthly_rent_basis', 15, 2); // avg monthly rent for this collection (may span increase boundary)
            $table->decimal('collection_amount', 15, 2);  // total for the interval in contract currency
            $table->string('currency', 10)->default('EGP');
            $table->enum('status', ['pending', 'collected', 'overdue'])->default('pending');
            $table->date('collected_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rent_collections');
        Schema::dropIfExists('rent_revenues');
        Schema::dropIfExists('rent_contracts');
    }
};