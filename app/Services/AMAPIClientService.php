<?php

namespace App\Services;

use App\Helpers\Helper;
use App\Models\AmapiDevice;
use App\Models\Device;
use App\Models\DeviceLockHistory;
use App\Models\Financing_plan;
use Carbon\Carbon;
use Exception;
use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
                        // 'client_reference' => $device->client->reference,
                        // 'backend_url' => config('app.url'),
                    ], $additionalData)),
                ]);

            if ($response->failed()) {
                Log::error('AMAPI enrollment token creation failed', [
                    'device_id' => $device->id,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
                throw new Exception('AMAPI enrollment token creation failed: '.$response->body());
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
                    'amapi_policy_id' => 'default_policy',
                    'amapi_state' => 'PROVISIONING',
                ]
            );

            return [
                'success' => true,
                'enrollment_token' => $enrollmentToken,
                'qr_code' => $qrCode,
                'expires_at' => now()->addDays(30),
            ];
        } catch (Exception $e) {
            Log::error('AMAPI QR Code generation failed', [
                'device_id' => $device->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Checker les device amapi pour recuperer les deviceId et les mettre a jour dans la table amapi_devices
     */
    public function syncAMAPIDevices()
    {
        $response = Http::withHeaders($this->getAuthHeaders())
            ->get("{$this->baseUrl}/enterprises/{$this->enterpriseId}/devices");

        if ($response->failed()) {
            Log::error('AMAPI Sync Failed', [
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return;
        }

        Log::info('AMAPI Sync Response', [
            'response' => $response->body(),
        ]);

        $googleDevices = $response->json('devices', []);

        Log::info('AMAPI Devices Count', [
            'count' => count($googleDevices),
        ]);

        foreach ($googleDevices as $googleDevice) {

            // ID AMAPI (ex: enterprises/.../devices/320283ccf89728c8)
            $amapiDeviceId = basename($googleDevice['name']);

            // Décodage du enrollmentTokenData
            $tokenData = json_decode($googleDevice['enrollmentTokenData'] ?? '{}', true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('AMAPI enrollmentTokenData invalide', [
                    'device' => $amapiDeviceId,
                    'value' => $googleDevice['enrollmentTokenData'] ?? null,
                ]);

                continue;
            }

            $laravelId = $tokenData['device_id'] ?? null;

            if (! $laravelId) {
                Log::warning('Aucun device_id trouvé dans enrollmentTokenData', [
                    'device' => $amapiDeviceId,
                ]);

                continue;
            }

            Log::info('Syncing AMAPI Device', [
                'laravel_id' => $laravelId,
                'amapi_device_id' => $amapiDeviceId,
                'state' => $googleDevice['state'] ?? null,
            ]);

            AmapiDevice::where('device_id', $laravelId)
                ->where('amapi_device_id', '!=', $amapiDeviceId)
                ->update([
                    'amapi_device_id' => $amapiDeviceId,
                    'amapi_state' => $googleDevice['state'] ?? null,
                    'last_amapi_sync_at' => now(),
                ]);
        }
    }

    // mise à jour

    public function afterCreate(Financing_plan $financing_plan): array
    {
        // update financing plan with device id
        // $financing_plan->update([
        //     'device_id' => Cache::pull('created_device_id'),
        // ]);

        // create enrollment token for google amapi enrollment
        $amapi_enrollment_token = $this->generateProvisioningQRCode($financing_plan->device);
        // dd($amapi_enrollment_token);

        // save payment histories
        (new \App\Services\PaymentService)->store([
            'financing_plan_id' => $financing_plan->id,
            'amount' => $financing_plan->down_payment,
            'method' => 'manual',
            'transaction_id' => uniqid('txn'),
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        return $amapi_enrollment_token;
    }

    /**
     * Verrouille un appareil via AMAPI
     */
    public function lockDevice(Device $device, string $reason, ?int $userId = null): bool
    {
        $amapiDevice = $device->amapiDevice;

        if (! $amapiDevice || ! $amapiDevice->amapi_device_id) {
            throw new Exception('Device not enrolled in AMAPI');
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
                'triggered_by_user_id' => $userId,
            ]);

            // Appliquer une politique de verrouillage via AMAPI 
            $response = Http::withHeaders($this->getAuthHeaders())
                ->patch(
                    "{$this->baseUrl}/enterprises/{$this->enterpriseId}/devices/{$amapiDevice->amapi_device_id}",
                    [
                        'policyName' => "enterprises/{$this->enterpriseId}/policies/locked_policy",
                        'state' => 'DISABLED',
                    ]
                );

            if ($response->successful()) {
                // Mettre à jour l'appareil AMAPI
                $amapiDevice->update([
                    'amapi_state' => 'DISABLED',
                    'amapi_policy_id' => 'locked_policy',
                    'last_command_sent_at' => now(),
                    'last_command_type' => 'LOCK',
                    'last_command_status' => 'SUCCESS',
                ]);

                // Mettre à jour l'historique
                $lockHistory->update([
                    'action' => 'LOCK',
                    'status' => 'SUCCESS',
                    'executed_at' => now(),
                    'amapi_command_id' => $response->json('name'),
                ]);

                // Mettre à jour l'appareil
                $device->update(['status' => 'locked']);

                Log::info('Device locked successfully via AMAPI', [
                    'device_id' => $device->id,
                    'reason' => $reason,
                ]);

                return true;
            }

            Log::error('AMAPI lock command failed', [
                'device_id' => $device->id,
                'device_amapi_id' => $amapiDevice->amapi_device_id,
                'response' => $response->body(),
            ]);

            throw new Exception($response->body());
        } catch (Exception $e) {
            // Marquer comme échec
            $lockHistory->update([
                'status' => 'FAILED',
                'error_message' => $e->getMessage(),
            ]);

            $amapiDevice->update([
                'last_command_status' => 'FAILED',
                'last_command_error' => $e->getMessage(),
            ]);

            Log::error('AMAPI lock command failed', [
                'device_id' => $device->id,
                'error' => $e->getMessage(),
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

        if (! $amapiDevice || ! $amapiDevice->amapi_device_id) {
            throw new Exception('Device not enrolled in AMAPI');
        }

        $lockHistory = DeviceLockHistory::create([
            'device_id' => $device->id,
            'financing_plan_id' => $device->financingPlan?->id,
            'action' => 'UNLOCK_ATTEMPT',
            'trigger_reason' => $reason,
            'status' => 'PENDING',
            'triggered_by_user_id' => $userId,
        ]);

        try {

            $response = Http::withHeaders($this->getAuthHeaders())
                ->patch(
                    "{$this->baseUrl}/enterprises/{$this->enterpriseId}/devices/{$amapiDevice->amapi_device_id}",
                    [
                        'policyName' => "enterprises/{$this->enterpriseId}/policies/default_policy",
                        'state' => 'ACTIVE',
                    ]
                );

            if ($response->successful()) {
                $amapiDevice->update([
                    'amapi_state' => 'ACTIVE',
                    'amapi_policy_id' => 'default_policy',
                    'last_command_sent_at' => now(),
                    'last_command_type' => 'UNLOCK',
                    'last_command_status' => 'SUCCESS',
                ]);

                $lockHistory->update([
                    'action' => 'UNLOCK',
                    'status' => 'SUCCESS',
                    'executed_at' => now(),
                    'amapi_command_id' => $response->json('name'),
                ]);

                $device->update(['status' => 'active']);

                Log::info('Device unlocked successfully via AMAPI', [
                    'device_id' => $device->id,
                    'reason' => $reason,
                ]);

                return true;
            }

            throw new Exception($response->body());
        } catch (Exception $e) {
            $lockHistory->update([
                'status' => 'FAILED',
                'error_message' => $e->getMessage(),
            ]);

            Log::error('AMAPI unlock command failed', [
                'device_id' => $device->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Supprimer un appareil via AMAPI
     */
    public function deleteDevice(Device $device, string $reason, ?int $userId = null): bool
    {
        $amapiDevice = $device->amapiDevice;

        if (! $amapiDevice || ! $amapiDevice->amapi_device_id) {
            throw new Exception('Device not enrolled in AMAPI');
        }

        $lockHistory = DeviceLockHistory::create([
            'device_id' => $device->id,
            'financing_plan_id' => $device->financingPlan?->id,
            'action' => 'DELETE_ATTEMPT',
            'trigger_reason' => $reason,
            'status' => 'PENDING',
            'triggered_by_user_id' => $userId,
        ]);
        $response = Http::withHeaders($this->getAuthHeaders())
            ->delete(
                "{$this->baseUrl}/enterprises/{$this->enterpriseId}/devices/{$amapiDevice->amapi_device_id}"
            );

        if ($response->successful() || $response->status() === 404) {
            Log::info('Appareil supprimé de AMAPI Enterprise', [
                'device_id' => $amapiDevice->amapi_device_id,
                'status' => $response->status(),
            ]);

            $amapiDevice->update([
                'amapi_state' => 'ACTIVE',
                'amapi_policy_id' => 'default_policy',
                'last_command_sent_at' => now(),
                'last_command_type' => 'UNLOCK',
                'last_command_status' => 'SUCCESS',
            ]);

            $lockHistory->update([
                'action' => 'UNLOCK',
                'status' => 'SUCCESS',
                'executed_at' => now(),
                'amapi_command_id' => $response->json('name'),
            ]);

            $device->update(['status' => 'active']);

            Log::info('Device unlocked successfully via AMAPI', [
                'device_id' => $device->id,
                'reason' => $reason,
            ]);

            return true;
        }

        Log::error('Erreur suppression device AMAPI', [
            'device_id' => $amapiDevice->amapi_device_id,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return false;
    }

    /**
     * Récupère l'état d'un appareil depuis AMAPI
     */
    public function syncDeviceStatus(Device $device): ?array
    {
        $amapiDevice = $device->amapiDevice;

        if (! $amapiDevice || ! $amapiDevice->amapi_device_id) {
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
                    'last_amapi_sync_at' => now(),
                ]);

                // last_seen_at dans Device doit refléter la vraie dernière activité connue par Google,
                // pas seulement les appels à notre API /device/status
                $this->syncLastSeenFromAmapiPayload($device, $data);

                return $data;
            }

            Log::warning('AMAPI device sync: réponse non réussie', [
                'device_id' => $device->id,
                'status' => $response->status(),
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('AMAPI device sync failed', [
                'device_id' => $device->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Met à jour device.last_seen_at si AMAPI rapporte une activité plus récente
     * que ce que nous avons localement (heartbeat, sync policy, compliance report).
     */
    private function syncLastSeenFromAmapiPayload(Device $device, array $data): void
    {
        $candidates = array_filter([
            $data['lastStatusReportTime'] ?? null,
            $data['lastPolicySyncTime'] ?? null,
            $data['lastPolicyComplianceReportTime'] ?? null,
        ]);

        if (empty($candidates)) {
            return;
        }

        try {
            $mostRecent = collect($candidates)
                ->map(fn ($ts) => Carbon::parse($ts))
                ->max();

            $currentLastSeen = $device->last_seen_at
                ? Carbon::parse($device->last_seen_at)
                : null;

            if (! $currentLastSeen || $mostRecent->greaterThan($currentLastSeen)) {
                $device->update(['last_seen_at' => $mostRecent]);

                Log::info('last_seen_at resynchronisé depuis AMAPI', [
                    'device_id' => $device->id,
                    'new_last_seen_at' => $mostRecent->toDateTimeString(),
                ]);
            }
        } catch (Exception $e) {
            Log::warning('Impossible de parser les timestamps AMAPI', [
                'device_id' => $device->id,
                'error' => $e->getMessage(),
            ]);
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
            'Authorization' => 'Bearer '.$this->getAccessToken(),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    /**
     * Obtient un access token (à implémenter selon AMAPI)
     */
    private function getAccessToken(): string
    {
        // return Helper::getAccessToken() ?? '';

        try {
            $serviceAccountPath = config('services.amapi.service_account_json');

            $client = new GoogleClient;
            $client->setAuthConfig($serviceAccountPath);
            $client->addScope('https://www.googleapis.com/auth/androidmanagement');

            $token = $client->fetchAccessTokenWithAssertion();

            return $token['access_token'] ?? null;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    private function calculateDaysOverdue(Device $device): ?int
    {
        $plan = $device->financingPlan;
        if (! $plan || ! $plan->next_payment_due_date) {
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
        if (! $device->last_seen_at) {
            return null;
        }

        return now()->diffInDays($device->last_seen_at);
    }
}
