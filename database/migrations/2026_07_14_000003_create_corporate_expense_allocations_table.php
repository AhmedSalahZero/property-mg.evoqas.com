<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores the per-unit allocation SNAPSHOT for a corporate expense — area,
 * eligibility status, and the resulting % share and amount, all as they
 * stood at the moment the expense was saved and evaluated "as of
 * expense_date" (see CorporateExpenseAllocationService).
 *
 * Deliberately a snapshot, not a live join: if a unit's area changes later,
 * or its occupancy/delivery status changes, past allocations must NOT
 * silently recompute — they are a historical accounting record.
 *
 * property_unit_id is NULL for a standalone Unit-nature property (the
 * allocation row maps directly to the property); it is set for a child
 * unit inside a Building/Land/Complex.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corporate_expense_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('corporate_expense_id')->constrained('corporate_expenses')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('property_unit_id')->nullable()->constrained('property_units')->cascadeOnDelete();

            // Snapshot fields — captured at allocation time, never recomputed live.
            $table->string('unit_label');            // display name at time of allocation
            $table->decimal('area', 14, 4)->nullable();
            $table->string('eligibility_status', 20); // occupied | vacant | not_delivered (informational)
            $table->decimal('allocation_pct', 8, 4);   // this unit's % share of the expense
            $table->decimal('allocated_amount', 15, 2);
            $table->decimal('allocated_base_amount', 18, 2)->nullable();

            $table->timestamps();

            $table->index(['company_id', 'property_id']);
            $table->index(['company_id', 'property_unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corporate_expense_allocations');
    }
};
