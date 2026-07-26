<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds `owner_name` to `properties` and `property_units`.
     *
     * Only relevant when the (effective) ownership is `usufruct` (Right of
     * Use) or `managed` (Managed For Others) — i.e. the company doesn't own
     * the asset, so we need to record who does. Nulled out by the
     * controller for every other ownership type, same pattern already used
     * for acquisition_cost/book_value/etc. under
     * nullAssetFieldsIfHidden().
     */
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('owner_name')->nullable()->after('ownership');
        });

        Schema::table('property_units', function (Blueprint $table) {
            $table->string('owner_name')->nullable()->after('ownership');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('owner_name');
        });

        Schema::table('property_units', function (Blueprint $table) {
            $table->dropColumn('owner_name');
        });
    }
};
