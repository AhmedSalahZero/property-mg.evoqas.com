<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ══════════════════════════════════════════════════════════════════
//  VERO Property Management — Companies Table
//  Consolidated: identity + details + financials + tax + modules
//
//  enabled_modules JSON keys:
//    contract_analysis      → Sales/Contract Analysis
//    revenues_analysis      → Revenues Analysis
//    expenses_analysis      → Expenses Analysis
//    profitability          → Profitability Analysis
//    financial_statements   → Financial Statements
//    kpis                   → KPI Dashboard
//    financial_studies      → Financial Studies
//    projects_tasks         → Projects & Tasks
//    statistica             → Statistica
//    loan_calculator        → Loan Calculator
//    customer_rating        → Customer Rating
//    price_calculator       → Price Calculator
// ══════════════════════════════════════════════════════════════════

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();

            // ── Identity ─────────────────────────────────────────
            $table->string('name');
            $table->string('trade_name')->nullable();
            $table->string('logo')->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();

            // ── Parent / Subsidiary relationship ─────────────────
            $table->foreignId('parent_id')
                  ->nullable()
                  ->constrained('companies')
                  ->nullOnDelete();

            // ── Legal & Registration ──────────────────────────────
            $table->string('legal_structure')->nullable();
            // Examples: S.A.E, LLC, Branch, Holding, Partnership, Other

            $table->date('established_date')->nullable();
            $table->integer('established_year')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('tax_id')->nullable();

            // ── Contact & Location ────────────────────────────────
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->default('Egypt');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();

            // ── Financial Settings ────────────────────────────────
            $table->string('currency')->default('EGP');
            $table->enum('fiscal_year_start', [
                '01','02','03','04','05','06',
                '07','08','09','10','11','12'
            ])->default('01');

            // ── Tax Type ─────────────────────────────────────────
            // corporate_income_tax : Standard CIT applied to net profit
            // zakat                : Islamic Zakat on net assets (typically 2.5%)
            $table->enum('tax_type', [
                'corporate_income_tax',
                'zakat',
            ])->default('corporate_income_tax');

            // ── Module Subscriptions (Feature Flags) ─────────────
            // JSON array of enabled module keys.
            // Controls which sections are visible for this company.
            // Example: ["contract_analysis","revenues_analysis","kpis"]
            // null = no modules enabled (new/inactive company)
            $table->json('enabled_modules')->nullable();

            // ── Status ───────────────────────────────────────────
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};