<?php

namespace App\Services;

use App\Models\Device;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DeviceMonitoringService
{
    public function __construct(
        private AMAPIClientService $amapiClient
    ) {}

    /**
     * Vérifie tous les appareils et applique les règles de verrouillage
     * À appeler via un cron job quotidien
     */
    public function checkAllDevices(): array
    {
        $results = [
            'checked' => 0,
            'locked' => 0,
            'unlocked' => 0,
            'errors' => [],
        ];

        // Récupérer tous les appareils actifs avec leur plan de financement
        $devices = Device::with(['financingPlan', 'amapiDevice', 'client'])
            ->whereHas('amapiDevice', function ($query) {
                $query->whereNotNull('amapi_device_id');
            })
            ->get();

        // logger le nombre d'appareils récupérés
        Log::info('Device monitoring started', ['total_devices' => $devices->count()]);

        foreach ($devices as $device) {
            $results['checked']++;

            try {
                $action = $this->determineDeviceAction($device);

                // logger l'action déterminée pour chaque appareil
                Log::info('Device action determined', [
                    'device_id' => $device->id,
                    'client' => $device->client?->name ?? 'Unknown',
                    'action' => $action,
                    'device_status' => $device->status,
                    'amapi_state' => $device->amapiDevice?->amapi_state,
                    'next_payment_due_date' => $device->financingPlan?->next_payment_due_date,
                    'last_seen_at' => $device->last_seen_at,
                ]);

                if ($action === 'LOCK') {
                    $reason = $this->getLockReason($device);
                    if ($this->amapiClient->lockDevice($device, $reason)) {
                        $results['locked']++;
                    }
                } elseif ($action === 'UNLOCK') {
                    if ($this->amapiClient->unlockDevice($device, 'PAYMENT_RECEIVED')) {
                        $results['unlocked']++;
                    }
                }

            } catch (\Exception $e) {
                $results['errors'][] = [
                    'device_id' => $device->id,
                    'client' => $device->client?->name ?? 'Unknown',
                    'error' => $e->getMessage(),
                ];

                Log::error('Device monitoring error', [
                    'device_id' => $device->id,
                    'client' => $device->client?->name ?? 'Unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Device monitoring completed', $results);

        return $results;
    }

    /**
     * Détermine l'action à prendre pour un appareil
     */
    private function determineDeviceAction(Device $device): ?string
    {
        $shouldBeLocked = $this->shouldDeviceBeLocked($device);
        $currentlyLocked = $device->status === 'locked' ||
                          $device->amapiDevice?->amapi_state === 'DISABLED';

        if ($shouldBeLocked && ! $currentlyLocked) {
            return 'LOCK';
        }

        if (! $shouldBeLocked && $currentlyLocked) {
            return 'UNLOCK';
        }

        return null; // Aucune action nécessaire
    }

    /**
     * Vérifie si un appareil doit être verrouillé
     */
    public function shouldDeviceBeLocked(Device $device): bool
    {
        // Règle 1 : Retard de paiement
        if ($this->isPaymentOverdue($device)) {
            return true;
        }

        // Règle 2 : Inactivité de 14 jours
        if ($this->isInactive14Days($device)) {
            return true;
        }

        return false;
    }

    /**
     * Vérifie si le paiement est en retard
     */
    private function isPaymentOverdue(Device $device): bool
    {
        $plan = $device->financingPlan;

        if (! $plan) {
            return false;
        }

        // Si le plan est déjà soldé, pas de retard
        if ($plan->getRawOriginal('status') === 'paid_in_full') {
            return false;
        }

        // Si pas de date de prochain paiement, pas de retard
        if (! $plan->next_payment_due_date) {
            return false;
        }

        // Comparer avec la date actuelle
        $dueDate = Carbon::parse($plan->next_payment_due_date);

        return now()->greaterThan($dueDate);
    }

    /**
     * Vérifie si l'appareil est inactif depuis 14 jours
     */
    // private function isInactive14Days(Device $device): bool
    // {
    //     if (!$device->last_seen_at) {
    //         // Si jamais vu, considérer comme inactif après 14 jours depuis création
    //         return $device->created_at->diffInDays(now()) >= 14;
    //     }

    //     $lastSeen = Carbon::parse($device->last_seen_at);

    //     return $lastSeen->diffInDays(now()) >= 14;
    // }
    /**
     * Vérifie si l'appareil est inactif depuis 14 jours.
     *
     * IMPORTANT : last_seen_at n'est mis à jour que lorsque le device appelle
     * notre API (/device/status) ou via un webhook AMAPI déjà reçu. Pour éviter
     * de verrouiller un appareil qui communique bien avec Google mais n'a pas
     * (encore) rappelé notre API, on resynchronise activement depuis AMAPI
     * dès qu'on approche du seuil (>= 12 jours), avant de trancher définitivement.
     */
    private function isInactive14Days(Device $device): bool
    {
        $daysSinceLastSeen = $this->daysSinceLastSeen($device);

        // On ne sollicite AMAPI que si on approche/dépasse le seuil,
        // pour ne pas multiplier les appels API sur l'ensemble du parc à chaque cron.
        if ($daysSinceLastSeen >= 12) {
            $this->refreshDeviceActivityFromAmapi($device);
            $daysSinceLastSeen = $this->daysSinceLastSeen($device->fresh());
        }

        return $daysSinceLastSeen >= 14;
    }

    /**
     * Calcule le nombre de jours écoulés depuis la dernière activité connue.
     */
    private function daysSinceLastSeen(Device $device): int
    {
        $lastSeen = $device->last_seen_at
            ? Carbon::parse($device->last_seen_at)
            : $device->created_at;

        return $lastSeen->diffInDays(now());
    }

    /**
     * Interroge AMAPI pour vérifier la dernière activité réelle de l'appareil
     * et met à jour last_seen_at en conséquence si Google rapporte plus récent.
     * Échec silencieux (log uniquement) : on se rabat alors sur la donnée locale.
     */
    private function refreshDeviceActivityFromAmapi(Device $device): void
    {
        if (! $device->amapiDevice?->amapi_device_id) {
            return;
        }

        try {
            $this->amapiClient->syncDeviceStatus($device);
        } catch (\Exception $e) {
            Log::warning('Resync AMAPI échouée, utilisation des données locales pour la décision', [
                'device_id' => $device->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Obtient la raison du verrouillage
     */
    private function getLockReason(Device $device): string
    {
        if ($this->isPaymentOverdue($device)) {
            return 'PAYMENT_OVERDUE';
        }

        if ($this->isInactive14Days($device)) {
            return 'INACTIVITY_14_DAYS';
        }

        return 'MANUAL_ADMIN';
    }

    /**
     * Vérifie un appareil spécifique et applique l'action nécessaire
     */
    public function checkSingleDevice(Device $device): array
    {
        try {
            $action = $this->determineDeviceAction($device);

            if ($action === 'LOCK') {
                $reason = $this->getLockReason($device);
                $success = $this->amapiClient->lockDevice($device, $reason);

                return [
                    'action' => 'LOCK',
                    'success' => $success,
                    'reason' => $reason,
                ];
            }

            if ($action === 'UNLOCK') {
                $success = $this->amapiClient->unlockDevice($device, 'PAYMENT_RECEIVED');

                return [
                    'action' => 'UNLOCK',
                    'success' => $success,
                ];
            }

            return [
                'action' => 'NONE',
                'message' => 'No action required',
            ];

        } catch (\Exception $e) {
            return [
                'action' => 'ERROR',
                'error' => $e->getMessage(),
            ];
        }
    }
}
