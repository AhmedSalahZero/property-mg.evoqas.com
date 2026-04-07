<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profitability_manual_inputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->enum('period_type', ['month', 'quarter', 'semi', 'year'])->default('month');
            $table->string('period_label', 20);
            $table->decimal('da_amount', 18, 2)->default(0);
            $table->decimal('interest_amount', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            // Short custom index name to avoid MySQL 64-char limit
            $table->unique(['company_id', 'period_type', 'period_label'], 'profit_manual_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profitability_manual_inputs');
    }
};