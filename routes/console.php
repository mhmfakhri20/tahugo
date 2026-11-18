<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// NFR-5: hapus foto absen yang lebih tua dari 90 hari.
Schedule::command('attendance:prune')->dailyAt('02:00')->withoutOverlapping();
