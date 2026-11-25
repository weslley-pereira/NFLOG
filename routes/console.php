<?php

use Illuminate\Support\Facades\Schedule;

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sessões PPPoE a partir do syslog; foto das ativas pela API de hora em hora.
Schedule::command('nflog:sessoes')->everyMinute()->withoutOverlapping();
Schedule::command('nflog:sessoes --foto')->hourlyAt(7)->withoutOverlapping();
