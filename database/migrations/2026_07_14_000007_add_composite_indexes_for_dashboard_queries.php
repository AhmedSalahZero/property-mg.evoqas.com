<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fix for audit finding F-4.
 *
 * Every table below already has a single-column index on company_id (Laravel
 * adds one automatically for any foreignId()->constrained() column). That's
 * not what the Dashboard/report queries actually filter on, though — they
 * filter on company_id *combined with* a date range and/or a status, e.g.:
 *
 *   WHERE company_id = ? AND revenue_date BETWEEN ? AND ?
 *   WHERE company_id = ? AND collection_date BETWEEN ? AND ? AND status = ?
 *   WHERE company_id = ? AND status = 'running'
 *
 * MySQL can only make full use of one index per table per query in patterns
 * like these, so a lone company_id index still forces it to scan every row
 * for that company before applying the date/status filter. As each table
 * grows into the tens/hundreds of thousands of rows (normal after a couple
 * of years across 50 companies), that scan is the difference between an
 * instant dashboard and a slow one.
 *
 * These are additive, non-destructive index-only changes — no data is
 * touched, no application code needs to change for them to take effect
 * (Laravel's query builder already sends WHERE clauses in the order shown
 * above; MySQL's optimizer will pick up the new composite indexes on its
 * own the next time these queries run).
 *
 * ifNotExists()/hasIndex() guards let this migration run safely even if it's
 * applied more than once, or if a similarly-named index already exists on a
 * fresh install.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing('rent_revenues', ['company_id', 'revenue_date'], 'rent_rev_company_date_idx');

        $this->addIndexIfMissing('rent_collections', ['company_id', 'collection_date', 'status'], 'rent_coll_company_date_status_idx');
        $this->addIndexIfMissing('rent_collections', ['rent_contract_id', 'status'], 'rent_coll_contract_status_idx');

        $this->addIndexIfMissing('rent_contracts', ['company_id', 'status'], 'rent_contracts_company_status_idx');
        $this->addIndexIfMissing('rent_contracts', ['company_id', 'end_date'], 'rent_contracts_company_enddate_idx');

        $this->addIndexIfMissing('property_expenses', ['company_id', 'expense_date'], 'prop_exp_company_date_idx');
        $this->addIndexIfMissing('property_expenses', ['company_id', 'status'], 'prop_exp_company_status_idx');

        // corporate_expenses already has (company_id, expense_date) from its
        // creation migration — only the status composite is missing.
        $this->addIndexIfMissing('corporate_expenses', ['company_id', 'status'], 'corp_exp_company_status_idx');

        // property_installment_dues already has (property_id, due_date) and
        // (company_id, status) from its creation migration. Report queries
        // also filter by company_id + due_date directly (Installments report,
        // aging buckets by due date rather than by property), so add that
        // combination too.
        $this->addIndexIfMissing('property_installment_dues', ['company_id', 'due_date'], 'inst_dues_company_duedate_idx');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('rent_revenues', 'rent_rev_company_date_idx');
        $this->dropIndexIfExists('rent_collections', 'rent_coll_company_date_status_idx');
        $this->dropIndexIfExists('rent_collections', 'rent_coll_contract_status_idx');
        $this->dropIndexIfExists('rent_contracts', 'rent_contracts_company_status_idx');
        $this->dropIndexIfExists('rent_contracts', 'rent_contracts_company_enddate_idx');
        $this->dropIndexIfExists('property_expenses', 'prop_exp_company_date_idx');
        $this->dropIndexIfExists('property_expenses', 'prop_exp_company_status_idx');
        $this->dropIndexIfExists('corporate_expenses', 'corp_exp_company_status_idx');
        $this->dropIndexIfExists('property_installment_dues', 'inst_dues_company_duedate_idx');
    }

    private function addIndexIfMissing(string $table, array $columns, string $indexName): void
    {
        if (!Schema::hasTable($table) || $this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($columns, $indexName) {
            $t->index($columns, $indexName);
        });
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (!Schema::hasTable($table) || !$this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($indexName) {
            $t->dropIndex($indexName);
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $result = $connection->select(
            'SHOW INDEX FROM `' . $table . '` WHERE Key_name = ?',
            [$indexName]
        );

        return count($result) > 0;
    }
};
