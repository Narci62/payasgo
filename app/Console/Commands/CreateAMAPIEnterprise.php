<?php

namespace App\Console\Commands;

use Google\Client as GoogleClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CreateAMAPIEnterprise extends Command
{
    protected $signature = 'amapi:create-enterprise {--name=PayAsGo}';

    protected $description = 'Crée une nouvelle entreprise dans Android Management API';

    public function handle()
    {
        $this->info('🚀 Création de l\'entreprise AMAPI...');

        try {
            // 1. Obtenir le token d'accès
            $accessToken = $this->getAccessToken();

            if (! $accessToken) {
                $this->error('❌ Impossible d\'obtenir le token d\'accès');

                return Command::FAILURE;
            }

            $this->info('✅ Token d\'accès obtenu');

            // 2. Créer l'entreprise via signupUrl
            $enterpriseName = $this->option('name');
            $projectId = config('services.amapi.project_id');

            // Étape 2a : Générer une signup URL AVEC callback
            $this->line('   Génération de la signup URL...');

            $callbackUrl = config('app.url').'/amapi/callback';

            // Vérifier que l'URL est en HTTPS (sauf en local)
            if (! app()->environment('local') && ! str_starts_with($callbackUrl, 'https://')) {
                $this->warn('⚠️  Le callback URL doit être en HTTPS en production');
                $this->line("   URL actuelle : {$callbackUrl}");
                $this->newLine();

                if (! $this->confirm('Continuer sans callback URL (vous devrez entrer l\'ID manuellement) ?', true)) {
                    return Command::FAILURE;
                }

                // Sans callback
                $callbackUrl = null;
            }

            $payload = ['projectId' => $projectId];

            if ($callbackUrl) {
                $payload['callbackUrl'] = $callbackUrl;
                $this->line("   Callback URL : {$callbackUrl}");
            }

            $signupResponse = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ])->post('https://androidmanagement.googleapis.com/v1/signupUrls', $payload);

            if ($signupResponse->failed()) {
                $this->error('❌ Échec de génération de signup URL');
                $this->error($signupResponse->body());

                return Command::FAILURE;
            }

            $signupData = $signupResponse->json();
            $signupUrl = $signupData['url'] ?? null;
            $signUpUrlName = $signupData['name'] ?? null;

            session(['amapi_signup_url_name' => $signUpUrlName]);

            if (! $signupUrl) {
                $this->error('❌ Aucune URL de signup générée');

                return Command::FAILURE;
            }

            $this->newLine();
            $this->line('✅ Signup URL générée');
            $this->newLine();
            $this->warn('⚠️  IMPORTANT : Vous devez maintenant compléter l\'enrollment');
            $this->newLine();
            $this->line('1️⃣  Ouvrez cette URL dans votre navigateur :');
            $this->newLine();
            $this->line($signupUrl);
            $this->newLine();
            $this->line(' Url name : '.$signUpUrlName);
            $this->newLine();
            $this->line('2️⃣  Suivez les étapes Google pour créer l\'entreprise');
            $this->line('3️⃣  Une fois complété, vous recevrez un ENTERPRISE_ID');
            $this->newLine();

            // Attendre confirmation
            if (! $this->confirm('Avez-vous complété l\'enrollment et obtenu l\'ENTERPRISE_ID ?', false)) {
                $this->warn('Processus annulé. Relancez la commande après avoir complété l\'enrollment.');

                return Command::SUCCESS;
            }

            // Demander l'enterprise ID
            $enterpriseId = $this->ask('Entrez votre ENTERPRISE_ID (format: enterprises/LC...)');

            if (empty($enterpriseId) || ! str_starts_with($enterpriseId, 'enterprises/')) {
                $this->error('❌ ENTERPRISE_ID invalide');

                return Command::FAILURE;
            }

            // Vérifier que l'enterprise existe
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->get("https://androidmanagement.googleapis.com/v1/{$enterpriseId}");

            if ($response->failed()) {
                $this->error('❌ Impossible de vérifier l\'entreprise');
                $this->error($response->body());

                return Command::FAILURE;
            }

            $data = $response->json();

            $this->newLine();
            $this->info('✅ Entreprise vérifiée avec succès !');
            $this->newLine();
            $this->line('📋 Informations de l\'entreprise :');
            $this->line('   Nom : '.($data['enterpriseDisplayName'] ?? 'N/A'));
            $this->line('   ID : '.$enterpriseId);
            $this->newLine();
            $this->line('📝 Ajoutez cette ligne dans votre fichier .env :');
            $this->newLine();
            $this->line("AMAPI_ENTERPRISE_ID={$enterpriseId}");
            $this->newLine();

            // 3. Proposition d'ajouter automatiquement au .env
            if ($this->confirm('Voulez-vous ajouter automatiquement AMAPI_ENTERPRISE_ID au fichier .env ?', true)) {
                $this->updateEnvFile('AMAPI_ENTERPRISE_ID', $enterpriseId);
                $this->info('✅ .env mis à jour');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Erreur : '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    private function getAccessToken(): ?string
    {
        try {
            $serviceAccountPath = storage_path('app/public/trueline-payguard-amapi-556ed97a2e37.json');

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
            $this->error('Erreur lors de l\'obtention du token : '.$e->getMessage());

            return null;
        }
    }

    /**
     * Met à jour une variable dans le fichier .env
     */
    private function updateEnvFile(string $key, string $value): void
    {
        $envFile = base_path('.env');

        if (! file_exists($envFile)) {
            return;
        }

        $content = file_get_contents($envFile);

        // Vérifier si la clé existe déjà
        if (preg_match("/^{$key}=/m", $content)) {
            // Remplacer la valeur existante
            $content = preg_replace(
                "/^{$key}=.*/m",
                "{$key}={$value}",
                $content
            );
        } else {
            // Ajouter la nouvelle clé
            $content .= "\n{$key}={$value}\n";
        }

        file_put_contents($envFile, $content);
    }
}
