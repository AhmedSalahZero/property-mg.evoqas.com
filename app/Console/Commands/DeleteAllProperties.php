<?php

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * php artisan properties:delete-all {company_id}
 *
 * Permanently deletes every property belonging to a company, and
 * everything that depends on those properties: child units, rent
 * contracts (and their revenues/collections), installment plans/dues,
 * property expenses (and their payments + forecasted payment schedules),
 * market values, tag links, Keep-or-Sell analyses, and corporate expense
 * allocation snapshots.
 *
 * This mirrors exactly what PropertyController::destroy() does for a
 * single property (see that method's comments), just applied in bulk to
 * every property for the given company in one transaction, using direct
 * query-builder deletes instead of one-by-one Eloquent model loops for
 * speed on larger datasets.
 *
 * Shows a summary of what will be deleted and asks for explicit
 * confirmation before touching anything. This is irreversible — always
 * take a database backup first if there's any doubt.
 */
class DeleteAllProperties extends Command
{
    protected $signature = 'properties:delete-all {company_id : The ID of the company whose properties should be permanently deleted}';

    protected $description = 'Permanently deletes ALL properties for a company, plus every contract, revenue, collection, installment, expense, and related record underneath them.';

    public function handle(): int
    {
        $companyId = (int) $this->argument('company_id');
        $company = Company::find($companyId);

        if (!$company) {
            $this->error("No company found with id {$companyId}.");
            return self::FAILURE;
        }

        $propertyIds = DB::table('properties')->where('company_id', $companyId)->pluck('id')->all();

        if (empty($propertyIds)) {
            $this->info("Company \"{$company->name}\" (id {$companyId}) has no properties. Nothing to delete.");
            return self::SUCCESS;
        }

        $unitIds = DB::table('property_units')->whereIn('property_id', $propertyIds)->pluck('id')->all();

        $contractIds = DB::table('rent_contracts')
            ->where(function ($q) use ($propertyIds, $unitIds) {
                $q->whereIn('property_id', $propertyIds);
                if (!empty($unitIds)) $q->orWhereIn('property_unit_id', $unitIds);
            })
            ->pluck('id')->all();

        $expenseIds = DB::table('property_expenses')->whereIn('property_id', $propertyIds)->pluck('id')->all();

        // ── Show exactly what's about to be deleted, then ask ──────────
        $this->warn("This will PERMANENTLY delete, for company \"{$company->name}\" (id {$companyId}):");
        $this->line('  - '.count($propertyIds).' properties');
        $this->line('  - '.count($unitIds).' child units (inside buildings/land/complexes)');
        $this->line('  - '.count($contractIds).' rent contracts, plus all their revenue and collection rows');
        $this->line('  - '.count($expenseIds).' property expenses, plus all their payments and forecasted payment schedules');
        $this->line('  - every related installment plan/due, market value entry, tag link, Keep-or-Sell analysis, and corporate expense allocation tied to these properties');
        $this->newLine();
        $this->error('This CANNOT be undone. Make sure you have a database backup if there is any doubt.');
        $this->newLine();

        if (!$this->confirm('Are you sure you want to permanently delete all of this?', false)) {
            $this->info('Cancelled — nothing was deleted.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($propertyIds, $unitIds, $contractIds, $expenseIds) {
            // Rent contracts — revenues/collections first.
            if (!empty($contractIds)) {
                DB::table('rent_revenues')->whereIn('rent_contract_id', $contractIds)->delete();
                DB::table('rent_collections')->whereIn('rent_contract_id', $contractIds)->delete();
                DB::table('rent_contracts')->whereIn('id', $contractIds)->delete();
            }

            // Installments.
            DB::table('property_installment_dues')->whereIn('property_id', $propertyIds)->delete();
            DB::table('property_installment_plans')->whereIn('property_id', $propertyIds)->delete();

            // Property expenses — payments and forecasted schedule first.
            if (!empty($expenseIds)) {
                DB::table('property_expense_payments')->whereIn('property_expense_id', $expenseIds)->delete();
                DB::table('property_expense_payment_schedules')->whereIn('property_expense_id', $expenseIds)->delete();
                DB::table('property_expenses')->whereIn('id', $expenseIds)->delete();
            }

            // Market values, tags, Keep-or-Sell, corporate allocations.
            DB::table('property_market_values')
                ->where(function ($q) use ($propertyIds, $unitIds) {
                    $q->whereIn('property_id', $propertyIds);
                    if (!empty($unitIds)) $q->orWhereIn('property_unit_id', $unitIds);
                })
                ->delete();

            DB::table('property_tag')->whereIn('property_id', $propertyIds)->delete();

            DB::table('keep_or_sell_analyses')
                ->where(function ($q) use ($propertyIds, $unitIds) {
                    $q->whereIn('property_id', $propertyIds);
                    if (!empty($unitIds)) $q->orWhereIn('property_unit_id', $unitIds);
                })
                ->delete();

            DB::table('corporate_expense_allocations')
                ->where(function ($q) use ($propertyIds, $unitIds) {
                    $q->whereIn('property_id', $propertyIds);
                    if (!empty($unitIds)) $q->orWhereIn('property_unit_id', $unitIds);
                })
                ->delete();

            // Units, then the properties themselves.
            DB::table('property_units')->whereIn('property_id', $propertyIds)->delete();
            DB::table('properties')->whereIn('id', $propertyIds)->delete();
        });

        $this->info('Done — all properties and every related record for this company have been permanently deleted.');
        return self::SUCCESS;
    }
}
