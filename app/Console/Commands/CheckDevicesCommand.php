<?php

namespace App\Console\Commands;

use App\Services\DeviceMonitoringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckDevicesCommand extends Command
{
    protected $signature = 'devices:check-lock-status';
    protected $description = 'Vérifie tous les appareils et applique les verrouillages nécessaires';

    public function handle(DeviceMonitoringService $monitoringService): int
    {
        $this->info('🔍 Début de la vérification des appareils...');

        $results = $monitoringService->checkAllDevices();

        $this->newLine();
        $this->info("✅ Appareils vérifiés : {$results['checked']}");
        $this->info("🔒 Appareils verrouillés : {$results['locked']}");
        $this->info("🔓 Appareils déverrouillés : {$results['unlocked']}");

        Log::info('Device monitoring results', [
            'checked' => $results['checked'],
            'locked' => $results['locked'],
            'unlocked' => $results['unlocked'],
            'errors' => $results['errors'],
        ]);

        if (count($results['errors']) > 0) {
            $this->newLine();
            $this->error("⚠️  Erreurs rencontrées : " . count($results['errors']));

            

            foreach ($results['errors'] as $error) {
                $this->error("  Device #{$error['device_id']}: {$error['error']}");
                Log::error('Device monitoring error', $error);
            }

            return Command::FAILURE;
        }

        $this->newLine();
        $this->info('✨ Vérification terminée avec succès');

        Log::info('Device monitoring completed successfully');

        return Command::SUCCESS;
    }
}
