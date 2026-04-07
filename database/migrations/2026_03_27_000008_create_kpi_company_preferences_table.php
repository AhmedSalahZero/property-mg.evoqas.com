<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ══════════════════════════════════════════════════════════════════
//  VERO Property Management — KPI Company Preferences Table
//
//  Purpose:
//    Global KPIs (company_id IS NULL on kpi_definitions) are shared
//    across ALL companies. We never mutate the global row.
//    Instead, when a company hides a global KPI, we write a row
//    here with is_hidden = true — scoped only to that company.
//
//    This means Company A hiding "Net Margin" has zero effect
//    on Company B's KPI list.
//
//    Custom KPIs (company_id = X on kpi_definitions) are toggled
//    directly on the definition — this table is not used for them.
// ══════════════════════════════════════════════════════════════════

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_company_preferences', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                  ->constrained('companies')
                  ->onDelete('cascade');

            $table->foreignId('kpi_definition_id')
                  ->constrained('kpi_definitions')
                  ->onDelete('cascade');

            // true  = company has hidden this global KPI
            // false = company has explicitly re-enabled it (default)
            $table->boolean('is_hidden')->default(false);

            $table->timestamps();

            // One preference row per company per KPI — no duplicates
            $table->unique(
                ['company_id', 'kpi_definition_id'],
                'kpi_prefs_company_definition_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_company_preferences');
    }
};