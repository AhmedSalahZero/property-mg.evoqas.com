<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keep_or_sell_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // ── Subject ─────────────────────────────────────────────────
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('property_unit_id')->nullable()->constrained('property_units')->nullOnDelete();
            $table->string('snapshot_label', 100)->nullable(); // e.g. "Q1 2026 Review"

            // ── Market & Sell Scenario ───────────────────────────────────
            $table->decimal('market_value', 18, 2)->nullable();          // pulled or overridden
            $table->decimal('selling_costs_pct', 5, 2)->default(0);      // % of market value
            $table->decimal('net_sale_proceeds', 18, 2)->nullable();      // computed & stored

            // ── Hold Assumptions ─────────────────────────────────────────
            $table->unsignedSmallInteger('holding_years')->default(5);
            $table->decimal('rent_growth_rate_pct', 5, 2)->default(0);   // % per year beyond contract
            $table->decimal('other_opex_pct', 5, 2)->default(0);          // % of gross revenue
            $table->decimal('corporate_tax_rate_pct', 5, 2)->default(0); // % applied on annual net income
            $table->decimal('discount_rate_pct', 5, 2)->default(10);     // WACC / required return
            $table->decimal('exit_cap_rate_pct', 5, 2)->default(7);      // for terminal value

            // ── Computed Outputs ─────────────────────────────────────────
            $table->decimal('npv_hold', 18, 2)->nullable();
            $table->decimal('irr_hold', 8, 4)->nullable();               // stored as percentage e.g. 12.3456
            $table->decimal('terminal_value', 18, 2)->nullable();
            $table->string('auto_recommendation', 20)->nullable();        // 'keep' | 'sell' | 'neutral'
            $table->json('auto_flags')->nullable();                        // array of warning strings
            $table->json('annual_cashflows')->nullable();                  // full year-by-year table (JSON)

            // ── User Recommendation ──────────────────────────────────────
            $table->text('analyst_recommendation')->nullable();

            // ── Share Token ──────────────────────────────────────────────
            $table->string('share_token', 64)->nullable()->unique();
            $table->timestamp('share_token_created_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keep_or_sell_analyses');
    }
};