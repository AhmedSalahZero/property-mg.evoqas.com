<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Add custom_category column
        Schema::table('project_expenses', function (Blueprint $table) {
            $table->string('custom_category')->nullable()->after('category');
        });

        // Step 2: Extend the category enum to the full list
        // MySQL requires rebuilding the enum — safest way is via raw SQL
        DB::statement("
            ALTER TABLE project_expenses
            MODIFY COLUMN category ENUM(
                'consultant',
                'freelancer',
                'legal',
                'accounting',
                'software',
                'saas_subscription',
                'hardware',
                'purchase',
                'raw_materials',
                'travel',
                'accommodation',
                'marketing',
                'training',
                'government_fees',
                'bank_charges',
                'insurance',
                'maintenance',
                'logistics',
                'other'
            ) NOT NULL DEFAULT 'other'
        ");
    }

    public function down(): void
    {
        Schema::table('project_expenses', function (Blueprint $table) {
            $table->dropColumn('custom_category');
        });

        DB::statement("
            ALTER TABLE project_expenses
            MODIFY COLUMN category ENUM(
                'consultant',
                'software',
                'purchase',
                'subscription',
                'travel',
                'other'
            ) NOT NULL DEFAULT 'other'
        ");
    }
};