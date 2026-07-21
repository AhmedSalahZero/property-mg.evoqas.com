<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Investment Decision Tool — mirrors the real Properties structure.
 *
 * Confirmed decision (July 2026 session): RAM evaluates acquisitions at
 * both levels — a single shop/office/unit, AND a whole building, land, or
 * complex made up of several different units (retail + office mixed,
 * etc.). The original Phase 1 schema only supported a single unit. This
 * migration adds the same `nature` split Property already has
 * (unit / building / land / complex), so a prospect can now be either
 * shape — exactly like a real Property already can be.
 *
 * When nature = 'unit': purchase_price / currency / expected_monthly_rent
 * stay directly on this row, same as before.
 * When nature = 'building' | 'land' | 'complex': those three columns are
 * no longer the source of truth — the total is the SUM of the prospect's
 * child units (see investment_prospect_units, next migration), same
 * convention as a real multi-unit Property. They're made nullable here
 * rather than dropped, so a prospect can be created and then have its
 * nature changed without losing data, and so the "total purchase price"
 * accessor on the model has one consistent place to fall back to.
 *
 * Uses a raw SQL MODIFY statement rather than Laravel's Schema::change()
 * — that helper requires the doctrine/dbal package, which isn't part of
 * this project's dependencies, and adding a new package just to relax one
 * NOT NULL constraint isn't worth it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investment_prospects', function (Blueprint $table) {
            $table->string('nature')->default('unit')->after('status'); // unit | building | land | complex
        });

        // purchase_price was NOT NULL originally — relax it so a
        // building/land/complex prospect (whose total lives on its units)
        // can be saved without a redundant top-level number.
        DB::statement('ALTER TABLE investment_prospects MODIFY purchase_price DECIMAL(18,2) NULL');
    }

    public function down(): void
    {
        Schema::table('investment_prospects', function (Blueprint $table) {
            $table->dropColumn('nature');
        });

        DB::statement('ALTER TABLE investment_prospects MODIFY purchase_price DECIMAL(18,2) NOT NULL');
    }
};
