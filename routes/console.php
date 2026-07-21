<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Fix for audit finding C3 — nothing else in the app ever flips a pending
// collection/installment to overdue, or expires a contract past its end date.
// Runs once daily just after midnight.
//
// IMPORTANT: this schedule only fires if the server actually has Laravel's
// scheduler running. Confirm the Herd site (and, later, production) has a
// system cron entry calling `php artisan schedule:run` every minute — e.g.:
//   * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
// Without that cron entry, this schedule definition alone does nothing.
Schedule::command('property:mark-overdue')->dailyAt('00:05');
