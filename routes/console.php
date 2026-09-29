<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('stumps:sync-health', function (): int {
    $since = now('UTC')->subMinutes((int) config('stumps.sync_monitor_window_minutes'));
    $counts = DB::table('sync_health_events')->where('created_at', '>=', $since)
        ->selectRaw('category, count(*) as aggregate')->groupBy('category')->pluck('aggregate', 'category');
    $context = [
        'window_minutes' => (int) config('stumps.sync_monitor_window_minutes'),
        'accepted' => (int) ($counts['accepted'] ?? 0),
        'conflicts' => (int) ($counts['conflict'] ?? 0),
        'rejections' => (int) ($counts['rejected'] ?? 0),
    ];
    $warning = $context['conflicts'] >= (int) config('stumps.sync_conflict_warning_threshold')
        || $context['rejections'] >= (int) config('stumps.sync_rejection_warning_threshold');
    Log::log($warning ? 'warning' : 'info', 'sync_health_window', $context);
    $this->line(json_encode($context, JSON_THROW_ON_ERROR));

    return $warning ? self::FAILURE : self::SUCCESS;
})->purpose('Report conflict and rejection rates without logging event payloads.');

Schedule::command('sanctum:prune-expired --hours=24')->dailyAt('02:10')->withoutOverlapping();
Schedule::command('stumps:sync-health')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=168')->dailyAt('02:20')->withoutOverlapping();
