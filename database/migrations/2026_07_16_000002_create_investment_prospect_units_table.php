<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Investment Decision Tool — child units for Building / Land / Complex
 * prospects. Direct mirror of `property_units` (the real portfolio's
 * child-unit table), simplified to only what a feasibility study needs —
 * no ownership, no depreciation, no market value history, since none of
 * that exists yet for something not yet purchased.
 *
 * A Complex acquisition can genuinely mix categories (retail + office +
 * medical in one purchase), so each unit carries its own category/type,
 * area, price, and expected rent — exactly like a real property_unit row.
 *
 * Hard-delete, no soft-deletes, cascades with its parent prospect — same
 * rule as investment_prospects itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_prospect_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_prospect_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete(); // denormalized for direct company-scoped queries, same pattern property_units uses

            $table->string('unit_name');
            $table->string('slot_type')->default('built_unit'); // built_unit | land_slot — mirrors property_units, land slots skip rent/category entirely

            $table->foreignId('property_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('property_type_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('area', 15, 4)->nullable();
            $table->string('unit_of_measurement')->nullable();

            $table->decimal('purchase_price', 18, 2)->default(0);
            $table->string('currency', 10)->default('EGP');
            $table->decimal('expected_monthly_rent', 18, 2)->nullable();

            $table->integer('sort_order')->default(0);

            $table->timestamps();

            // Explicit short name — the auto-generated one
            // ("investment_prospect_units_investment_prospect_id_sort_order_index")
            // is 68 characters, over MySQL's 64-character identifier limit.
            $table->index(['investment_prospect_id', 'sort_order'], 'ipu_prospect_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_prospect_units');
    }
};