<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ═══════════════════════════════════════════════════════════════
        // PROPERTIES — parent record for all natures
        // nature: unit | building | land | complex
        // A "unit" nature stores all financials directly here.
        // building / land / complex store location/ownership only;
        // their financials live in property_units.
        // ═══════════════════════════════════════════════════════════════
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            // ── Identity ─────────────────────────────────────────────
            $table->enum('nature', ['unit', 'building', 'land', 'complex']);
            $table->string('property_name');
            $table->string('property_code')->nullable();          // unique per company — enforced below
            $table->enum('ownership', [
                'fully_owned',
                'installments',
                'usufruct',
                'managed',
            ]);

            // ── Location ─────────────────────────────────────────────
            $table->string('country')->default('Egypt');
            $table->string('governorate')->nullable();
            $table->string('province')->nullable();
            $table->string('location')->nullable();               // free-text address / landmark

            // ── Category & Type (unit-nature only; nullable for parent records) ──
            $table->foreignId('property_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('property_type_id')->nullable()->constrained()->nullOnDelete();

            // ── Physical ──────────────────────────────────────────────
            $table->decimal('area', 15, 4)->nullable();
            $table->string('unit_of_measurement')->nullable();    // m2 / ft2 / feddan / etc.

            // ── Financials (unit-nature only; null for parent natures) ──
            $table->decimal('acquisition_cost', 18, 2)->nullable();
            $table->string('currency', 10)->default('EGP');
            $table->string('acquisition_date', 7)->nullable();    // stored as "MM/YYYY"
            $table->decimal('book_value', 18, 2)->nullable();
            $table->decimal('accumulated_depreciation', 18, 2)->nullable();
            $table->decimal('monthly_depreciation', 18, 2)->nullable();
            $table->unsignedSmallInteger('depreciation_duration_months')->nullable(); // 0 = no depreciation (land)

            // ── Status ────────────────────────────────────────────────
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            // ── Indexes ───────────────────────────────────────────────
            $table->index(['company_id', 'nature']);
            $table->unique(['company_id', 'property_code']); // code unique per company
        });

        // ═══════════════════════════════════════════════════════════════
        // PROPERTY UNITS — child units inside building / land / complex
        // Also used for land slots (slot_type = 'land_slot' → no depreciation)
        // ═══════════════════════════════════════════════════════════════
        Schema::create('property_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete(); // parent

            // ── Slot type (land parent only) ──────────────────────────
            // 'built_unit' = has depreciation  |  'land_slot' = no depreciation
            $table->enum('slot_type', ['built_unit', 'land_slot'])->default('built_unit');

            // ── Identity ─────────────────────────────────────────────
            $table->string('unit_name');
            $table->string('unit_code')->nullable();
            $table->enum('ownership', [
                'fully_owned',
                'installments',
                'usufruct',
                'managed',
            ])->nullable();                                       // inherits parent if null

            // ── Location (overrides parent if filled) ─────────────────
            $table->string('location')->nullable();

            // ── Category & Type ───────────────────────────────────────
            $table->foreignId('property_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('property_type_id')->nullable()->constrained()->nullOnDelete();

            // ── Physical ──────────────────────────────────────────────
            $table->decimal('area', 15, 4)->nullable();
            $table->string('unit_of_measurement')->nullable();

            // ── Financials ────────────────────────────────────────────
            $table->decimal('acquisition_cost', 18, 2)->nullable();
            $table->string('currency', 10)->default('EGP');
            $table->string('acquisition_date', 7)->nullable();    // "MM/YYYY"
            $table->decimal('book_value', 18, 2)->nullable();

            // depreciation — null / 0 for land_slot rows
            $table->decimal('accumulated_depreciation', 18, 2)->nullable();
            $table->decimal('monthly_depreciation', 18, 2)->nullable();
            $table->unsignedSmallInteger('depreciation_duration_months')->nullable();

            // ── Status ────────────────────────────────────────────────
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['property_id', 'slot_type']);
        });

        // ═══════════════════════════════════════════════════════════════
        // PROPERTY MARKET VALUES — repeater log per property or unit
        // parent_type: 'property' | 'property_unit'
        // ═══════════════════════════════════════════════════════════════
        Schema::create('property_market_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            // Polymorphic-style but simple: one of the two will be filled
            $table->foreignId('property_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('property_unit_id')->nullable()->constrained('property_units')->cascadeOnDelete();

            $table->decimal('market_value', 18, 2);
            $table->string('value_date', 7);                      // "MM/YYYY"
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['property_id']);
            $table->index(['property_unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_market_values');
        Schema::dropIfExists('property_units');
        Schema::dropIfExists('properties');
    }
};