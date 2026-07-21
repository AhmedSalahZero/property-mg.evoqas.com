<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Investment Decision Tool — Phase 1.
 *
 * A "prospect" is a candidate acquisition that does NOT exist in the
 * `properties` table — confirmed scope decision (July 2026 session): this
 * tool only ever evaluates brand-new potential purchases, never a property
 * already owned (that's what Keep-or-Sell is for). If a prospect is later
 * won and actually purchased, converting it into a real `properties` row
 * is a separate, manual step outside this tool — nothing here writes to
 * `properties` automatically.
 *
 * Deliberately a hard-delete table, no soft-deletes — this is a management
 * planning tool, not an accounting ledger, same rule already applied to
 * PropertyInstallmentDue's hard-delete (see the Mark Unpaid feature).
 * Saved analyses against a prospect (investment_analyses, Phase 4) cascade
 * delete with it, since they're draft feasibility studies, not committed
 * financial records.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_prospects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // ── Identity ─────────────────────────────────────────────
            $table->string('prospect_name');
            $table->string('status')->default('evaluating'); // evaluating | pursuing | passed | acquired — informational only, nothing automated on change

            // ── Location (same shape as properties, kept optional) ────
            $table->string('country')->default('Egypt');
            $table->string('governorate')->nullable();
            $table->string('province')->nullable();
            $table->string('location')->nullable();

            // ── Category & Type (reuse existing company settings) ─────
            $table->foreignId('property_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('property_type_id')->nullable()->constrained()->nullOnDelete();

            // ── Physical ────────────────────────────────────────────
            $table->decimal('area', 15, 4)->nullable();
            $table->string('unit_of_measurement')->nullable();

            // ── Deal economics ──────────────────────────────────────
            $table->decimal('purchase_price', 18, 2);
            $table->string('currency', 10)->default('EGP');
            $table->decimal('expected_monthly_rent', 18, 2)->nullable(); // full potential rent once fully leased/stabilized

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_prospects');
    }
};
