<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manpower_titles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manpower_department_id')
                ->nullable()
                ->constrained('manpower_departments')
                ->cascadeOnDelete();
            $table->string('title_name');
            $table->enum('cost_center', ['cost_of_service', 'opex', 'sales_marketing', 'admin_general']);
            // Kept for backward compatibility — always false in VERO Property Management
            $table->boolean('is_branch_title')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'is_branch_title', 'sort_order']);
            $table->index('manpower_department_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manpower_titles');
    }
};