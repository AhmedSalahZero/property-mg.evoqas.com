<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_category_id')
                ->constrained('expense_categories')
                ->cascadeOnDelete();
            $table->string('item_name');
            $table->string('coa_code', 100)->nullable();
            $table->boolean('is_employee_expense')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['expense_category_id', 'sort_order']);
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_items');
    }
};