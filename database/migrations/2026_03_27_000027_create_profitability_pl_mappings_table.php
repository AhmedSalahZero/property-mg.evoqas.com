<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profitability_pl_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('expense_category');
            $table->enum('pl_line', ['cogs', 'opex', 'da', 'interest', 'tax', 'other'])->default('opex');
            $table->timestamps();
            $table->unique(['company_id', 'expense_category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profitability_pl_mappings');
    }
};