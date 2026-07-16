<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manpower_departments', function (Blueprint $table) {
            // NULL for non-cost_of_service departments; optionally filled for cost_of_service
            $table->string('business_unit_name')->nullable()->after('cost_center');
        });
    }

    public function down(): void
    {
        Schema::table('manpower_departments', function (Blueprint $table) {
            $table->dropColumn('business_unit_name');
        });
    }
};