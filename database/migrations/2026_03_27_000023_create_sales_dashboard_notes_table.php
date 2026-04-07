<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::create('sales_dashboard_notes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('company_id')->constrained()->cascadeOnDelete();
        $table->date('date_from');
        $table->date('date_to');
        $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
        $table->longText('note');
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('sales_dashboard_notes');
}

};