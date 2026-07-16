<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->date('subscription_start_date')->nullable()->after('is_active');
            $table->unsignedInteger('subscription_duration_months')->nullable()->after('subscription_start_date');
            $table->date('subscription_end_date')->nullable()->after('subscription_duration_months');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'subscription_start_date',
                'subscription_duration_months',
                'subscription_end_date',
            ]);
        });
    }
};

