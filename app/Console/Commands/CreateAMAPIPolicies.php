<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

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
            // $this->info('📝 Création de locked_policy...');
            // $lockedPolicyId = $this->createLockedPolicy($accessToken, $enterpriseId);

            // if ($lockedPolicyId) {
            //     $this->info("✅ locked_policy créée : {$lockedPolicyId}");
            // }

            $this->newLine();
            $this->info('✅ Politiques créées avec succès !');
            $this->newLine();
            $this->line('📝 Ajoutez ces lignes dans votre fichier .env :');
            $this->newLine();
            $this->line("AMAPI_POLICY_DEFAULT={$defaultPolicyId}");
            // $this->line("AMAPI_POLICY_LOCKED={$lockedPolicyId}");
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
        $enterpriseId = ltrim($enterpriseId, '/');
        $name = "enterprises/{$enterpriseId}/policies/{$policyId}";
        $url = "https://androidmanagement.googleapis.com/v1/{$name}";

        $policy = $this->getDefaultPolicyConfig();

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
            'Content-Type' => 'application/json',
        ])->patch($url, $policy);

        if ($response->failed()) {
            Log::error('AMAPI Policy Error', [
                'constructed_url' => $url,
                'error_detail' => $response->json() ?? $response->body()
            ]);
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
            'applications' => [
                [
                   // 'packageName' => 'com.trueline.mdm',
                    'packageName' => 'com.facebook.katana',
                    'installType' => 'FORCE_INSTALLED',
                    'defaultPermissionPolicy' => 'GRANT',
                ],

                [
                    'packageName' => 'com.android.vending',
                    'installType' => 'AVAILABLE'
                ]
            ],

            "factoryResetDisabled"  => true,
            "frpAdminEmails"  => [
                "etstrueline@gmail.com"
            ],

            // "complianceRules" => [
            //     [
            //         "nonComplianceDetailCondition" => [
            //             "nonComplianceReason" => "NETWORK_INFO",
            //         ],
            //         "packageNamesToExempt" => [],
            //         "actionAfterDays" => 14,
            //         "blockAction" => [
            //             "blockAfterDays" => 0,
            //         ]
            //     ]
            // ],

            "safeBootDisabled"  => true,
            "debuggingFeaturesAllowed" => false,
            "addUserDisabled"=> true,
            "removeUserDisabled"=> true,
            "systemUpdate" => [
                "type" => "WINDOWED",
            ],
            "appAutoUpdatePolicy" => "ALWAYS",
            "locationMode" => "SENSORS_ONLY"

        ];
    }

    private function getMiddlePolicyConfig()
    {
        return [
            "applications" => [
                [
                    'packageName' => 'com.trueline.mdm',
                    "installType" => "FORCE_INSTALLED",
                    "defaultRuntimePermissionsPolicy" => "GRANT"
                ],
                [
                    "packageName" => "com.whatsapp",
                    "installType" => "BLOCKED"
                ],
                [
                    "packageName" => "com.zhiliaoapp.musically",
                    "installType" => "BLOCKED"
                ],
                [
                    "packageName" => "com.facebook.katana",
                    "installType" => "BLOCKED"
                ],
                [
                    "packageName" => "com.instagram.android",
                    "installType" => "BLOCKED"
                ]
            ],
            "factoryResetDisabled" => true,
            "debuggingFeaturesAllowed" => false
        ];
    }

    private function getLockedPolicyConfig(): array
    {
        return [
            // Application vitrine forcée (pour afficher message verrouillage)
            'applications' => [
                [
                    'packageName' => 'com.trueline.mdm', // Votre app
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
                    dd("Le fichier est introuvable à cet endroit précis : " . $serviceAccountPath);

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
