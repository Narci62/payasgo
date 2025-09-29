<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeviceStatusResource;
use Illuminate\Http\Request;

class DeviceStatusController extends Controller
{
    public function status(Request $request)
    {
        // 1. Obtenir l'appareil authentifié
        // Laravel Sanctum rend l'appareil disponible via user() sur la requête
        $device = $request->user();

        // 2. Mettre à jour sa dernière date de contact
        $device->update(['last_seen_at' => now()]);

        // 3. Charger les informations de son plan de financement
        // C'est crucial pour vérifier les dates et le solde
        $device->load('financingPlan');

        // 4. Retourner la réponse formatée via une API Resource
        // La ressource contiendra la logique pour décider du statut
        return new DeviceStatusResource($device);
    }
}
