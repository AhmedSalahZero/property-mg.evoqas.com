<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fix — PropertyExpenseController::destroy() and CorporateExpenseController
 * ::destroy() deleted an expense's payments (and, for corporate expenses,
 * its allocations) but never its forecasted payment schedule rows
 * (property_expense_payment_schedules / corporate_expense_payment_schedules
 * — a separate table from actual payments, used by the Cash Forecast to
 * project future cash outflows). Both `*_expense_id` foreign keys are
 * defined ON DELETE CASCADE, so if foreign key enforcement was active this
 * never actually orphaned anything — but this one-time cleanup removes any
 * schedule rows that ARE currently orphaned (expense_id no longer exists)
 * regardless of that, since an expense already deleted before this fix
 * shipped may have left its schedule rows behind. This is very likely the
 * exact "deleted expense still affecting figures" symptom reported.
 *
 * Not reversible — there's nothing meaningful to restore (the rows point at
 * expenses that no longer exist).
 */
return new class extends Migration
{
    public function up(): void
    {
        $validPropertyExpenseIds = DB::table('property_expenses')->pluck('id');
        DB::table('property_expense_payment_schedules')
            ->whereNotIn('property_expense_id', $validPropertyExpenseIds)
            ->delete();

        $validCorporateExpenseIds = DB::table('corporate_expenses')->pluck('id');
        DB::table('corporate_expense_payment_schedules')
            ->whereNotIn('corporate_expense_id', $validCorporateExpenseIds)
            ->delete();
    }

    public function down(): void
    {
        // No-op — orphaned rows pointing at already-deleted expenses aren't
        // meaningful data to restore.
    }
};
