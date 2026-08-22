<?php

namespace App\Console\Commands;

use Google\Client as GoogleClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CreateAMAPIPolicies extends Command
{
    protected $signature = 'amapi:create-policies';

    protected $description = 'Crée les politiques par défaut (default_policy et locked_policy)';

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
            //  $this->line("AMAPI_POLICY_DEFAULT={$defaultPolicyId}");
            // $this->line("AMAPI_POLICY_LOCKED={$lockedPolicyId}");
            $this->newLine();

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Erreur : '.$e->getMessage());

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
                'error_detail' => $response->json() ?? $response->body(),
            ]);
            $this->error('Échec création default_policy : '.$response->body());

            return null;
        }

        return $policyId;
    }

    private function createLockedPolicy(string $accessToken, string $enterpriseId): ?string
    {
        $policyId = 'locked_policy';
        $enterpriseId = ltrim($enterpriseId, '/');
        $name = "enterprises/{$enterpriseId}/policies/{$policyId}";
        $url = "https://androidmanagement.googleapis.com/v1/{$name}";

        $policy = $this->getLockedPolicyConfig();

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
            'Content-Type' => 'application/json',
        ])->patch($url, $policy);

        if ($response->failed()) {
            $this->error('Échec création locked_policy : '.$response->body());

            return null;
        }

        return $policyId;
    }

    // private function getDefaultPolicyConfig(): array
    // {
    //     return [
    //         'applications' => [
    //             [
    //                 'packageName' => 'com.trueline.mdm',
    //                 'installType' => 'FORCE_INSTALLED',
    //                 'defaultPermissionPolicy' => 'GRANT',
    //             ],

    //             [
    //                 'packageName' => 'com.facebook.katana',
    //                 'installType' => 'FORCE_INSTALLED',
    //                 'defaultPermissionPolicy' => 'GRANT',
    //             ],

    //             [
    //                 'packageName' => 'com.android.chrome',
    //                 'installType' => 'AVAILABLE',
    //             ],

    //             [
    //                 'packageName' => 'com.android.vending',
    //                 'installType' => 'AVAILABLE',
    //             ],

    //             [
    //                 'packageName' => 'com.whatsapp',
    //                 'installType' => 'AVAILABLE',
    //             ],

    //             [
    //                 'packageName' => 'com.instagram.android',
    //                 'installType' => 'AVAILABLE',
    //             ],

    //             [
    //                 'packageName' => 'com.google.android.youtube',
    //                 'installType' => 'AVAILABLE',
    //             ],

    //             [
    //                 'packageName' => 'com.microsoft.office.word',
    //                 'installType' => 'AVAILABLE',
    //             ],

    //             [
    //                 'packageName' => 'com.adobe.reader',
    //                 'installType' => 'AVAILABLE',
    //             ],

    //             [
    //                 'packageName' => 'com.twitter.android',
    //                 'installType' => 'AVAILABLE',
    //             ],

    //             [
    //                 'packageName' => 'com.facebook.lite',
    //                 'installType' => 'AVAILABLE',
    //             ],

    //             [
    //                 'packageName' => 'com.zhiliaoapp.musically',
    //                 'installType' => 'AVAILABLE',
    //             ],

    //             [
    //                 'packageName' => 'com.microsoft.office.excel',
    //                 'installType' => 'AVAILABLE',
    //             ],

    //             [
    //                 'packageName' => 'org.telegram.messenger',
    //                 'installType' => 'AVAILABLE',
    //             ],
    //         ],

    //         'factoryResetDisabled' => true,
    //         'safeBootDisabled' => true,
    //         'debuggingFeaturesAllowed' => false,
    //         'addUserDisabled' => true,
    //         'removeUserDisabled' => true,

    //         'frpAdminEmails' => [
    //             'etstrueline@gmail.com',
    //         ],

    //         'appAutoUpdatePolicy' => 'ALWAYS',

    //         'locationMode' => 'HIGH_ACCURACY',

    //         'systemUpdate' => [
    //             'type' => 'AUTOMATIC',
    //         ],
    //     ];
    // }

    private function getDefaultPolicyConfig(): array
    {
        return [
            'applications' => [
                [
                    'packageName' => 'com.trueline.mdm',
                    'installType' => 'FORCE_INSTALLED',
                    'defaultPermissionPolicy' => 'GRANT',
                ],

            ],

            'playStoreMode' => 'BLACKLIST', // empecher l'accès au Play Store

            'factoryResetDisabled' => true,
            'installUnknownSourcesAllowed' => false, // empecher l'installation d'applications depuis des sources inconnues
            'safeBootDisabled' => true, // empecher le démarrage en mode sans échec
            'debuggingFeaturesAllowed' => true, // empecher le débogage
            // 'addUserDisabled' => true,
            // 'removeUserDisabled' => true,
            // 'modifyAccountsDisabled' => true, // empecher la modification des comptes
            'uninstallAppsDisabled' => true, // empecher la désinstallation de l'application MDM

            'frpAdminEmails' => [
                'etstrueline@gmail.com',
            ],

            'appAutoUpdatePolicy' => 'ALWAYS', // toujours mettre à jour les applications automatiquement

            'locationMode' => 'HIGH_ACCURACY', // mode de localisation haute précision

            'systemUpdate' => [
                'type' => 'AUTOMATIC', // mise à jour automatique du système
            ],
        ];
    }

    private function getMiddlePolicyConfig()
    {
        return [
            'applications' => [
                [
                    'packageName' => 'com.trueline.mdm',
                    'installType' => 'FORCE_INSTALLED',
                    'defaultRuntimePermissionsPolicy' => 'GRANT',
                ],
                [
                    'packageName' => 'com.whatsapp',
                    'installType' => 'BLOCKED',
                ],
                [
                    'packageName' => 'com.zhiliaoapp.musically',
                    'installType' => 'BLOCKED',
                ],
                [
                    'packageName' => 'com.facebook.katana',
                    'installType' => 'BLOCKED',
                ],
                [
                    'packageName' => 'com.instagram.android',
                    'installType' => 'BLOCKED',
                ],
            ],
            'factoryResetDisabled' => true,
            'debuggingFeaturesAllowed' => false,
        ];
    }

    // private function getLockedPolicyConfig(): array
    // {
    //     return [
    //         // Application vitrine forcée (pour afficher message verrouillage)
    //         'applications' => [
    //             [
    //                 "packageName" => "com.trueline.mdm",
    //                 "installType" => "KIOSK",
    //                 "defaultPermissionPolicy" => "GRANT"
    //             ],
    //         ],
    //     ];
    // }

    private function getLockedPolicyConfig(): array
    {
        return [
            // === Application vitrine en mode KIOSK ===
            'applications' => [
                [
                    'packageName' => 'com.trueline.mdm',
                    'installType' => 'FORCE_INSTALLED',
                    'defaultPermissionPolicy' => 'GRANT',
                    // 'lockTaskAllowed' => true,
                ],
                [
                    'packageName' => 'com.android.vending',
                    'installType' => 'BLOCKED',
                ],
            ],

            // === Launcher Kiosk natif de Google ===
            // Remplace l'écran d'accueil par le launcher verrouillé de Google
            'kioskCustomLauncherEnabled' => true,
            // 'defaultPermissionPolicy' => 'GRANT',

            // === Personnalisation du comportement kiosk ===
            'kioskCustomization' => [
                // Bouton power : disponible (écran de verrouillage)
                // Options: POWER_BUTTON_AVAILABLE, POWER_BUTTON_BLOCKED
                'powerButtonActions' => 'POWER_BUTTON_AVAILABLE',

                // Avertissements système (batterie faible, etc.) : visibles
                // Options: ERROR_AND_WARNINGS_ENABLED, ERROR_AND_WARNINGS_DISABLED
                'systemErrorWarnings' => 'ERROR_AND_WARNINGS_ENABLED',

                // Barre de navigation (boutons retour/accueil/applications) : cachée
                // Options: NAVIGATION_ENABLED, NAVIGATION_DISABLED, NAVIGATION_HOME_BUTTON_ONLY
                'systemNavigation' => 'NAVIGATION_DISABLED',

                // Barre de statut : cachée (heure, batterie, notifications)
                // Options: NOTIFICATIONS_AND_SYSTEM_INFO_ENABLED, NOTIFICATIONS_AND_SYSTEM_INFO_DISABLED
                'statusBar' => 'NOTIFICATIONS_AND_SYSTEM_INFO_DISABLED',

                // Apparence du device en mode kiosk
                // Options: KIOSK_CUSTOMIZATION_DEVICE_SETTINGS, KIOSK_CUSTOMIZATION_UNDEFINED
                'deviceSettings' => 'SETTINGS_ACCESS_BLOCKED',
            ],

            // === Mode Play Store ===
            // En mode locked, on passe en WHITELIST pour verrouiller totalement
            // Seule l'app vitrine est accessible, aucune autre app ne peut être installée
            'playStoreMode' => 'WHITELIST',

            // === Sécurité maximale : blocage de toutes les échappatoires ===
            'factoryResetDisabled' => true,
            'safeBootDisabled' => true,
            'debuggingFeaturesAllowed' => false,
            'installUnknownSourcesAllowed' => false,
            'installAppsDisabled' => true,
            'uninstallAppsDisabled' => true,
            'addUserDisabled' => true,
            'removeUserDisabled' => true,
            'modifyAccountsDisabled' => true,
            'mountPhysicalMediaDisabled' => true,      // Bloque USB/SD card
            'outgoingCallsDisabled' => false,           // Bloque les appels
            'smsDisabled' => false,                     // Bloque les SMS
            'dataRoamingDisabled' => false,             // Bloque le roaming
            'tetheringConfigDisabled' => false,               // Bloque le partage de connexion
            'vpnConfigDisabled' => true,               // Bloque les VPN utilisateur
            'wifiConfigDisabled' => false,              // Seul l'EMM configure le WiFi
            'bluetoothConfigDisabled' => false,         // Seul l'EMM configure le Bluetooth

            // === Contrôle du clavier et saisie ===
            'setWallpaperDisabled' => true,
            'funDisabled' => true,                     // Bloque les apps de divertissement système

            // === Mises à jour automatiques ===
            'appAutoUpdatePolicy' => 'ALWAYS',

            // === Localisation ===
            'locationMode' => 'HIGH_ACCURACY',

            // === Mises à jour système automatiques ===
            'systemUpdate' => [
                'type' => 'AUTOMATIC',
                // Optionnel: fenêtre de maintenance
                // 'startMinutes' => 120,  // 02h00
                // 'endMinutes' => 300,    // 05h00
            ],

            // === FRP (Factory Reset Protection) ===
            'frpAdminEmails' => [
                'etstrueline@gmail.com',
            ],

            // === Permissions par défaut ===
            // Toutes les permissions des apps sont refusées par défaut, sauf celles explicitement demandées
            'defaultPermissionPolicy' => 'GRANT',

            // === Clavier et IME ===
            // Si tu veux forcer un clavier spécifique, décommente:
            // 'permittedInputMethods' => [
            //     [
            //         'packageName' => 'com.google.android.inputmethod.latin',
            //     ],
            // ],

            // === Réseau ===
            // Si tu veux forcer un réseau WiFi spécifique:
            // 'openNetworkConfiguration' => [
            //     'NetworkConfigurations' => [
            //         [
            //             'GUID' => 'wifi-entreprise',
            //             'Name' => 'WiFi-Trueline',
            //             'Type' => 'WiFi',
            //             'WiFi' => [
            //                 'SSID' => 'WiFi-Trueline',
            //                 'Security' => 'WPA-PSK',
            //                 'Passphrase' => 'votre_mot_de_passe',
            //             ],
            //         ],
            //     ],
            // ],
        ];
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
