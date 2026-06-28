<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


// Exécute la commande tous les jours à une heure précise (ex: 9h du matin)
Schedule::command('app:send-payment-reminders')->dailyAt('09:00');

// Rappels de paiement (existant)
Schedule::command('app:send-payment-reminders')->dailyAt('09:00');

// sychronisation des nouveaux appareils avec AMAPI
Schedule::command('devices:sync-amapi-devices')->hourly()->withoutOverlapping()->onFailure(function () {
    Log::error('Device sync with AMAPI failed');
});

// ========================================
// NOUVEAU : Vérification automatique des verrouillages
// ========================================
Schedule::command('devices:check-lock-status')
    ->dailyAt('00:00')  // Tous les jours à minuit
    ->withoutOverlapping()
    ->onFailure(function () {
        // Envoyer une notification en cas d'échec
        Log::error('Device lock check cron failed');
    });

// Alternative : exécuter toutes les 6 heures
// Schedule::command('devices:check-lock-status')->everySixHours();
