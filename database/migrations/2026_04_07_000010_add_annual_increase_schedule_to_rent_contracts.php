<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rent_contracts', function (Blueprint $table) {
            $table->json('annual_increase_schedule')->nullable()->after('annual_increase_rate');
        });
    }

    public function down(): void
    {
        Schema::table('rent_contracts', function (Blueprint $table) {
            $table->dropColumn('annual_increase_schedule');
        });
    }
};
