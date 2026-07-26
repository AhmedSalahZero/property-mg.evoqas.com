<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Same policy now applied to Companies as was already applied to
 * Properties/PropertyUnits and Corporate/Property Expenses: deleting a
 * company must be permanent, not a soft hide. See
 * 2026_07_20_000001_hard_delete_properties_and_units.php for the original
 * reasoning.
 *
 * This migration:
 *   1. Permanently purges any company CURRENTLY sitting soft-deleted right
 *      now. Its non-super-admin users are deleted explicitly first (users
 *      are ON DELETE SET NULL from companies, not cascade, by deliberate
 *      design elsewhere in the app — see CompanyController::destroy()).
 *      Everything else — properties (and everything under them),
 *      contracts, expenses, projects, tenants, tags, Statistica series,
 *      custom reports, Investment Decision prospects, Keep-or-Sell
 *      analyses, Company Settings, etc. — is already ON DELETE CASCADE
 *      from company_id across the schema, so deleting the company row
 *      itself cleans all of that up correctly in one step.
 *   2. Drops the now-unused `deleted_at` column, so `->delete()` going
 *      forward is a genuine, permanent SQL delete.
 *
 * Irreversible by nature — down() only restores the column, not the data.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->purgeTrashedCompanies();

        Schema::table('companies', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }

    private function purgeTrashedCompanies(): void
    {
        if (!Schema::hasColumn('companies', 'deleted_at')) {
            return;
        }

        $trashedCompanyIds = DB::table('companies')
            ->whereNotNull('deleted_at')
            ->pluck('id')
            ->all();

        if (empty($trashedCompanyIds)) {
            return;
        }

        DB::table('users')
            ->whereIn('company_id', $trashedCompanyIds)
            ->where('is_super_admin', false)
            ->delete();

        // Cascades through every company_id-scoped table via the
        // database's own foreign keys.
        DB::table('companies')->whereIn('id', $trashedCompanyIds)->delete();
    }

    public function down(): void
    {
        // Schema-only reversal — the purged data itself cannot be restored.
        Schema::table('companies', function (Blueprint $table) {
            $table->softDeletes();
        });
    }
};
