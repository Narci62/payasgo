# Synthèse AMAPI — Architecture Device Management

## Contexte

PayasGo est un dispositif de financement de téléphones (buy-now-pay-later). L'entreprise doit pouvoir **verrouiller les appareils** en cas de non-paiement et **les libérer** une fois le financement terminé. L'objectif est de trouver un équilibre entre contrôle total sur l'appareil et respect de l'espace personnel de l'utilisateur.

## Analyse des modes Android Enterprise

### 1. Fully Managed (Device Owner)

- Contrôle total sur tout l'appareil
- **Aucune donnée personnelle possible**
- Le device est entièrement géré par l'entreprise
- `locked_policy` grise tout (apps perso incluses)

### 2. Work Profile (Profile Owner)

- Espace séparé (conteneur travail / conteneur perso)
- L'entreprise ne manage **que le work profile**
- **Impossible de griser les apps personnelles**
- L'utilisateur peut continuer à utiliser normalement son téléphone perso même en mode lock

### 3. COPE — Company-Owned, Personally Enabled ✅ (mode retenu)

- Appartient à l'entreprise (company-owned)
- Work profile activé pour l'espace travail
- **Espace perso disponible** pour l'utilisateur
- **Verrouillage device-wide possible** (bloque tout, persos inclus)
- `RELINQUISH_OWNERSHIP` à la fin du financement libère le device

| Action | Fully Managed | Work Profile | COPE |
|---|---|---|---|
| Données perso | ❌ | ✅ | ✅ |
| Lock total (persos grises) | ✅ | ❌ | ✅ |
| Wipe factory reset | ✅ | ❌ (work only) | ✅ (device-wide) |
| Relinquish (libérer le device) | ❌ | N/A | ✅ |
| Verrouillage apps persos | ✅ | ❌ | ✅ |

## Architecture retenue — COPE

### Flow lifecycle d'un appareil

```
1. ENROLLMENT
   → QR Code généré par generateProvisioningQRCode()
   → Policy : default_policy (avec workProfilePolicy activé)
   → Device enregistré dans amapi_devices (state: PROVISIONING)
   → L'utilisateur installe ses apps perso, utilise normalement

2. UTILISATION NORMALE
   → default_policy appliquée
   → Espace travail : apps force-installed (com.trueline.mdm)
   → Espace perso : libre (apps, données, comptes)

3. VERROUILLAGE (non-paiement)
   → lockDevice() applique locked_policy
   → kioskCustomLauncherEnabled : écran d'accueil verrouillé
   → usagesDisabled : accès à l'espace perso bloqué
   → Seule l'app vitrine (com.trueline.mdm) est accessible
   → Navigation, barre de statut, boutons désactivés

4. DÉVERROUILLAGE (paiement repris)
   → unlockDevice() réapplique default_policy
   → Espace perso restauré
   → Utilisation normale reprise

5. LIBÉRATION (financement terminé)
   → RELINQUISH_OWNERSHIP (à implémenter)
   → Work profile supprimé
   → Données personnelles conservées
   → Téléphone devient 100% personnel
```

### Fichiers concernés

| Fichier | Rôle |
|---|---|
| `app/Services/AMAPIClientService.php` | Service principal AMAPI (enrollment, lock, unlock, delete) |
| `app/Console/Commands/CreateAMAPIPolicies.php` | Création des policies AMAPI (default, locked) |
| `config/services.php` | Configuration AMAPI (enterprise_id, policies, service account) |
| `app/Models/AmapiDevice.php` | Modèle de suivi des devices AMAPI |

## Politiques AMAPI

### default_policy (utilisation normale — COPE)

```php
[
    // === Work Profile (indispensable pour COPE) ===
    'workProfilePolicy' => [
        'enabled' => true,
    ],
    'ensureVerificationAgent' => true,

    // === Applications ===
    'applications' => [
        [
            'packageName' => 'com.trueline.mdm',
            'installType' => 'FORCE_INSTALLED',
            'defaultPermissionPolicy' => 'GRANT',
        ],
    ],

    // === Sécurité ===
    'factoryResetDisabled' => true,        // Reset interdit (y compris espace perso)
    'safeBootDisabled' => true,            // Mode sans échec interdit
    'installUnknownSourcesAllowed' => false, // APKs tierces interdites
    'debuggingFeaturesAllowed' => false,    // ADB/debug interdit
    'uninstallAppsDisabled' => true,        // Désinstallation app MDM interdite

    // === Play Store ===
    'playStoreMode' => 'BLACKLIST',

    // === Mises à jour ===
    'appAutoUpdatePolicy' => 'ALWAYS',
    'systemUpdate' => ['type' => 'AUTOMATIC'],

    // === FRP (Factory Reset Protection) ===
    'frpAdminEmails' => ['etstrueline@gmail.com'],

    // === Localisation ===
    'locationMode' => 'HIGH_ACCURACY',
]
```

### locked_policy (verrouillage device-wide)

```php
[
    // === Application vitrine (seule app visible) ===
    'applications' => [
        [
            'packageName' => 'com.trueline.mdm',
            'installType' => 'FORCE_INSTALLED',
            'defaultPermissionPolicy' => 'GRANT',
        ],
        [
            'packageName' => 'com.android.vending',
            'installType' => 'BLOCKED',
        ],
    ],

    // === Kiosk (écran d'accueil verrouillé) ===
    'kioskCustomLauncherEnabled' => true,
    'kioskCustomization' => [
        'powerButtonActions' => 'POWER_BUTTON_BLOCKED',
        'systemErrorWarnings' => 'ERROR_AND_WARNINGS_ENABLED',
        'systemNavigation' => 'NAVIGATION_DISABLED',
        'statusBar' => 'NOTIFICATIONS_AND_SYSTEM_INFO_DISABLED',
        'deviceSettings' => 'SETTINGS_ACCESS_BLOCKED',
    ],

    // === Blocage espace perso ===
    'usagesDisabled' => true,  // ← CLÉ : bloque l'accès aux apps persos

    // === Sécurité maximale ===
    'factoryResetDisabled' => true,
    'safeBootDisabled' => true,
    'debuggingFeaturesAllowed' => false,
    'installUnknownSourcesAllowed' => false,
    'installAppsDisabled' => true,
    'uninstallAppsDisabled' => true,
    'addUserDisabled' => true,
    'removeUserDisabled' => true,
    'modifyAccountsDisabled' => true,
    'mountPhysicalMediaDisabled' => true,
    'vpnConfigDisabled' => true,
    'setWallpaperDisabled' => true,
    'funDisabled' => true,

    // === Play Store ===
    'playStoreMode' => 'WHITELIST',

    // === Mises à jour ===
    'appAutoUpdatePolicy' => 'ALWAYS',
    'systemUpdate' => ['type' => 'AUTOMATIC'],

    // === FRP ===
    'frpAdminEmails' => ['etstrueline@gmail.com'],

    // === Localisation ===
    'locationMode' => 'HIGH_ACCURACY',
]
```

## Sécurité — Protection contre le factory reset

### Menaces identifiées

| Menace | Protection | Statut |
|---|---|---|
| Reset via Settings | `factoryResetDisabled: true` | ✅ Implémenté |
| Reset hardware (boutons physiques) | FRP (Factory Reset Protection) | ⚠️ Partiel |
| Mode sans échec | `safeBootDisabled: true` | ✅ Implémenté |
| ADB / USB debugging | `debuggingFeaturesAllowed: false` | ✅ Implémenté |
| APKs tierces | `installUnknownSourcesAllowed: false` | ✅ Implémenté |
| Désinstallation app MDM | `uninstallAppsDisabled: true` | ✅ Implémenté |
| Accès aux Settings | `kioskCustomization.deviceSettings: SETTINGS_ACCESS_BLOCKED` | ✅ Implémenté |

### Protection FRP (Factory Reset Protection)

Même en cas de reset hardware forcé (boutons power + volume) :
1. Le téléphone redémarre
2. L'écran FRP s'affiche — demande le compte `etstrueline@gmail.com`
3. Sans ce compte, le téléphone reste **inutilisable**
4. Le device ne peut pas être revendu ou réutilisé

**Note** : Le FRP n'est pas infaillible (selon la version Android, des failles existent). C'est un ralentissement, pas une barrière totale.

### Renforcements supplémentaires possibles

```php
// Dans default_policy
'settingsChangesDisabled' => true,  // Empêche les changements de settings

// Dans locked_policy
'powerButtonActions' => 'POWER_BUTTON_BLOCKED',  // Bouton power désactivé en kiosk
'outgoingCallsDisabled' => true,   // Appels bloqués
'smsDisabled' => true,             // SMS bloqués
```

## Implémentation — Ce qui manque

### 1. Adapter default_policy en COPE

Ajouter dans `getDefaultPolicyConfig()` :
```php
'workProfilePolicy' => ['enabled' => true],
'ensureVerificationAgent' => true,
'debuggingFeaturesAllowed' => false,  // manquant dans default
```

### 2. Adapter locked_policy pour COPE

Remplacer/ajouter :
```php
'kioskCustomLauncherEnabled' => true,
'usagesDisabled' => true,  // ← bloque l'espace perso
'kioskCustomization' => [
    'powerButtonActions' => 'POWER_BUTTON_BLOCKED',
    'systemNavigation' => 'NAVIGATION_DISABLED',
    'statusBar' => 'NOTIFICATIONS_AND_SYSTEM_INFO_DISABLED',
    'deviceSettings' => 'SETTINGS_ACCESS_BLOCKED',
],
```

### 3. Implémenter RELINQUISH_OWNERSHIP

Dans `AMAPIClientService`, ajouter une méthode dédiée :
```php
public function relinquishOwnership(Device $device, string $reason, ?int $userId = null): bool
{
    // PATCH vers l'API AMAPI avec :
    // 'command' => 'RELINQUISH_OWNERSHIP'
    // Cela supprime le work profile et conserve les données perso
}
```

**Important** : Ne pas confondre avec `deleteDevice()` qui fait un `DELETE` HTTP (wipe factory reset).

### 4. Mettre à jour generateProvisioningQRCode

Le provisioning doit utiliser la policy COPE (avec work profile) :
```php
'policyName' => "enterprises/{$this->enterpriseId}/policies/default_policy",
// La default_policy doit maintenant contenir workProfilePolicy
```

## Résumé des choix techniques

| Décision | Choix | Justification |
|---|---|---|
| Mode de management | COPE | Contrôle total + données perso possibles |
| Policy enrollment | default_policy + workProfile | Work profile pour espace perso |
| Policy lock | locked_policy + kiosk + usagesDisabled | Bloque tout (perso + travail) |
| Libération device | RELINQUISH_OWNERSHIP | Conserve les données perso |
| Protection reset | factoryResetDisabled + FRP | Double couche de sécurité |
