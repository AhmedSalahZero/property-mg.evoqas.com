<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->enum('data_source', [
                'rent_collections',
                'rent_revenues',
                'property_expenses',
                'installment_dues',
            ]);
            $table->json('dimensions');   // ordered array: ['governorate','property_type','month']
            $table->json('measures');     // array: ['amount_collected','collection_count']
            $table->json('filters');      // {start_date, end_date, governorate[], property_id[], ...}
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_reports');
    }
};
