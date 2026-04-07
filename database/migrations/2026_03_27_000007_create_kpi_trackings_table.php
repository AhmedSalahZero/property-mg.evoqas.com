<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ══════════════════════════════════════════════════════════════════
//  VERO Property Management — KPI Definitions Table
//
//  Stores the KPI library — both global (company_id = null)
//  and company-specific custom KPIs (company_id = X).
//
//  Categories:
//    financial    — auto_fs KPIs pulled from Financial Statements
//    contract     — contract pipeline metrics (manual entry)
//    operational  — delivery & workforce metrics (manual entry)
//    customer     — satisfaction & retention metrics (manual entry)
//
//  Sources:
//    auto_fs  — value pulled automatically from Financial Statements
//    manual   — value entered manually by the user each period
//
//  Units:
//    currency | percentage | ratio | number
// ══════════════════════════════════════════════════════════════════

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_definitions', function (Blueprint $table) {
            $table->id();

            // null = global (visible to all companies)
            // set  = custom KPI created by that specific company
            $table->foreignId('company_id')
                  ->nullable()
                  ->constrained('companies')
                  ->onDelete('cascade');

            $table->string('name');
            $table->enum('category', [
                'financial',
                'contract',
                'operational',
                'customer',
            ])->default('financial');

            $table->enum('unit', [
                'currency',
                'percentage',
                'ratio',
                'number',
            ])->default('number');

            $table->enum('source', [
                'auto_fs',   // pulled from Financial Statements module
                'manual',    // entered manually each period
            ])->default('manual');

            // Maps to a key in the Financial Statements calculation
            // e.g. 'gross_profit', 'net_margin_pct', 'roe'
            // null for manual KPIs
            $table->string('fs_mapping')->nullable();

            $table->boolean('higher_is_better')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('description')->nullable();

            $table->timestamps();

            // Prevent duplicate KPI names per company (or globally)
            $table->unique(['company_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_definitions');
    }
};