<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_category_id')->constrained('expense_categories')->cascadeOnDelete();
            $table->foreignId('expense_item_id')->constrained('expense_items')->cascadeOnDelete();
            $table->date('expense_date');
            $table->decimal('expense_amount', 15, 2);
            $table->string('currency', 10)->default('EGP');
            $table->decimal('fx_rate', 12, 6)->nullable(); // null = same as company currency
            $table->text('notes')->nullable();
            $table->enum('status', ['unpaid', 'partially_paid', 'fully_paid'])->default('unpaid');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_expenses');
    }
};