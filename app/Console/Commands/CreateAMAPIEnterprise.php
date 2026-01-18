<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Google\Client as GoogleClient;

class CreateAMAPIEnterprise extends Command
{
    protected $signature = 'amapi:create-enterprise {--name=PayAsGo}';
    protected $description = 'Crée une nouvelle entreprise dans Android Management API';

    public function handle()
    {
        $this->info('🚀 Création de l\'entreprise AMAPI...');

           $this->info('🚀 Création de l\'entreprise AMAPI...');

        try {
            // 1. Obtenir le token d'accès
            $accessToken = $this->getAccessToken();

            if (!$accessToken) {
                $this->error('❌ Impossible d\'obtenir le token d\'accès');
                return Command::FAILURE;
            }

            $this->info('✅ Token d\'accès obtenu');

            // 2. Créer l'entreprise via signupUrl
            $enterpriseName = $this->option('name');
            $projectId = config('services.amapi.project_id');

            // Étape 2a : Générer une signup URL
            $this->line('   Génération de la signup URL...');

            // --- MODIFICATION ICI : Assurez-vous que le callbackUrl n'est pas envoyé ---
            $signupResponse = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ])->post('https://androidmanagement.googleapis.com', [
                'projectId' => $projectId,
                // Ne mettez PAS de callbackUrl dans ce tableau.
            ]);
            // -------------------------------------------------------------------------

            if ($signupResponse->failed()) {
                $this->error('❌ Échec de génération de signup URL');
                $this->error($signupResponse->body());
                return Command::FAILURE;
            }

            // ... (le reste du script reste identique pour gérer la suite)
            $signupData = $signupResponse->json();
            $signupUrl = $signupData['url'] ?? null;
            // ... (le reste des étapes de confirmation et de vérification)

            $this->newLine();
            $this->info('✅ Entreprise vérifiée avec succès !');
            $this->newLine();
            $this->line('📋 Informations de l\'entreprise :');
            $this->line('   Nom : ' . ($data['enterpriseDisplayName'] ?? 'N/A'));

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Erreur : ' . $e->getMessage());
            return Command::FAILURE;
        }
    
    }

    private function getAccessToken(): ?string
    {
        try {
            $serviceAccountPath = config('services.amapi.service_account_json');

            if (!file_exists($serviceAccountPath)) {
                $this->error("❌ Fichier service account introuvable : {$serviceAccountPath}");
                return null;
            }

            $client = new GoogleClient();
            $client->setAuthConfig($serviceAccountPath);
            $client->addScope('https://www.googleapis.com/auth/androidmanagement');

            $token = $client->fetchAccessTokenWithAssertion();

            return $token['access_token'] ?? null;
        } catch (\Exception $e) {
            $this->error('Erreur lors de l\'obtention du token : ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Met à jour une variable dans le fichier .env
     */
    private function updateEnvFile(string $key, string $value): void
    {
        $envFile = base_path('.env');

        if (!file_exists($envFile)) {
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
