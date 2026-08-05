<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keep every connected user's play history (and therefore the public
// PH-wide overview) fresh without anyone needing to hit "sync" manually.
Schedule::command('statsfm:sync')
    ->everyTenMinutes()
    ->withoutOverlapping();
