<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keep_or_sell_analyses', function (Blueprint $table) {
            // Anchors "Year 1" of the rolling 12-month projection. Format MM/YYYY
            // to match every other date-picker field in the app (varchar(7)).
            // Nullable so existing saved snapshots (created before this fix)
            // don't break — the frontend falls back to showing "—" for them.
            $table->string('evaluation_month', 7)->nullable()->after('holding_years');
        });
    }

    public function down(): void
    {
        Schema::table('keep_or_sell_analyses', function (Blueprint $table) {
            $table->dropColumn('evaluation_month');
        });
    }
};
