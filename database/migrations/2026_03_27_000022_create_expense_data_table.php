<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('upload_id')->constrained('expense_uploads')->onDelete('cascade');
            $table->date('date')->nullable();
            $table->string('expense_category', 255)->nullable();
            $table->string('expense_sub_category', 255)->nullable();
            $table->string('expense_name', 255)->nullable();
            $table->decimal('expense_amount', 18, 2)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'date']);
            $table->index(['company_id', 'expense_category']);
            $table->index(['company_id', 'expense_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_data');
    }
};