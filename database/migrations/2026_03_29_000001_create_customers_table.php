<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('customer_name', 255);
            $table->string('business_sector', 150)->nullable();
            $table->enum('source', ['manual', 'imported'])->default('manual');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'customer_name']);
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};