<?php

namespace App\Console\Commands;

use App\Services\AMAPIClientService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncAMAPIDevices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */

    protected $signature = 'devices:sync-amapi-devices';
    protected $description = "Synchronise les appareils avec l'API AMAPI";



    /**
     * Execute the console command.
     */
    public function handle( AMAPIClientService $amapiClientService )
    {
        $this->info('🔍 Début de la synchronisation des appareils...');

        $amapiClientService->syncAMAPIDevices();

        $this->newLine();

        Log::info('🔍 Synchronisation des appareils terminée avec succès.');


    }
}
