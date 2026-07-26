<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * VERO is a management system, not an accounting/archival one — deleting a
 * property is meant to be permanent. Property/PropertyUnit were soft-deleted
 * (Laravel's SoftDeletes: `delete()` only stamped `deleted_at` and hid the
 * row from normal queries) so nothing was ever actually removed, and every
 * dependent row (contracts, revenues, collections, installments, expenses,
 * market values, etc.) stayed fully intact and kept showing up in reports
 * like the Cash Forecast.
 *
 * This migration does two things:
 *   1. Permanently purges any property/unit that is CURRENTLY sitting
 *      soft-deleted right now, cascading through every dependent table
 *      explicitly (not relying solely on the database's own
 *      ON DELETE CASCADE foreign keys, in case foreign key enforcement
 *      isn't active in a given environment). Without this step, simply
 *      dropping the `deleted_at` column below would make these properties
 *      reappear as normal active properties again, since nothing would
 *      mark them as gone anymore.
 *   2. Drops the now-unused `deleted_at` column from both tables, so
 *      `->delete()` going forward is a genuine, permanent SQL delete
 *      (see PropertyController::destroy(), which was updated in the same
 *      change to explicitly cascade-delete everything a property owns).
 *
 * Irreversible by nature — down() only restores the columns, not the data.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->purgeTrashedProperties();

        Schema::table('properties', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('property_units', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }

    private function purgeTrashedProperties(): void
    {
        if (!Schema::hasColumn('properties', 'deleted_at')) {
            return;
        }

        $trashedPropertyIds = DB::table('properties')
            ->whereNotNull('deleted_at')
            ->pluck('id')
            ->all();

        // Units belonging to a trashed property, plus any unit that was
        // (unusually) trashed on its own without its parent property being
        // trashed too.
        $unitIdsUnderTrashedProps = DB::table('property_units')
            ->whereIn('property_id', $trashedPropertyIds)
            ->pluck('id')
            ->all();

        $independentlyTrashedUnitIds = Schema::hasColumn('property_units', 'deleted_at')
            ? DB::table('property_units')->whereNotNull('deleted_at')->pluck('id')->all()
            : [];

        $trashedUnitIds = array_values(array_unique(array_merge($unitIdsUnderTrashedProps, $independentlyTrashedUnitIds)));

        if (empty($trashedPropertyIds) && empty($trashedUnitIds)) {
            return; // nothing to purge
        }

        // ── Rent contracts (property-level or unit-level) ──────────────
        $contractIds = DB::table('rent_contracts')
            ->where(function ($q) use ($trashedPropertyIds, $trashedUnitIds) {
                if (!empty($trashedPropertyIds)) $q->orWhereIn('property_id', $trashedPropertyIds);
                if (!empty($trashedUnitIds))     $q->orWhereIn('property_unit_id', $trashedUnitIds);
            })
            ->pluck('id')
            ->all();

        if (!empty($contractIds)) {
            DB::table('rent_revenues')->whereIn('rent_contract_id', $contractIds)->delete();
            DB::table('rent_collections')->whereIn('rent_contract_id', $contractIds)->delete();
            DB::table('rent_contracts')->whereIn('id', $contractIds)->delete();
        }

        // ── Installments ─────────────────────────────────────────────
        if (!empty($trashedPropertyIds)) {
            DB::table('property_installment_dues')->whereIn('property_id', $trashedPropertyIds)->delete();
            DB::table('property_installment_plans')->whereIn('property_id', $trashedPropertyIds)->delete();
        }

        // ── Property expenses (payments + forecast schedule first) ─────
        if (!empty($trashedPropertyIds)) {
            $expenseIds = DB::table('property_expenses')
                ->whereIn('property_id', $trashedPropertyIds)
                ->pluck('id')
                ->all();

            if (!empty($expenseIds)) {
                DB::table('property_expense_payments')->whereIn('property_expense_id', $expenseIds)->delete();
                DB::table('property_expense_payment_schedules')->whereIn('property_expense_id', $expenseIds)->delete();
                DB::table('property_expenses')->whereIn('id', $expenseIds)->delete();
            }
        }

        // ── Market values, tags, Keep-or-Sell, corporate allocations ────
        DB::table('property_market_values')
            ->where(function ($q) use ($trashedPropertyIds, $trashedUnitIds) {
                if (!empty($trashedPropertyIds)) $q->orWhereIn('property_id', $trashedPropertyIds);
                if (!empty($trashedUnitIds))     $q->orWhereIn('property_unit_id', $trashedUnitIds);
            })
            ->delete();

        if (!empty($trashedPropertyIds)) {
            DB::table('property_tag')->whereIn('property_id', $trashedPropertyIds)->delete();
        }

        DB::table('keep_or_sell_analyses')
            ->where(function ($q) use ($trashedPropertyIds, $trashedUnitIds) {
                if (!empty($trashedPropertyIds)) $q->orWhereIn('property_id', $trashedPropertyIds);
                if (!empty($trashedUnitIds))     $q->orWhereIn('property_unit_id', $trashedUnitIds);
            })
            ->delete();

        DB::table('corporate_expense_allocations')
            ->where(function ($q) use ($trashedPropertyIds, $trashedUnitIds) {
                if (!empty($trashedPropertyIds)) $q->orWhereIn('property_id', $trashedPropertyIds);
                if (!empty($trashedUnitIds))     $q->orWhereIn('property_unit_id', $trashedUnitIds);
            })
            ->delete();

        // ── Units, then the properties themselves ───────────────────────
        if (!empty($trashedUnitIds)) {
            DB::table('property_units')->whereIn('id', $trashedUnitIds)->delete();
        }
        if (!empty($trashedPropertyIds)) {
            DB::table('properties')->whereIn('id', $trashedPropertyIds)->delete();
        }
    }

    public function down(): void
    {
        // Schema-only reversal — the purged data itself cannot be restored.
        Schema::table('properties', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('property_units', function (Blueprint $table) {
            $table->softDeletes();
        });
    }
};
