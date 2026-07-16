<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
  public function up(): void
{
    Schema::create('sales_data', function (Blueprint $table) {
        $table->id();
        $table->foreignId('company_id')->constrained()->onDelete('cascade');
        $table->foreignId('upload_id')->constrained('sales_uploads')->onDelete('cascade');
        $table->date('date')->nullable();
        $table->string('document_type', 50)->nullable();
        $table->string('document_number', 100)->nullable();
        $table->string('country', 100)->nullable();
        $table->string('branch', 150)->nullable();
        $table->string('business_unit', 150)->nullable();
        $table->string('customer_name', 200)->nullable();
        $table->string('business_sector', 150)->nullable();
        $table->string('zone', 100)->nullable();
        $table->string('sales_channel', 150)->nullable();
        $table->string('sales_person', 150)->nullable();
        $table->string('brand', 150)->nullable();
        $table->string('service_category', 150)->nullable();
        $table->string('service_sub_category', 150)->nullable();
        $table->string('service_item', 150)->nullable();
        $table->decimal('quantity', 15, 4)->nullable();
        $table->string('measurement_unit', 50)->nullable();
        $table->decimal('price_per_unit', 15, 4)->nullable();
        $table->decimal('sales_value', 15, 2)->nullable();
        $table->decimal('cash_discount', 15, 2)->nullable();
        $table->decimal('quantity_discount', 15, 2)->nullable();
        $table->decimal('special_discount', 15, 2)->nullable();
        $table->decimal('other_discounts', 15, 2)->nullable();
        $table->decimal('net_sales_value', 15, 2)->nullable();
        $table->timestamps();

        // Indexes for fast reporting
        $table->index(['company_id', 'date']);
        $table->index(['company_id', 'brand']);
        $table->index(['company_id', 'sales_person']);
        $table->index(['company_id', 'customer_name']);
        $table->index(['company_id', 'service_category']);
        $table->index(['company_id', 'service_item']);
        $table->index(['company_id', 'business_sector']);
        
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_data');
    }
};
