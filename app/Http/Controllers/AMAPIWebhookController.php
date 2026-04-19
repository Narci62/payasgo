<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AmapiDevice;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AMAPIWebhookController extends Controller
{
    /**
     * Webhook principal pour recevoir les événements AMAPI
     */
    public function handleWebhook(Request $request)
    {
        // Vérifier la signature du webhook
        if (!$this->verifyWebhookSignature($request)) {
            Log::warning('AMAPI webhook signature verification failed');
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = $request->all();
        $eventType = $payload['eventType'] ?? null;

        Log::info('AMAPI webhook received', ['event_type' => $eventType]);

        try {
            switch ($eventType) {
                case 'ENROLLMENT':
                    return $this->handleEnrollment($payload);

                case 'COMPLIANCE_REPORT':
                    return $this->handleComplianceReport($payload);

                case 'STATUS_REPORT':
                    return $this->handleStatusReport($payload);

                case 'COMMAND_COMPLETED':
                    return $this->handleCommandCompleted($payload);

                default:
                    Log::info('Unhandled AMAPI event type', ['type' => $eventType]);
                    return response()->json(['message' => 'Event received'], 200);
            }
        } catch (\Exception $e) {
            Log::error('AMAPI webhook processing error', [
                'error' => $e->getMessage(),
                'payload' => $payload
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Gère l'événement d'enrollment (appareil provisionné)
     */
    private function handleEnrollment(array $payload): \Illuminate\Http\JsonResponse
    {
        $deviceName = $payload['device']['name'] ?? null;
        $additionalData = json_decode($payload['enrollmentToken']['additionalData'] ?? '{}', true);

        $deviceId = $additionalData['device_id'] ?? null;

        if (!$deviceId) {
            Log::error('AMAPI enrollment: device_id not found in additionalData');
            return response()->json(['error' => 'Invalid data'], 400);
        }

        $device = Device::find($deviceId);
        if (!$device) {
            Log::error('AMAPI enrollment: device not found', ['device_id' => $deviceId]);
            return response()->json(['error' => 'Device not found'], 404);
        }

        // Mettre à jour l'enregistrement AMAPI
        $amapiDevice = AmapiDevice::where('device_id', $deviceId)->first();
        if ($amapiDevice) {
            $amapiDevice->update([
                'amapi_device_id' => $this->extractDeviceId($deviceName),
                'amapi_state' => 'ACTIVE',
                'enrolled_at' => now(),
                'amapi_metadata' => $payload['device'] ?? null
            ]);
        }

        // Mettre à jour l'appareil
        $device->update([
            'status' => 'active'
        ]);

        Log::info('AMAPI device enrolled successfully', [
            'device_id' => $deviceId,
            'amapi_device_id' => $deviceName
        ]);

        return response()->json(['message' => 'Enrollment processed'], 200);
    }

    /**
     * Gère le rapport de conformité
     */
    private function handleComplianceReport(array $payload): \Illuminate\Http\JsonResponse
    {
        $deviceName = $payload['device']['name'] ?? null;
        $amapiDeviceId = $this->extractDeviceId($deviceName);

        $amapiDevice = AmapiDevice::where('amapi_device_id', $amapiDeviceId)->first();
        if (!$amapiDevice) {
            Log::warning('AMAPI compliance report: device not found', ['amapi_device_id' => $amapiDeviceId]);
            return response()->json(['error' => 'Device not found'], 404);
        }

        // Mettre à jour les métadonnées avec les infos de conformité
        $metadata = $amapiDevice->amapi_metadata ?? [];
        $metadata['compliance_report'] = $payload;

        $amapiDevice->update([
            'amapi_metadata' => $metadata,
            'last_amapi_sync_at' => now()
        ]);

        Log::info('AMAPI compliance report processed', ['device_id' => $amapiDevice->device_id]);

        return response()->json(['message' => 'Compliance report processed'], 200);
    }

    /**
     * Gère le rapport de statut (heartbeat)
     */
    private function handleStatusReport(array $payload): \Illuminate\Http\JsonResponse
    {
        $deviceName = $payload['device']['name'] ?? null;
        $amapiDeviceId = $this->extractDeviceId($deviceName);

        $amapiDevice = AmapiDevice::where('amapi_device_id', $amapiDeviceId)->first();
        if (!$amapiDevice) {
            return response()->json(['error' => 'Device not found'], 404);
        }

        // Mettre à jour last_seen_at sur l'appareil
        $device = $amapiDevice->device;
        if ($device) {
            $device->update([
                'last_seen_at' => now()
            ]);
        }

        $amapiDevice->update([
            'last_amapi_sync_at' => now()
        ]);

        return response()->json(['message' => 'Status report processed'], 200);
    }

    /**
     * Gère la complétion d'une commande
     */
    private function handleCommandCompleted(array $payload): \Illuminate\Http\JsonResponse
    {
        $deviceName = $payload['device']['name'] ?? null;
        $commandName = $payload['command']['name'] ?? null;
        $commandType = $payload['command']['type'] ?? null;
        $status = $payload['command']['status'] ?? null;

        $amapiDeviceId = $this->extractDeviceId($deviceName);
        $amapiDevice = AmapiDevice::where('amapi_device_id', $amapiDeviceId)->first();

        if (!$amapiDevice) {
            return response()->json(['error' => 'Device not found'], 404);
        }

        // Mettre à jour le statut de la commande
        $amapiDevice->update([
            'last_command_status' => $status === 'SUCCEEDED' ? 'SUCCESS' : 'FAILED',
            'last_command_error' => $status !== 'SUCCEEDED' ? json_encode($payload['command']) : null
        ]);

        Log::info('AMAPI command completed', [
            'device_id' => $amapiDevice->device_id,
            'command_type' => $commandType,
            'status' => $status
        ]);

        return response()->json(['message' => 'Command completion processed'], 200);
    }

    /**
     * Vérifie la signature du webhook
     */
    private function verifyWebhookSignature(Request $request): bool
    {
        $secret = config('services.amapi.webhook_secret');

        if (!$secret) {
            // Si pas de secret configuré, accepter (à adapter selon vos besoins)
            return true;
        }

        $signature = $request->header('X-AMAPI-Signature');
        $payload = $request->getContent();

        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Extrait l'ID de l'appareil depuis le nom complet AMAPI
     */
    private function extractDeviceId(string $deviceName): string
    {
        // Format: enterprises/{enterpriseId}/devices/{deviceId}
        $parts = explode('/', $deviceName);
        return end($parts);
    }
}
