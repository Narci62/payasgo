<?php

namespace App\Console\Commands;

use App\Services\DeviceMonitoringService;
use Illuminate\Console\Command;

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

        if (count($results['errors']) > 0) {
            $this->newLine();
            $this->error("⚠️  Erreurs rencontrées : " . count($results['errors']));

            foreach ($results['errors'] as $error) {
                $this->error("  Device #{$error['device_id']}: {$error['error']}");
            }

            return Command::FAILURE;
        }

        $this->newLine();
        $this->info('✨ Vérification terminée avec succès');

        return Command::SUCCESS;
    }
}
