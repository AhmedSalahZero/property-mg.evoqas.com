<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two small company-scoped "master list" tables so Province/District
     * and Owner Name can be driven from a managed dropdown (add new /
     * rename / delete) instead of free-text typing — same idea as the
     * existing `tags` table, but a single-select list rather than a
     * many-to-many tag set.
     *
     * Deliberately NOT foreign keys on `properties`/`property_units` —
     * those two tables keep storing `province`/`owner_name` as plain
     * strings (already the case before this migration). These lookup
     * tables only back the dropdown + "manage" UI; renaming an entry here
     * also mass-updates any already-saved properties/units whose stored
     * string matches the old name (see ProvinceController::update() /
     * PropertyOwnerController::update()), so the two stay in sync without
     * a hard FK relationship or a data migration on the properties tables.
     */
    public function up(): void
    {
        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });

        Schema::create('property_owners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_owners');
        Schema::dropIfExists('provinces');
    }
};
