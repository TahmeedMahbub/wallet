<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Run `php artisan schedule:run` every minute (cron) for these to fire.
Schedule::command('wallet:backup-database')->dailyAt('02:30')->onOneServer()->withoutOverlapping()->runInBackground();
Schedule::command('wallet:prune-keys')->hourly()->onOneServer();
Schedule::command('auth:clear-resets')->daily()->onOneServer();
Schedule::command('queue:prune-failed --hours=168')->daily()->onOneServer();
