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
    Schema::create('sales_reports', function (Blueprint $table) {
        $table->id();
        $table->foreignId('company_id')->constrained()->onDelete('cascade');
        $table->string('name', 200);
        $table->enum('report_type', [
            'single_dimension',
            'matrix',
            'ranking',
            'customer_nature',
            'period_comparison',
            'trend'
        ]);
        $table->json('config')->nullable(); // stores dimensions, filters, metrics
        $table->boolean('is_system')->default(false); // true = built-in, false = custom
        $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
        $table->timestamps();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_reports');
    }
};
