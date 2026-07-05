<?php

use App\Http\Controllers\AMAPICallbackController;
use App\Http\Controllers\Api\FedapayWebhookController;
use App\Http\Controllers\ManagedPlayController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect("/admin");
});


Route::get('/paiement/{imat?}', [FedapayWebhookController::class, 'showForm'])->name('payment.form');
Route::post('/paiement', [FedapayWebhookController::class, 'processPayment'])->name('payment.process');
Route::get('/fedapay/finish', [FedapayWebhookController::class, 'callback'])->name('fedapay.end');

// Callback AMAPI (après enrollment)
Route::get('/amapi', [App\Http\Controllers\AMAPICallbackController::class, 'handleCallback'])
    ->name('amapi.callback');

Route::get('/generate-signup-url', [AMAPICallbackController::class, 'generateSignupUrl'])
        ->name('amapi.generate-signup-url');

Route::get('/information/{imat}', [UserController::class, 'show']);


Route::middleware(['auth'])->prefix('admin/mdm')->group(function () {
    // C'est cette ligne qui crée l'URL exacte attendue par Google
    Route::get('apps', [ManagedPlayController::class, 'getIframeUrl'])->name('admin.mdm.apps');
});

// cron org pour synchro
Route::get('/cron/sync-data', function () {
    // verifier si l'entete comporte un clé valeur
    $secret = request()->header('X-CRON-SECRET');
    if ($secret !== env('CRON_SECRET')) {
        abort(403, 'Unauthorized');
    }
    // executer la commande php artisan devices:sync-amapi-devices
    Artisan::call('devices:sync-amapi-devices');
})->name('cron.sync');

Route::get('/cron/verify-all-devices', function () {
    // verifier si l'entete comporte un clé valeur
    $secret = request()->header('X-CRON-SECRET');
    if ($secret !== env('CRON_SECRET')) {
        abort(403, 'Unauthorized');
    }
    // executer la commande php artisan devices:check-lock-status
    Artisan::call('devices:check-lock-status');
})->name('cron.verifyAllDevices');

