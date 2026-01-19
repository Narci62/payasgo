<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class AMAPICallbackController extends Controller
{
    /**
     * Callback appelé par Google après enrollment
     *
     * URL: https://yourdomain.com/amapi/callback
     */
    public function handleEnrollmentCallback(Request $request)
    {
        Log::info('AMAPI Enrollment Callback received', [
            'all_params' => $request->all(),
            'query' => $request->query(),
        ]);

        dd($request);

        // Google envoie les paramètres suivants :
        // - enterpriseToken : Token unique de l'entreprise créée
        // - OR adminEmail : Email de l'admin qui a accepté

        $enterpriseToken = $request->query('enterpriseToken');
        $adminEmail = $request->query('adminEmail');

        if (!$enterpriseToken) {
            return view('amapi.callback-error', [
                'error' => 'Enterprise token manquant'
            ]);
        }

        // Avec le token, on peut maintenant récupérer l'ENTERPRISE_ID
        try {
            $enterpriseId = $this->getEnterpriseIdFromToken($enterpriseToken);

            if (!$enterpriseId) {
                throw new \Exception('Impossible de récupérer l\'enterprise ID');
            }

            Log::info('AMAPI Enterprise created successfully', [
                'enterprise_id' => $enterpriseId,
                'admin_email' => $adminEmail,
            ]);

            // Stocker l'ENTERPRISE_ID (plusieurs options)
            $this->storeEnterpriseId($enterpriseId);

            Log::info('AMAPI Enterprise created successfully', [
                'enterprise_id' => $enterpriseId,
                'admin_email' => $adminEmail,
            ]);

            // Afficher une page de succès
            return view('amapi.callback-success', [
                'enterprise_id' => $enterpriseId,
                'admin_email' => $adminEmail,
            ]);
        } catch (\Exception $e) {
            Log::error('AMAPI Callback error', [
                'error' => $e->getMessage(),
                'enterprise_token' => $enterpriseToken,
            ]);

            return view('amapi.callback-error', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Récupère l'ENTERPRISE_ID à partir du token
     */

    private function getEnterpriseIdFromToken(string $enterpriseToken): ?string
    {
        try {
            $accessToken = $this->getAccessToken();
            $signupUrlName = session('amapi_signup_url_name'); // Récupération du nom stocké

            // 1. On construit l'URL avec les paramètres obligatoires
            // ATTENTION : signupUrlName doit être au format "signupUrls/XYZ"
            $queryParams = http_build_query([
                'enterpriseToken' => $enterpriseToken,
                'signupUrlName'   => $signupUrlName
            ]);

            $url = "https://androidmanagement.googleapis.com?{$queryParams}";

            // 2. L'appel POST : Le corps DOIT être un objet JSON vide {}
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type'  => 'application/json',
            ])->withBody('{}', 'application/json')->post($url);

            if ($response->failed()) {
                // Utilisation d'un tableau vide pour éviter le TypeError précédent
                Log::error('AMAPI 400 Error Detail', $response->json() ?? ['raw_body' => $response->body()]);
                return null;
            }

            return $response->json()['name']; // Retourne "enterprises/LC..."

        } catch (\Exception $e) {
            Log::error('Exception AMAPI', ['msg' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Stocke l'ENTERPRISE_ID de plusieurs façons
     */
    private function storeEnterpriseId(string $enterpriseId): void
    {
        // Option 1 : Dans la base de données
        \Illuminate\Support\Facades\DB::table('amapi_config')->updateOrInsert(
            ['key' => 'enterprise_id'],
            [
                'value' => $enterpriseId,
                'updated_at' => now(),
            ]
        );

        // Option 2 : Dans le cache
        cache()->forever('amapi_enterprise_id', $enterpriseId);

        // Option 3 : Mettre à jour le .env (nécessite permissions d'écriture)
        $this->updateEnvFile('AMAPI_ENTERPRISE_ID', $enterpriseId);
    }

    /**
     * Met à jour le fichier .env
     */
    private function updateEnvFile(string $key, string $value): void
    {
        $envFile = base_path('.env');

        if (!file_exists($envFile) || !is_writable($envFile)) {
            Log::warning('Cannot update .env file', [
                'file' => $envFile,
                'writable' => is_writable($envFile),
            ]);
            return;
        }

        $content = file_get_contents($envFile);

        if (preg_match("/^{$key}=/m", $content)) {
            // Remplacer la valeur existante
            $content = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $content);
        } else {
            // Ajouter la nouvelle ligne
            $content .= "\n{$key}={$value}\n";
        }

        file_put_contents($envFile, $content);

        Log::info('.env file updated', ['key' => $key]);
    }

    /**
     * Obtient un access token
     */
    private function getAccessToken(): string
    {
        $serviceAccountPath = storage_path('app/public/trueline-payguard-amapi-556ed97a2e37.json');

        $client = new \Google\Client();
        $client->setAuthConfig($serviceAccountPath);
        $client->addScope('https://www.googleapis.com/auth/androidmanagement');

        $token = $client->fetchAccessTokenWithAssertion();

        return $token['access_token'];
    }
}
