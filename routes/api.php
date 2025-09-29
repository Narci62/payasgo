<?php

use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\DeviceStatusController;
use App\Http\Controllers\Api\FedapayWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// admin
Route::middleware('auth:admin-api')->prefix('admin')->group(function () {
    Route::get("/manuel-paiement", [FedapayWebhookController::class, "index"]);
});

Route::get('/payasgo', function(){
    return response()->json(['message' => 'Welcome to the PayasGo API']);
});

//registration client
Route::post("/client/register", [ClientController::class, "store"]);


// registration device
Route::post("/device/register", [DeviceController::class, "store"]);
Route::middleware('auth:device-api', 'device.auth')->prefix('device')->group(function () {
    Route::get("/status", [DeviceStatusController::class, "status"]);
});

// payment by fedepay
Route::post("/webhooks/fedapay", [FedapayWebhookController::class, "handleWebhook"]);
