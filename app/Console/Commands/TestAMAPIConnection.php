<?php

namespace App\Console\Commands;

use Google\Client as GoogleClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestAMAPIConnection extends Command
{
    protected $signature = 'amapi:test';

    protected $description = 'Teste la connexion à l\'API Android Management';

    public function handle()
    {
        $this->info('🔍 Test de connexion AMAPI...');
        $this->newLine();

        // 1. Vérifier les variables d'environnement
        $this->info('1️⃣ Vérification des variables .env...');

        $checks = [
            'AMAPI_PROJECT_ID' => config('services.amapi.project_id'),
            'AMAPI_ENTERPRISE_ID' => config('services.amapi.enterprise_id'),
            'AMAPI_SERVICE_ACCOUNT_JSON' => config('services.amapi.service_account_json'),
            'AMAPI_POLICY_DEFAULT' => config('services.amapi.policies.default'),
            'AMAPI_POLICY_LOCKED' => config('services.amapi.policies.locked'),
        ];

        $allConfigured = true;
        foreach ($checks as $key => $value) {
            if (empty($value)) {
                $this->error("   ❌ {$key} non configuré");
                $allConfigured = false;
            } else {
                $this->line("   ✅ {$key}: ".(strlen($value) > 50 ? substr($value, 0, 50).'...' : $value));
            }
        }

        if (! $allConfigured) {
            $this->newLine();
            $this->error('❌ Configuration incomplète. Veuillez configurer toutes les variables.');

            return Command::FAILURE;
        }

        // 2. Vérifier le fichier service account
        $this->newLine();
        $this->info('2️⃣ Vérification du fichier service account...');

        $serviceAccountPath = config('services.amapi.service_account_json');
        if (! file_exists($serviceAccountPath)) {
            $this->error("   ❌ Fichier introuvable : {$serviceAccountPath}");

            return Command::FAILURE;
        }

        $this->line("   ✅ Fichier trouvé : {$serviceAccountPath}");

        // 3. Obtenir un token d'accès
        $this->newLine();
        $this->info('3️⃣ Obtention du token d\'accès...');

        try {
            $client = new GoogleClient;
            $client->setAuthConfig($serviceAccountPath);
            $client->addScope('https://www.googleapis.com/auth/androidmanagement');

            $token = $client->fetchAccessTokenWithAssertion();

            if (! isset($token['access_token'])) {
                $this->error('   ❌ Impossible d\'obtenir le token');
                $this->error('   Erreur : '.($token['error_description'] ?? 'Inconnue'));

                return Command::FAILURE;
            }

            $accessToken = $token['access_token'];
            $this->line('   ✅ Token obtenu : '.substr($accessToken, 0, 30).'...');

        } catch (\Exception $e) {
            $this->error('   ❌ Erreur : '.$e->getMessage());

            return Command::FAILURE;
        }

        // 4. Tester l'accès à l'enterprise
        $this->newLine();
        $this->info('4️⃣ Test d\'accès à l\'entreprise...');

        $enterpriseId = config('services.amapi.enterprise_id');
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
        ])->get("https://androidmanagement.googleapis.com/v1/{$enterpriseId}");

        if ($response->failed()) {
            $this->error('   ❌ Impossible d\'accéder à l\'entreprise');
            $this->error('   '.$response->body());

            return Command::FAILURE;
        }

        $enterprise = $response->json();
        $this->line('   ✅ Entreprise accessible : '.($enterprise['enterpriseDisplayName'] ?? 'N/A'));

        // 5. Vérifier les policies
        $this->newLine();
        $this->info('5️⃣ Vérification des politiques...');

        $policies = ['default', 'locked'];
        foreach ($policies as $policyType) {
            $policyId = config("services.amapi.policies.{$policyType}");

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->get("https://androidmanagement.googleapis.com/v1/{$enterpriseId}/policies/{$policyId}");

            if ($response->successful()) {
                $this->line("   ✅ {$policyType}_policy existe");
            } else {
                $this->error("   ❌ {$policyType}_policy introuvable");
            }
        }

        // Résumé
        $this->newLine();
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('✅ Configuration AMAPI fonctionnelle !');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        $this->line('Prochaines étapes :');
        $this->line('  1. Exécuter les migrations : php artisan migrate');
        $this->line('  2. Tester l\'enrollment d\'un appareil');
        $this->line('  3. Configurer le webhook AMAPI');
        $this->newLine();

        return Command::SUCCESS;
    }
}
