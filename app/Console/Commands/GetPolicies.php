<?php

namespace App\Console\Commands;

use Google\Client as GoogleClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class GetPolicies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:get-policies';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $enterpriseId = config('services.amapi.enterprise_id');

        if (! $enterpriseId) {
            $this->error('❌ AMAPI_ENTERPRISE_ID non configuré dans .env');

            return Command::FAILURE;
        }

        $this->info('🚀 Création des politiques AMAPI...');

        try {
            $accessToken = $this->getAccessToken();

            if (! $accessToken) {
                $this->error('❌ Impossible d\'obtenir le token d\'accès');

                return Command::FAILURE;
            }

            $this->info('📝 Récupération des politiques existantes...');

            $policies = $this->getPolicies($accessToken, $enterpriseId);

            if (empty($policies)) {
                $this->warn('Aucune policy trouvée.');

                return Command::SUCCESS;
            }

            foreach ($policies as $policy) {
                $this->line('');
                $this->info($policy['name']);

                $this->line(json_encode($policy, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }

            $this->newLine();

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Erreur : '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    private function getPolicies(string $accessToken, string $enterpriseId): array
    {
        $enterpriseId = ltrim($enterpriseId, '/');

        $url = "https://androidmanagement.googleapis.com/v1/enterprises/{$enterpriseId}/policies";

        $response = Http::withToken($accessToken)->get($url);

        if ($response->failed()) {
            $this->error($response->body());

            return [];
        }

        return $response->json('policies', []);
    }

    private function getAccessToken(): ?string
    {
        try {
            $serviceAccountPath = config('services.amapi.service_account_json');

            if (! file_exists($serviceAccountPath)) {
                dd('Le fichier est introuvable à cet endroit précis : '.$serviceAccountPath);

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
}
