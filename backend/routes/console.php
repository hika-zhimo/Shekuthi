<?php

use App\Console\Commands\ListingLifecycleSweep;
use App\Console\Commands\RetentionSweep;
use Illuminate\Support\Facades\Schedule;

// Retention & DPDP sweeps (M7.5). Gate behind APP_CRON_ENABLED so the
// schedule is inert until Hostinger cron availability is confirmed (Q7).
// Run manually at any time:  php artisan retention:sweep --dry-run
if (filter_var(env('APP_CRON_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN)) {
    Schedule::command(RetentionSweep::class)->dailyAt('03:00');
    Schedule::command(ListingLifecycleSweep::class)->hourly()->withoutOverlapping();
}
