<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\FedapayWebhookController;

Route::get('/', function () {
    return redirect("/admin");
});


Route::get('/paiement', [FedapayWebhookController::class, 'showForm'])->name('payment.form');
Route::post('/paiement', [FedapayWebhookController::class, 'processPayment'])->name('payment.process');
Route::post('/fedapay/webhook', [FedapayWebhookController::class, 'webhook'])->name('fedapay.webhook');
