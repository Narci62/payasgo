<?php

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\AMAPIWebhookController;
use App\Http\Controllers\Api\DeviceStatusController;
use App\Http\Controllers\Api\FinancingPlanController;
use App\Http\Controllers\Api\FedapayWebhookController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post("/users/getuser", [ClientController::class, "getUserByDeviceToken"]);

// admin
Route::middleware('auth:admin-api')->prefix('admin')->group(function () {
    Route::get("/manuel-paiement", [FedapayWebhookController::class, "index"]);
});

Route::get('/payasgo', function(){
    return response()->json(['message' => 'Welcome to the PayasGo API']);
});

//registration client
Route::post("/client/register", [ClientController::class, "store"]);

//create financing plan
Route::post("/client/financing-plan", [FinancingPlanController::class, "store"]);

// registration device
Route::post("/user-profile", [DeviceController::class, "store"]);
Route::post("/auth", [DeviceController::class, "refresh"]);

Route::middleware('auth:device-api', 'device.auth')->prefix('device')->group(function () {
    Route::get("/status", [DeviceStatusController::class, "status"]);
});

// payment by fedepay
Route::post("/webhooks/fedapay", [FedapayWebhookController::class, "handleWebhook"]);
Route::post('/webhook', [FedapayWebhookController::class, 'webhook'])->name('fedapay.webhook');

// Webhook AMAPI (doit être accessible publiquement)
Route::post('/webhooks/amapi', [AMAPIWebhookController::class, 'handleWebhook'])
    ->name('amapi.webhook');
