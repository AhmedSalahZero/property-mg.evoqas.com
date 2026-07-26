<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 of the "Record Sale" feature (confirmed July 2026) — the app had
 * no way to record an actual property/unit sale. The only removal path was
 * a hard delete (PropertyController::destroy()), which wipes every
 * dependent record — completely wrong for a sale, where the historical
 * revenue/expense/contract trail needs to stay intact for reporting.
 *
 * Design (confirmed with the client):
 *   - Sale can happen at 3 levels: a standalone Unit, a single child unit
 *     inside a Building/Land/Complex, or the ENTIRE Building/Land/Complex
 *     in one transaction (price entered as one lump sum, divided by total
 *     area to get a price/sqm, then allocated to each unit by its own
 *     area — same "last unit absorbs rounding" pattern already used by
 *     CorporateExpenseAllocationService).
 *   - One row here always represents ONE unit's sale. A whole-property
 *     sale produces one row per child unit, linked by a shared
 *     `sale_batch_id` so they can still be viewed/undone as one
 *     transaction.
 *   - Payment can be cash or installments. Realized gain/loss is booked
 *     in full on the sale date either way (confirmed) — Phase 2 will add
 *     the actual installment-receivable schedule (property_sale_dues,
 *     mirroring property_installment_dues) and wire it into Cash Forecast.
 *   - `sold_at` on properties/property_units is a fast denormalized marker
 *     (drives the "Sold" tab and excludes the unit from Occupancy/NOI);
 *     the property_sales row is the full record (price, buyer, gain/loss).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            // Groups multiple rows sold together in one whole-property
            // transaction. Null for a standalone/individual single-unit sale.
            $table->uuid('sale_batch_id')->nullable();

            // property_id is always set: for a standalone Unit sale, this
            // IS the sold property; for a child-unit sale, this is the
            // PARENT Building/Land/Complex (for filtering/grouping).
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_unit_id')->nullable()->constrained('property_units')->cascadeOnDelete();

            $table->date('sale_date');
            $table->string('buyer_name')->nullable();

            // Snapshots — area/price basis at the moment of sale, so the
            // record stays accurate even if the unit's own area is edited
            // later (it shouldn't be, once sold, but never trust that).
            $table->decimal('area_at_sale', 15, 4)->nullable();
            $table->decimal('price_per_sqm', 18, 2)->nullable(); // only set for whole-property sales

            $table->decimal('sale_price', 18, 2);               // this unit's price (own currency)
            $table->string('currency', 10)->default('EGP');
            $table->decimal('selling_costs_pct', 5, 2)->default(0);
            $table->decimal('net_sale_proceeds', 18, 2);         // sale_price * (1 - selling_costs_pct/100), own currency

            // Base-currency conversion — same convention as every other
            // money-bearing table (CurrencyConversionService::convert()).
            $table->decimal('net_sale_proceeds_base_amount', 18, 2)->nullable();
            $table->decimal('book_value_base_amount_at_sale', 18, 2)->nullable(); // snapshot of the unit's book value, base currency
            $table->decimal('realized_gain_loss', 18, 2)->nullable();            // net_sale_proceeds_base_amount - book_value_base_amount_at_sale
            $table->string('base_currency', 10)->nullable();
            $table->decimal('fx_rate_used', 18, 6)->nullable();

            $table->enum('payment_method', ['cash', 'installments'])->default('cash');
            // Phase 1: basic terms captured as a note only. Phase 2 replaces
            // this with a real due-date schedule (property_sale_dues).
            $table->text('payment_terms_notes')->nullable();

            // The running contract this sale terminated, if any — kept for
            // traceability even though the contract's own status/terminated_date
            // already reflect the termination.
            $table->foreignId('rent_contract_id')->nullable()->constrained('rent_contracts')->nullOnDelete();

            // Any collection tranche that straddled the sale date (started
            // before, ended after) is left untouched rather than guessed at
            // — recorded here so it surfaces as a manual-review flag.
            $table->text('warnings')->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'sale_date']);
            $table->index('sale_batch_id');
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->date('sold_at')->nullable()->after('is_active');
        });

        Schema::table('property_units', function (Blueprint $table) {
            $table->date('sold_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('sold_at');
        });

        Schema::table('property_units', function (Blueprint $table) {
            $table->dropColumn('sold_at');
        });

        Schema::dropIfExists('property_sales');
    }
};
