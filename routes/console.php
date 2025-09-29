<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


// Exécute la commande tous les jours à une heure précise (ex: 9h du matin)
Schedule::command('app:send-payment-reminders')->dailyAt('09:00');
