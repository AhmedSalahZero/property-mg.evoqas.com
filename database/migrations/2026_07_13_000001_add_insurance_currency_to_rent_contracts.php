<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fix for a follow-up business rule clarified after the C4 fix: the
 * insurance deposit is real cash collected from the tenant alongside the
 * rent, so it should be denominated in collection_currency — not whatever
 * currency the rent itself happens to be negotiated in (contract_currency),
 * which can differ (e.g. rent negotiated in USD, actually collected in EGP).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rent_contracts', function (Blueprint $table) {
            $table->string('insurance_currency', 10)->nullable()->after('insurance_amount');
        });
    }

    public function down(): void
    {
        Schema::table('rent_contracts', function (Blueprint $table) {
            $table->dropColumn('insurance_currency');
        });
    }
};
