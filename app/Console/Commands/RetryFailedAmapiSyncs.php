<?php

namespace App\Console\Commands;

use App\Models\AmapiSyncLog;
use App\Notifications\AmapiSyncFailedNotification;
use App\Services\AMAPIClientService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class RetryFailedAmapiSyncs extends Command
{
    protected $signature = 'amapi:retry-failed-syncs {--max-attempts=3 : Nombre maximum de tentatives}';

    protected $description = 'Retry les synchronisations AMAPI échouées';

    public function handle(): int
    {
        $maxAttempts = $this->option('max-attempts');

        $failedLogs = AmapiSyncLog::where('status', 'failed')
            ->where('attempts', '<', $maxAttempts)
            ->with(['financingPlan.device.client', 'device.client'])
            ->get();

        if ($failedLogs->isEmpty()) {
            $this->info('Aucune synchronisation échouée à réessayer.');

            return self::SUCCESS;
        }

        $this->info("{$failedLogs->count()} synchronisation(s) échouée(s) à réessayer.");

        $successCount = 0;
        $failCount = 0;

        foreach ($failedLogs as $log) {
            $this->line("Traitement du log #{$log->id} (action: {$log->action}, device: {$log->device_id})");

            if (! $log->financingPlan || ! $log->device) {
                $this->error('  - Plan ou device introuvable, skip.');
                $log->markAsFailed('Plan ou device introuvable');
                $failCount++;

                continue;
            }

            try {
                $amapiClient = app(AMAPIClientService::class);

                if ($log->action === 'DELETE') {
                    $success = $amapiClient->deleteDevice($log->device, 'RETRY_SYNC');
                } elseif ($log->action === 'UNLOCK') {
                    $success = $amapiClient->unlockDevice($log->device, 'RETRY_SYNC');
                } else {
                    $success = $amapiClient->lockDevice($log->device, 'RETRY_SYNC');
                }

                if ($success) {
                    $log->markAsSuccess();
                    $log->financingPlan->update(['amapi_sync_status' => 'synced']);
                    $this->info('  - Succès !');
                    $successCount++;
                } else {
                    throw new \Exception('AMAPI API returned false');
                }
            } catch (\Exception $e) {
                $log->markAsFailed($e->getMessage());
                $this->error("  - Échec : {$e->getMessage()}");
                $failCount++;

                $log->financingPlan->update([
                    'amapi_sync_status' => 'failed',
                    'amapi_sync_error' => $e->getMessage(),
                ]);

                Log::error('AMAPI retry sync failed', [
                    'sync_log_id' => $log->id,
                    'device_id' => $log->device_id,
                    'action' => $log->action,
                    'error' => $e->getMessage(),
                    'attempts' => $log->attempts,
                ]);

                if ($log->attempts >= $maxAttempts) {
                    $admins = \App\Models\User::where('is_admin', true)->get();
                    Notification::send($admins, new AmapiSyncFailedNotification(
                        $log->financingPlan,
                        $log->device,
                        $log->action,
                        "Échec après {$maxAttempts} tentatives : {$e->getMessage()}"
                    ));
                }
            }
        }

        $this->newLine();
        $this->info("Résumé : {$successCount} succès, {$failCount} échecs sur {$failedLogs->count()} tentatives.");

        return $failCount > 0 ? self::FAILURE : self::SUCCESS;
    }
}
