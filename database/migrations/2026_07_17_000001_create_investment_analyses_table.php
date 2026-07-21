<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Investment Decision Tool — Phase 4: saved/shareable snapshots.
 *
 * Mirrors keep_or_sell_analyses' philosophy: simple scalar inputs get real,
 * queryable columns; bulkier structured data goes into JSON columns (that
 * table already does this for auto_flags/annual_cashflows). This tool's
 * output is inherently bigger — 3 scenarios plus Portfolio Impact and
 * Company Cash Flow Impact, not one hold-scenario — so more of the payload
 * lives in JSON here than in Keep-or-Sell's table, but the same underlying
 * rule applies: only the headline numbers actually worth listing/sorting
 * on (npv_base_case, irr_base_case) get pulled out as their own columns.
 *
 * Same share-token pattern as Keep-or-Sell too: a nullable unique token +
 * a created_at timestamp that resets whenever the token is regenerated,
 * checked for a 90-day expiry at view time (see
 * InvestmentDecisionController::shareAnalysis()) — no link works forever.
 *
 * Cascades with its parent prospect — a saved analysis is a snapshot of a
 * feasibility study, not a standalone financial record, so it has no
 * reason to outlive the prospect it was run against.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('investment_prospect_id')->constrained()->cascadeOnDelete();

            $table->string('snapshot_label', 100)->nullable();

            // ── Shared assumptions used for this run — real columns ────
            $table->string('funding_path', 30);
            $table->unsignedTinyInteger('exit_year');
            $table->decimal('discount_rate_pct', 5, 2);
            $table->decimal('corporate_tax_rate_pct', 5, 2);
            $table->decimal('selling_costs_pct', 5, 2);
            $table->string('exit_value_method', 20);
            $table->string('rent_collection_interval', 20);
            $table->decimal('inflation_rate_pct', 5, 2)->default(10);

            // ── Structured inputs/outputs — JSON ────────────────────────
            $table->json('scenario_inputs');             // the 3 scenarios' assumption inputs as submitted
            $table->json('funding_params')->nullable();  // funding-path-specific params used
            $table->json('computed_result');              // full result: 3 scenarios + portfolio_impact + cash_flow_impact

            // ── Headline outputs — Base Case only, for quick listing ────
            $table->decimal('npv_base_case', 18, 2)->nullable();
            $table->decimal('irr_base_case', 8, 4)->nullable();

            $table->text('analyst_recommendation')->nullable();

            $table->string('share_token', 64)->nullable()->unique();
            $table->timestamp('share_token_created_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_analyses');
    }
};
