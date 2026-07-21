<?php

namespace App\Console\Commands;

use App\Models\PropertyInstallmentDue;
use App\Models\RentCollection;
use App\Models\RentContract;
use Illuminate\Console\Command;

/**
 * Daily housekeeping command — this is the fix for audit finding C3.
 *
 * Fix for audit finding C3‑B: this file previously lived at
 * app/Commands/MarkOverdueRecords.php, which does not match its
 * App\Console\Commands namespace under PSR-4 autoloading, AND sits outside
 * the app/Console/Commands directory Laravel's command auto-discovery
 * scans — so 'property:mark-overdue' was never actually registered as a
 * real Artisan command, and the Schedule::command() call in
 * routes/console.php was silently a no-op. Moved here to fix that; no
 * logic below was changed.
 *
 * Nothing else in the application ever transitions a record's status based on
 * the passage of time, so without this running daily:
 *   - rent_collections / property_installment_dues sit as 'pending' forever,
 *     even years after their due date has passed — every "Overdue" KPI,
 *     aging bucket, and auto-insight in the Dashboard stays at zero forever.
 *   - rent_contracts stay 'running' forever after their end_date passes,
 *     since RentContract::autoExpire() was defined but never invoked.
 *
 * This command is intentionally simple and idempotent — running it twice in
 * a row (or missing a day and catching up later) produces the same result,
 * since it only ever moves 'pending' → 'overdue' / 'running' → 'expired' and
 * never touches rows that are already 'collected', 'paid', 'terminated', etc.
 */
class MarkOverdueRecords extends Command
{
    protected $signature = 'property:mark-overdue';

    protected $description = 'Flip pending rent collections and installment dues past their due date to overdue, and expire contracts past their end date.';

    public function handle(): int
    {
        $overdueCollections   = RentCollection::autoMarkOverdue();
        $overdueInstallments  = PropertyInstallmentDue::autoMarkOverdue();
        $expiredContracts     = RentContract::autoExpire();

        $this->info("Marked {$overdueCollections} rent collection(s) as overdue.");
        $this->info("Marked {$overdueInstallments} installment due(s) as overdue.");
        $this->info("Expired {$expiredContracts} rent contract(s) past their end date.");

        return self::SUCCESS;
    }
}