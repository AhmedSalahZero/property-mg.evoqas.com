<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── Property Categories ───────────────────────────────────────────
        Schema::create('property_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('category_name', 255);
            $table->boolean('is_system')->default(false);  // true = seeded default, user cannot delete
            $table->smallInteger('sort_order')->unsigned()->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'category_name']);
        });

        // ── Property Types (children of a category) ───────────────────────
        Schema::create('property_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_category_id')->constrained('property_categories')->cascadeOnDelete();
            $table->string('type_name', 255);
            $table->smallInteger('sort_order')->unsigned()->default(0);
            $table->timestamps();

            $table->unique(['property_category_id', 'type_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_types');
        Schema::dropIfExists('property_categories');
    }
};