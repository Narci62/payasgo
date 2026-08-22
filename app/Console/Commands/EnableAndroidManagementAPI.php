<?php

namespace App\Console\Commands;

use Google\Client as GoogleClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class EnableAndroidManagementAPI extends Command
{
    protected $signature = 'amapi:enable';

    protected $description = 'Vérifie et active l\'API Android Management';

    public function handle()
    {
        $this->info('🔍 Vérification de l\'API Android Management...');
        $this->newLine();

        $projectId = config('services.amapi.project_id');

        if (empty($projectId)) {
            $this->error('❌ AMAPI_PROJECT_ID non configuré dans .env');

            return Command::FAILURE;
        }

        $this->line("📋 Projet GCP : {$projectId}");
        $this->newLine();

        // Instructions manuelles (car l'activation via API nécessite des droits spéciaux)
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('📱 ACTIVATION DE L\'API ANDROID MANAGEMENT');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        $this->line('1️⃣  Ouvrez cette URL dans votre navigateur :');
        $this->newLine();
        $url = "https://console.cloud.google.com/apis/library/androidmanagement.googleapis.com?project={$projectId}";
        $this->comment($url);
        $this->newLine();

        $this->line('2️⃣  Cliquez sur le bouton "ACTIVER" (ENABLE)');
        $this->newLine();

        $this->line('3️⃣  Attendez quelques secondes que l\'activation soit effective');
        $this->newLine();

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        if (! $this->confirm('Avez-vous activé l\'API ?', false)) {
            $this->warn('⏸️  Relancez cette commande après avoir activé l\'API');

            return Command::SUCCESS;
        }

        // Vérifier que l'API est bien activée
        $this->info('🔍 Vérification de l\'activation...');

        try {
            $accessToken = $this->getAccessToken();

            if (! $accessToken) {
                $this->error('❌ Impossible d\'obtenir le token');

                return Command::FAILURE;
            }

            // Tester la génération d'une signup URL (meilleur test)
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ])->post('https://androidmanagement.googleapis.com/v1/signupUrls', [
                'projectId' => $projectId,
            ]);

            if ($response->status() === 404) {
                $this->error('❌ L\'API ne semble toujours pas activée');
                $this->warn('   Attendez 1-2 minutes et réessayez');

                return Command::FAILURE;
            }

            if ($response->status() === 403) {
                $this->warn('⚠️  L\'API est activée mais vous n\'avez pas encore d\'entreprise');
                $this->info('✅ C\'est normal ! Passez à l\'étape suivante');
                $this->newLine();
                $this->line('Prochaine étape :');
                $this->comment('   php artisan amapi:create-enterprise');

                return Command::SUCCESS;
            }

            if ($response->successful()) {
                $this->info('✅ L\'API Android Management est activée et fonctionnelle !');
                $this->newLine();
                $this->line('Prochaine étape :');
                $this->comment('   php artisan amapi:create-enterprise');

                return Command::SUCCESS;
            }

            $this->warn("⚠️  Réponse inattendue (status {$response->status()})");
            $this->line($response->body());

        } catch (\Exception $e) {
            $this->error('❌ Erreur : '.$e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function getAccessToken(): ?string
    {
        try {
            $serviceAccountPath = config('services.amapi.service_account_json');

            if (! file_exists($serviceAccountPath)) {
                $this->error("❌ Fichier service account introuvable : {$serviceAccountPath}");

                return null;
            }

            $client = new GoogleClient;
            $client->setAuthConfig($serviceAccountPath);
            $client->addScope('https://www.googleapis.com/auth/androidmanagement');

            $token = $client->fetchAccessTokenWithAssertion();

            return $token['access_token'] ?? null;

        } catch (\Exception $e) {
            $this->error('Erreur token : '.$e->getMessage());

            return null;
        }
    }
}
