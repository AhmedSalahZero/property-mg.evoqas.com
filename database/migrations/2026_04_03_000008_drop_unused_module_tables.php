<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop all tables belonging to removed modules:
     * - Sales Analysis
     * - Expense Analysis (Excel-based)
     * - Profitability (Excel-based)
     * - KPI Tracking
     * - Customer Credit Criteria
     * - Service Categories / Items
     */
    public function up(): void
    {
        // Disable foreign key checks so we can drop in any order
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('sales_data');
        Schema::dropIfExists('sales_uploads');
        Schema::dropIfExists('sales_field_mappings');
        Schema::dropIfExists('sales_reports');
        Schema::dropIfExists('sales_dashboard_notes');

        Schema::dropIfExists('expense_data');
        Schema::dropIfExists('expense_uploads');
        Schema::dropIfExists('expense_dashboard_notes');

        Schema::dropIfExists('profitability_dashboard_notes');
        Schema::dropIfExists('profitability_manual_inputs');
        Schema::dropIfExists('profitability_pl_mappings');

        Schema::dropIfExists('kpi_trackings');
        Schema::dropIfExists('kpi_company_preferences');
        Schema::dropIfExists('kpi_definitions');

        Schema::dropIfExists('customer_credit_criteria_settings');

        Schema::dropIfExists('service_items');
        Schema::dropIfExists('service_categories');

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Rollback is intentionally left empty.
     * These tables belonged to removed modules — there is nothing to restore.
     */
    public function down(): void
    {
        // Intentionally empty — these modules are permanently removed
    }
};