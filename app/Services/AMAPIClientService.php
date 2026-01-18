<?php

namespace App\Services;

use App\Models\AmapiDevice;
use App\Models\Device;
use App\Models\DeviceLockHistory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class AMAPIClientService
{
    private string $baseUrl;
    private string $enterpriseId;
    private string $serviceAccountKey;

    public function __construct()
    {
        $this->baseUrl = config('services.amapi.base_url');
        $this->enterpriseId = config('services.amapi.enterprise_id');
        $this->serviceAccountKey = config('services.amapi.service_account_key');
    }

    /**
     * Génère un QR code pour le provisioning d'un nouvel appareil
     */
    public function generateProvisioningQRCode(Device $device, array $additionalData = []): array
    {
        try {
            // Créer un enrollment token dans AMAPI
            $response = Http::withHeaders($this->getAuthHeaders())
                ->post("{$this->baseUrl}/enterprises/{$this->enterpriseId}/enrollmentTokens", [
                    'policyName' => "enterprises/{$this->enterpriseId}/policies/default_policy",
                    'duration' => '2592000s', // 30 jours
                    'additionalData' => json_encode(array_merge([
                        'device_id' => $device->id,
                        'client_reference' => $device->client->reference,
                        'backend_url' => config('app.url'),
                    ], $additionalData))
                ]);

            if ($response->failed()) {
                throw new Exception("AMAPI enrollment token creation failed: " . $response->body());
            }

            $data = $response->json();
            $enrollmentToken = $data['name'];
            $qrCode = $data['qrCode'];

            // Enregistrer dans amapi_devices
            AmapiDevice::updateOrCreate(
                ['device_id' => $device->id],
                [
                    'amapi_enterprise_id' => $this->enterpriseId,
                    'enrollment_token' => $enrollmentToken,
                    'qr_code_data' => $qrCode,
                    'amapi_state' => 'PROVISIONING'
                ]
            );

            return [
                'success' => true,
                'enrollment_token' => $enrollmentToken,
                'qr_code' => $qrCode,
                'expires_at' => now()->addDays(30)
            ];

        } catch (Exception $e) {
            Log::error('AMAPI QR Code generation failed', [
                'device_id' => $device->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Verrouille un appareil via AMAPI
     */
    public function lockDevice(Device $device, string $reason, ?int $userId = null): bool
    {
        $amapiDevice = $device->amapiDevice;

        if (!$amapiDevice || !$amapiDevice->amapi_device_id) {
            throw new Exception("Device not enrolled in AMAPI");
        }

        try {
            // Créer l'historique AVANT d'envoyer la commande
            $lockHistory = DeviceLockHistory::create([
                'device_id' => $device->id,
                'financing_plan_id' => $device->financingPlan?->id,
                'action' => 'LOCK_ATTEMPT',
                'trigger_reason' => $reason,
                'status' => 'PENDING',
                'remaining_balance' => $device->financingPlan?->remaining_balance,
                'days_overdue' => $this->calculateDaysOverdue($device),
                'days_inactive' => $this->calculateDaysInactive($device),
                'triggered_by_user_id' => $userId
            ]);

            // Appliquer une politique de verrouillage via AMAPI
            $response = Http::withHeaders($this->getAuthHeaders())
                ->patch(
                    "{$this->baseUrl}/enterprises/{$this->enterpriseId}/devices/{$amapiDevice->amapi_device_id}",
                    [
                        'policyName' => "enterprises/{$this->enterpriseId}/policies/locked_policy",
                        'state' => 'DISABLED'
                    ]
                );

            if ($response->successful()) {
                // Mettre à jour l'appareil AMAPI
                $amapiDevice->update([
                    'amapi_state' => 'DISABLED',
                    'last_command_sent_at' => now(),
                    'last_command_type' => 'LOCK',
                    'last_command_status' => 'SUCCESS'
                ]);

                // Mettre à jour l'historique
                $lockHistory->update([
                    'action' => 'LOCK',
                    'status' => 'SUCCESS',
                    'executed_at' => now(),
                    'amapi_command_id' => $response->json('name')
                ]);

                // Mettre à jour l'appareil
                $device->update(['status' => 'locked']);

                Log::info('Device locked successfully via AMAPI', [
                    'device_id' => $device->id,
                    'reason' => $reason
                ]);

                return true;
            }

            throw new Exception($response->body());

        } catch (Exception $e) {
            // Marquer comme échec
            $lockHistory->update([
                'status' => 'FAILED',
                'error_message' => $e->getMessage()
            ]);

            $amapiDevice->update([
                'last_command_status' => 'FAILED',
                'last_command_error' => $e->getMessage()
            ]);

            Log::error('AMAPI lock command failed', [
                'device_id' => $device->id,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Déverrouille un appareil via AMAPI
     */
    public function unlockDevice(Device $device, string $reason, ?int $userId = null): bool
    {
        $amapiDevice = $device->amapiDevice;

        if (!$amapiDevice || !$amapiDevice->amapi_device_id) {
            throw new Exception("Device not enrolled in AMAPI");
        }

        try {
            $lockHistory = DeviceLockHistory::create([
                'device_id' => $device->id,
                'financing_plan_id' => $device->financingPlan?->id,
                'action' => 'UNLOCK_ATTEMPT',
                'trigger_reason' => $reason,
                'status' => 'PENDING',
                'triggered_by_user_id' => $userId
            ]);

            $response = Http::withHeaders($this->getAuthHeaders())
                ->patch(
                    "{$this->baseUrl}/enterprises/{$this->enterpriseId}/devices/{$amapiDevice->amapi_device_id}",
                    [
                        'policyName' => "enterprises/{$this->enterpriseId}/policies/default_policy",
                        'state' => 'ACTIVE'
                    ]
                );

            if ($response->successful()) {
                $amapiDevice->update([
                    'amapi_state' => 'ACTIVE',
                    'last_command_sent_at' => now(),
                    'last_command_type' => 'UNLOCK',
                    'last_command_status' => 'SUCCESS'
                ]);

                $lockHistory->update([
                    'action' => 'UNLOCK',
                    'status' => 'SUCCESS',
                    'executed_at' => now(),
                    'amapi_command_id' => $response->json('name')
                ]);

                $device->update(['status' => 'active']);

                Log::info('Device unlocked successfully via AMAPI', [
                    'device_id' => $device->id,
                    'reason' => $reason
                ]);

                return true;
            }

            throw new Exception($response->body());

        } catch (Exception $e) {
            $lockHistory->update([
                'status' => 'FAILED',
                'error_message' => $e->getMessage()
            ]);

            Log::error('AMAPI unlock command failed', [
                'device_id' => $device->id,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Récupère l'état d'un appareil depuis AMAPI
     */
    public function syncDeviceStatus(Device $device): ?array
    {
        $amapiDevice = $device->amapiDevice;

        if (!$amapiDevice || !$amapiDevice->amapi_device_id) {
            return null;
        }

        try {
            $response = Http::withHeaders($this->getAuthHeaders())
                ->get("{$this->baseUrl}/enterprises/{$this->enterpriseId}/devices/{$amapiDevice->amapi_device_id}");

            if ($response->successful()) {
                $data = $response->json();

                $amapiDevice->update([
                    'amapi_state' => $data['state'],
                    'amapi_metadata' => $data,
                    'last_amapi_sync_at' => now()
                ]);

                return $data;
            }

            return null;

        } catch (Exception $e) {
            Log::error('AMAPI device sync failed', [
                'device_id' => $device->id,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Headers d'authentification AMAPI
     */
    private function getAuthHeaders(): array
    {
        // À adapter selon votre méthode d'authentification AMAPI
        // (OAuth2, Service Account, API Key, etc.)
        return [
            'Authorization' => 'Bearer ' . $this->getAccessToken(),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];
    }

    /**
     * Obtient un access token (à implémenter selon AMAPI)
     */
    private function getAccessToken(): string
    {
        // Implémentation OAuth2 ou autre méthode d'auth
        // Cache le token pour éviter trop d'appels
        return cache()->remember('amapi_access_token', 3500, function () {
            // Logique d'obtention du token
            return 'your_token_here';
        });
    }

    private function calculateDaysOverdue(Device $device): ?int
    {
        $plan = $device->financingPlan;
        if (!$plan || !$plan->next_payment_due_date) {
            return null;
        }

        $dueDate = \Carbon\Carbon::parse($plan->next_payment_due_date);
        if (now()->lessThan($dueDate)) {
            return 0;
        }

        return now()->diffInDays($dueDate);
    }

    private function calculateDaysInactive(Device $device): ?int
    {
        if (!$device->last_seen_at) {
            return null;
        }

        return now()->diffInDays($device->last_seen_at);
    }
}
