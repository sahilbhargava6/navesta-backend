<?php

use Illuminate\Support\Facades\Schedule;

// Run with `php artisan schedule:work` (or a cron calling `schedule:run` every minute).
// Only one scheduler may run across all servers (onOneServer needs a shared cache: Redis).
$ist = config('navesta.timezone');

// Debt history: queue this month's snapshot for every customer, in batches
Schedule::command('navesta:capture-snapshots')->dailyAt('00:30')->timezone($ist)->withoutOverlapping()->onOneServer();

// WhatsApp reminders for payments due in a few days
Schedule::command('navesta:send-due-reminders')->dailyAt('09:00')->timezone($ist)->withoutOverlapping()->onOneServer();

// Encrypted database backups, old-backup cleanup, an alert if the latest backup is stale,
// and a weekly test restore
Schedule::command('backup:clean')->dailyAt('01:00')->timezone($ist)->onOneServer();
Schedule::command('backup:run --only-db')->dailyAt('01:30')->timezone($ist)->withoutOverlapping()->onOneServer();
Schedule::command('backup:monitor')->dailyAt('03:00')->timezone($ist)->onOneServer();
Schedule::command('navesta:verify-backup')->weeklyOn(0, '04:00')->timezone($ist)->withoutOverlapping()->onOneServer();

// Data retention: audit entries and statements past their retention period
Schedule::command('model:prune')->dailyAt('02:00')->timezone($ist)->onOneServer();

// Alert (via the error tracker) when a queue backs up
Schedule::command('queue:monitor '.collect(['notifications', 'scans', 'default'])
    ->map(fn ($q) => config('queue.default').':'.$q)->implode(',').' --max=200')
    ->everyFiveMinutes()
    ->onOneServer();

// Housekeeping
Schedule::command('sanctum:prune-expired --hours=24')->daily()->onOneServer();
Schedule::command('queue:prune-failed --hours=720')->daily()->onOneServer();
