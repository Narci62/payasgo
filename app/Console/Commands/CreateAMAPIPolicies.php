<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Google\Client as GoogleClient;

class CreateAMAPIPolicies extends Command
{
    protected $signature = 'amapi:create-policies';
    protected $description = 'Crée les politiques par défaut (default_policy et locked_policy)';

    public function handle()
    {
        $enterpriseId = config('services.amapi.enterprise_id');

        if (!$enterpriseId) {
            $this->error('❌ AMAPI_ENTERPRISE_ID non configuré dans .env');
            return Command::FAILURE;
        }

        $this->info('🚀 Création des politiques AMAPI...');

        try {
            $accessToken = $this->getAccessToken();

            if (!$accessToken) {
                $this->error('❌ Impossible d\'obtenir le token d\'accès');
                return Command::FAILURE;
            }

            // 1. Créer la politique par défaut (appareil actif)
            $this->info('📝 Création de default_policy...');
            $defaultPolicyId = $this->createDefaultPolicy($accessToken, $enterpriseId);

            if ($defaultPolicyId) {
                $this->info("✅ default_policy créée : {$defaultPolicyId}");
            }

            // 2. Créer la politique de verrouillage
            $this->info('📝 Création de locked_policy...');
            $lockedPolicyId = $this->createLockedPolicy($accessToken, $enterpriseId);

            if ($lockedPolicyId) {
                $this->info("✅ locked_policy créée : {$lockedPolicyId}");
            }

            $this->newLine();
            $this->info('✅ Politiques créées avec succès !');
            $this->newLine();
            $this->line('📝 Ajoutez ces lignes dans votre fichier .env :');
            $this->newLine();
            $this->line("AMAPI_POLICY_DEFAULT={$defaultPolicyId}");
            $this->line("AMAPI_POLICY_LOCKED={$lockedPolicyId}");
            $this->newLine();

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Erreur : ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function createDefaultPolicy(string $accessToken, string $enterpriseId): ?string
    {
        $policyId = 'default_policy';
        $url = "https://androidmanagement.googleapis.com/v1/{$enterpriseId}/policies/{$policyId}";

        $policy = $this->getDefaultPolicyConfig();

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
            'Content-Type' => 'application/json',
        ])->put($url, $policy);

        if ($response->failed()) {
            $this->error('Échec création default_policy : ' . $response->body());
            return null;
        }

        return $policyId;
    }

    private function createLockedPolicy(string $accessToken, string $enterpriseId): ?string
    {
        $policyId = 'locked_policy';
        $url = "https://androidmanagement.googleapis.com/v1/{$enterpriseId}/policies/{$policyId}";

        $policy = $this->getLockedPolicyConfig();

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
            'Content-Type' => 'application/json',
        ])->put($url, $policy);

        if ($response->failed()) {
            $this->error('Échec création locked_policy : ' . $response->body());
            return null;
        }

        return $policyId;
    }

    private function getDefaultPolicyConfig(): array
    {
        return [
            // Appareil en mode kiosque avec votre application vitrine
            'applications' => [
                [
                    'packageName' => 'com.payasgo.vitrine', // À remplacer par votre package
                    'installType' => 'FORCE_INSTALLED',
                    'defaultPermissionPolicy' => 'GRANT',
                    'lockTaskAllowed' => true, // Permet le mode kiosque
                ],
            ],

            // Mode kiosque : seulement l'app vitrine accessible
            'kioskCustomization' => [
                'deviceSettings' => 'SETTINGS_ACCESS_ALLOWED',
                'powerButtonActions' => 'POWER_BUTTON_AVAILABLE',
                'statusBar' => 'NOTIFICATIONS_AND_SYSTEM_INFO_ENABLED',
                'systemErrorWarnings' => 'ERROR_AND_WARNINGS_ENABLED',
                'systemNavigation' => 'NAVIGATION_ENABLED',
            ],

            // Applications système autorisées
            'systemUpdate' => [
                'type' => 'AUTOMATIC',
                'startMinutes' => 120, // 2h du matin
                'endMinutes' => 300,   // 5h du matin
            ],

            // Restrictions minimales (appareil fonctionnel)
            'bluetoothConfigDisabled' => false,
            'cellBroadcastsConfigDisabled' => false,
            'factoryResetDisabled' => true, // Empêcher factory reset
            'keyguardDisabled' => false,
            'statusBarDisabled' => false,
            'wifiConfigDisabled' => false,

            // Autoriser les appels, SMS, contacts
            'smsDisabled' => false,
            'outgoingCallsDisabled' => false,

            // Sécurité
            'screenCaptureDisabled' => false,
            'cameraDisabled' => false,
            'adjustVolumeDisabled' => false,

            // Password requirements (optionnel)
            'passwordRequirements' => [
                'passwordMinimumLength' => 4,
                'passwordQuality' => 'NUMERIC',
            ],

            // Network settings
            'mobileNetworksConfigDisabled' => false,
            'privateKeySelectionEnabled' => true,

            // Compliance rules (vérifications)
            'complianceRules' => [
                [
                    'nonComplianceDetailCondition' => [
                        'settingName' => 'locationType',
                        'comparisonOperator' => 'EQUALS',
                        'value' => 'GPS_DISABLED',
                    ],
                    'disableApps' => false, // Ne pas désactiver apps si GPS off
                ],
            ],

            // Status reporting
            'statusReportingSettings' => [
                'applicationReportsEnabled' => true,
                'deviceSettingsEnabled' => true,
                'softwareInfoEnabled' => true,
                'memoryInfoEnabled' => true,
                'networkInfoEnabled' => true,
                'displayInfoEnabled' => true,
                'powerManagementEventsEnabled' => true,
                'hardwareStatusEnabled' => true,
                'systemPropertiesEnabled' => true,
                'commonCriteriaModeEnabled' => false,
            ],
        ];
    }

    private function getLockedPolicyConfig(): array
    {
        return [
            // Application vitrine forcée (pour afficher message verrouillage)
            'applications' => [
                [
                    'packageName' => 'com.payasgo.vitrine', // Votre app
                    'installType' => 'FORCE_INSTALLED',
                    'defaultPermissionPolicy' => 'GRANT',
                    'lockTaskAllowed' => true,
                ],
            ],

            // Mode kiosque STRICT : uniquement app vitrine
            'kioskCustomization' => [
                'deviceSettings' => 'SETTINGS_ACCESS_BLOCKED', // Bloque paramètres
                'powerButtonActions' => 'POWER_BUTTON_BLOCKED', // Bloque bouton power
                'statusBar' => 'NOTIFICATIONS_AND_SYSTEM_INFO_DISABLED', // Cache notifs
                'systemErrorWarnings' => 'ERROR_AND_WARNINGS_MUTED',
                'systemNavigation' => 'NAVIGATION_DISABLED', // Désactive navigation
            ],

            // RESTRICTIONS MAXIMALES
            'bluetoothConfigDisabled' => true,     // Bloque Bluetooth
            'cellBroadcastsConfigDisabled' => true,
            'factoryResetDisabled' => true,        // Bloque factory reset
            'keyguardDisabled' => true,            // Désactive écran de verrouillage natif
            'statusBarDisabled' => true,           // Cache barre de statut
            'wifiConfigDisabled' => true,          // Bloque config WiFi

            // Bloquer communications
            'smsDisabled' => true,                 // Bloque SMS
            'outgoingCallsDisabled' => true,       // Bloque appels sortants
            'outgoingBeamDisabled' => true,        // Bloque NFC/Beam

            // Bloquer médias et caméra
            'cameraDisabled' => true,              // Bloque caméra
            'screenCaptureDisabled' => true,       // Bloque screenshots
            'adjustVolumeDisabled' => true,        // Bloque volume

            // Bloquer ajout de comptes
            'addUserDisabled' => true,
            'accountTypesWithManagementDisabled' => ['*'],

            // Bloquer installations
            'installUnknownSourcesAllowed' => false,

            // Bloquer modifications système
            'mobileNetworksConfigDisabled' => true,
            'modifyAccountsDisabled' => true,
            'unmuteMicrophoneDisabled' => true,

            // Message de verrouillage (affiché sur écran)
            'deviceOwnerLockScreenInfo' => [
                'localizedMessages' => [
                    'fr' => [
                        'title' => '🔒 Téléphone suspendu',
                        'message' => "Votre versement est en retard.\n\nEffectuez votre paiement pour débloquer l'appareil.\n\nContact : +229 01 76 65 65",
                    ],
                    'default' => [
                        'title' => '🔒 Device Locked',
                        'message' => "Payment overdue.\n\nPlease pay to unlock.\n\nContact: +229 01 76 65 65",
                    ],
                ],
            ],

            // Reporting (même verrouillé, on veut les infos)
            'statusReportingSettings' => [
                'applicationReportsEnabled' => true,
                'deviceSettingsEnabled' => true,
                'softwareInfoEnabled' => true,
                'networkInfoEnabled' => true,
                'powerManagementEventsEnabled' => true,
                'hardwareStatusEnabled' => true,
            ],

            // System updates toujours actifs
            'systemUpdate' => [
                'type' => 'AUTOMATIC',
                'startMinutes' => 120,
                'endMinutes' => 300,
            ],
        ];
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
}
