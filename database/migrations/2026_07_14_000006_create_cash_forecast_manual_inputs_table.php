<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fix for audit finding H-4 — Cash Forecast's manually-entered rows
 * (Salaries, New Hirings, Other Collections, Other Payments) have always
 * lived only in the Vue page's in-memory reactive state, with no endpoint
 * anywhere that ever saved them. Every one of those rows was silently lost
 * the moment the page was refreshed, navigated away from, or reopened
 * later — a serious reliability gap for a tool whose whole point is
 * answering "what is our maximum financing need."
 *
 * One row per company (upsert on save), storing all four sections as JSON —
 * consistent with how this app already stores other structured, no-history,
 * "current planning assumptions" data (see rent_contracts.annual_increase_
 * schedule, keep_or_sell_analyses.annual_cashflows, custom_reports.filters).
 * There's no per-row payment/paid-status history to preserve here (unlike
 * rent collections or installment dues), so a full replace-on-save is the
 * right, simple model — matching the "regenerate schedule" pattern already
 * used elsewhere in this app for exactly this kind of user-input data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_forecast_manual_inputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();

            // { "2026-04": "50000", "2026-05": "52000", ... }
            $table->json('salaries')->nullable();

            // { "2026-04": [{"title": "...", "amount": "..."}], ... }
            $table->json('new_hirings')->nullable();

            // [{ "name": "...", "amounts": { "2026-04": "...", ... } }, ...]
            $table->json('other_collections')->nullable();
            $table->json('other_payments')->nullable();

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_forecast_manual_inputs');
    }
};
