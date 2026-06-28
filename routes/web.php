<?php

use App\Http\Controllers\AMAPICallbackController;
use App\Http\Controllers\Api\FedapayWebhookController;
use App\Http\Controllers\ManagedPlayController;
use App\Http\Controllers\UserController;
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
