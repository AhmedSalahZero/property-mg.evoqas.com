<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ══════════════════════════════════════════════════════════════════
//  VERO Property Management — KPI Trackings Table
//
//  Stores the actual & target values entered per company,
//  per KPI definition, per period.
//
//  Period types:  monthly | quarterly | annual
//  Period labels: '2026-03' | '2026-Q1' | '2026'
//
//  auto_synced = true means the value was pulled automatically
//  from the Financial Statements module (auto_fs KPIs).
//  auto_synced = false means it was entered manually.
// ══════════════════════════════════════════════════════════════════

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_trackings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                  ->constrained('companies')
                  ->onDelete('cascade');

            $table->foreignId('kpi_definition_id')
                  ->constrained('kpi_definitions')
                  ->onDelete('cascade');

            // Period
            $table->enum('period_type', ['monthly', 'quarterly', 'annual'])
                  ->default('monthly');
            $table->string('period_label'); // e.g. '2026-03', '2026-Q1', '2026'

            // Values
            $table->decimal('target', 20, 4)->nullable();
            $table->decimal('actual', 20, 4)->nullable();

            // Stored computed values (also available as model accessors)
            $table->decimal('variance', 20, 4)->nullable();
            $table->decimal('variance_percent', 10, 4)->nullable();

            // Status: on_track | watch | at_risk | no_data
            $table->string('status')->default('no_data');

            $table->text('notes')->nullable();

            // Who entered it and how
            $table->foreignId('entered_by')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null');

            $table->boolean('auto_synced')->default(false);

            $table->timestamps();

            // One tracking row per company + KPI + period — no duplicates
            $table->unique(
                ['company_id', 'kpi_definition_id', 'period_type', 'period_label'],
                'kpi_trackings_unique'
            );

            // Index for fast dashboard queries
            $table->index(['company_id', 'period_type', 'period_label'], 'kpi_trackings_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_trackings');
    }
};