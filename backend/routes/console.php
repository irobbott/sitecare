<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\Website;
use App\Jobs\CheckWebsite;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('sitecare:monitor-due', function () {
    $due=Website::query()->where('status','active')->where(function($query){$query->whereNull('last_checked_at')->orWhereRaw('last_checked_at <= DATE_SUB(UTC_TIMESTAMP(), INTERVAL monitor_interval MINUTE)');})->limit(500)->get(['id']);
    foreach($due as $website) CheckWebsite::dispatch($website->id);
    $this->info("Dispatched {$due->count()} website checks.");
})->purpose('Queue due health checks for approved websites');

Schedule::command('sitecare:monitor-due')->everyMinute()->withoutOverlapping();
