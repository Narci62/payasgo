<div align="center">

# PayasGo

**Financement de smartphones — acheter maintenant, payer plus tard**

API REST Laravel + panel d'administration Filament

</div>

---

## Table des matières

1. [Présentation](#1-présentation)
2. [Stack technique](#2-stack-technique)
3. [Prérequis](#3-prérequis)
4. [Installation](#4-installation)
5. [Configuration](#5-configuration)
6. [Architecture du dépôt](#6-architecture-du-dépôt)
7. [Modèle de données](#7-modèle-de-données)
8. [Authentification et guards](#8-authentification-et-guards)
9. [API REST](#9-api-rest)
10. [Routes web](#10-routes-web)
11. [Panel d'administration Filament](#11-panel-dadministration-filament)
12. [Cycle de financement](#12-cycle-de-financement)
13. [Android Management API (AMAPI)](#13-android-management-api-amapi)
14. [FedaPay](#14-fedapay)
15. [File d'attente et notifications](#15-file-dattente-et-notifications)
16. [Commandes artisan et planification](#16-commandes-artisan-et-planification)
17. [Tests, style et déploiement](#17-tests-style-et-déploiement)

---

## 1. Présentation

PayasGo est une plateforme de **crédit téléphonique** : un distributeur vend un smartphone
au client, encaisse un acompte, et le solde est remboursé par échéances. En cas de
défaut de paiement, l'appareil reste pilotable à distance.

Le produit repose sur deux briques :

- **Un panel d'administration** (Filament) pour les équipes commerciales et le support :
  gestion des clients, des appareils, des plans de financement, du stock et des rôles.
- **Une API REST** consommée par l'application mobile Android du client et par les
  intégrations internes.

La garantie repose sur l'**Android Management API** (Google AMAPI) : chaque
appareil vendu est enrôlé dans l'enterprise Google de PayasGo, ce qui permet de le
verrouiller, de le déverrouiller ou de lui en céder la propriété selon l'état du
financement.

Devise : **XOF (FCFA)**. Marché francophone (Sénégal, Côte d'Ivoire, Burkina Faso…).

---

## 2. Stack technique

| Couche | Technologie |
|---|---|
| Framework | Laravel 12 |
| Langage | PHP 8.3 |
| Panel d'administration | Filament 4 |
| Authentification API | Laravel Sanctum 4 |
| Authentification panel | Session + Spatie Permission 6 |
| Paiement | FedaPay (`fedapay/fedapay-php ^0.4.7`) |
| Gestion d'appareils | Google Android Management API (`google/apiclient`) |
| Push | FCM (`laravel-notification-channels/fcm`) |
| QR codes | `simplesoftwareio/simple-qrcode` |
| Médias | `spatie/laravel-medialibrary` |
| Export PDF | `barryvdh/laravel-dompdf` |
| Front (assets) | Vite 7 + Tailwind CSS 4 |
| Tests | PHPUnit 11 |
| Style PHP | Laravel Pint |

---

## 3. Prérequis

- **PHP 8.3** avec les extensions `pdo_mysql`, `mbstring`, `openssl`, `curl`, `zip`, `gd`
- **Composer 2**
- **Node.js 20+** et npm (uniquement pour compiler les assets)
- **MySQL 8** (les tests utilisent SQLite en mémoire)
- Un projet **Google Cloud** avec l'**Android Management API** activée, et un compte de
  service disposant du rôle *Android Management Admin*
- Un compte **FedaPay** (live et/ou sandbox)

---

## 4. Installation

```bash
# 1. Dépendances PHP
composer install

# 2. Dépendances front
npm install

# 3. Environnement
cp .env.example .env
php artisan key:generate

# 4. Base de données
php artisan migrate
php artisan db:seed          # rôles, permissions et administrateur

# 5. Stockage public (documents clients, photos de garant, APK)
php artisan storage:link

# 6. Assets
npm run build
npm run filament:theme        # CSS du panel Filament
```

### Bootstrap AMAPI

Les étapes suivantes créent les objets côté Google. Elles **appellent l'API AMAPI** :
ne les lancez que sur un projet Google de test au premier réglage.

```bash
php artisan amapi:enable               # vérifie que l'API Android Management est active
php artisan amapi:create-enterprise    # crée l'enterprise et affiche l'AMAPI_ENTERPRISE_ID
php artisan amapi:create-policies      # crée default_policy, cope_policy, locked_policy
php artisan amapi:test                 # vérifie l'authentification du compte de service
```

Copiez ensuite dans `.env` les valeurs affichées :

```dotenv
AMAPI_ENTERPRISE_ID=enterprises/XXXXXXXX
AMAPI_POLICY_DEFAULT=default_policy
AMAPI_POLICYCOPE=cope_policy
AMAPI_POLICY_LOCKED=locked_policy
```

### Développement

```bash
composer dev
```

Lance en parallèle `artisan serve`, `artisan queue:listen`, `artisan pail` et `npm run dev`.

Le panel est accessible sur **`/admin`**, l'API sur **`/api`**, la sonde de santé sur `/up`.

---

## 5. Configuration

### Application

| Variable | Défaut | Rôle |
|---|---|---|
| `APP_NAME` | `Laravel` | Nom affiché |
| `APP_ENV` | `production` | Environnement |
| `APP_KEY` | — | **À générer**, signature des cookies et sessions |
| `APP_DEBUG` | `false` | Ne jamais activer en production |
| `APP_URL` | `http://localhost` | URL publique, utilisée par les callbacks FedaPay |
| `API_URL` | `http://127.0.0.1:8000/api` | URL de l'API remontée au client |
| `APP_LOCALE` | `fr` | Interface et messages en français |

### Base de données, cache, session, file d'attente

| Variable | Défaut | Rôle |
|---|---|---|
| `DB_CONNECTION` | `sqlite` | **Utiliser `mysql` en développement et en production** |
| `DB_HOST` / `DB_PORT` | `127.0.0.1` / `3306` | Serveur MySQL |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | — | Identifiants |
| `CACHE_STORE` | `database` | Table `cache` |
| `SESSION_DRIVER` | `database` | Table `sessions` — requise pour Filament |
| `QUEUE_CONNECTION` | `database` | Table `jobs` — requise pour les rappels et notifications |

> `config/database.php` retombe sur SQLite par défaut. Suivez `.env.example` et positionnez
> explicitement `DB_CONNECTION=mysql`.

### FedaPay

| Variable | Rôle |
|---|---|
| `FEDAPAY_L_PUBLIC_KEY` | Clé publique (live) |
| `FEDAPAY_L_PRIVATE_KEY` | Clé secrète (live) |
| `FEDAPAY_WEBHOOK_SIGNATURE_KEY` | Clé de signature du webhook (live) |
| `FEDAPAY_T_PUBLIC_KEY` | Clé publique (sandbox) |
| `FEDAPAY_T_PRIVATE_KEY` | Clé secrète (sandbox) |
| `FEDAPAY_WEBHOOK_SIGNATURE_SANDBOX_KEY` | Clé de signature du webhook (sandbox) |

Le contrôleur `FedapayWebhookController` initialise le SDK FedaPay sur la configuration
`services.fedapay`, dont le mode est fixé à `live` dans `config/services.php`. Le bloc
`services.fedapayT` et la variable `FEDAPAY_MODE` y sont déclarés mais ne sont pas lus par
le code applicatif.

### Android Management API

| Variable | Défaut | Rôle |
|---|---|---|
| `AMAPI_BASE_URL` | `https://androidmanagement.googleapis.com/v1` | Racine de l'API |
| `AMAPI_PROJECT_ID` | — | Projet Google Cloud |
| `AMAPI_ENTERPRISE_ID` | — | Ressource `enterprises/XXXX` |
| `AMAPI_SERVICE_ACCOUNT_JSON` | `app/public/trueline-payguard-amapi-556ed97a2e37.json` | Chemin du JSON du compte de service, relatif à `storage/` |
| `AMAPI_POLICY_DEFAULT` | `default_policy` | Policy appliquée aux appareils Fully Managed |
| `AMAPI_POLICYCOPE` | `cope_policy` | Policy appliquée aux appareils COPE |
| `AMAPI_POLICY_LOCKED` | `locked_policy` | Policy appliquée lors d'un verrouillage |
| `AMAPI_WEBHOOK_URL` | — | Callback à déclarer chez Google |
| `AMAPI_WEBHOOK_SECRET` | — | Secret de signature des webhooks entrants |

Le JSON du compte de service est lu par `AMAPIClientService::getAccessToken()`, qui
demande un jeton OAuth2 sur le scope `.../auth/androidmanagement`. Le fichier doit être
présent dans `storage/app/public/` et **ne doit jamais être commité**.

### Divers

| Variable | Rôle |
|---|---|
| `CRON_SECRET` | Secret attendu dans l'en-tête `X-CRON-SECRET` des routes `/cron/*` |
| `PAYMENT_LINK` | Lien de paiement affiché dans les ressources API |
| `SANCTUM_TOKEN_PREFIX` | Préfixe des tokens d'API, vide par défaut |

---

## 6. Architecture du dépôt

```
app/
├── Console/Commands/    9 commandes artisan (AMAPI, devices, rappels)
├── Filament/
│   ├── Pages/           Dashboard, SalesReport
│   ├── Resources/       Resources Filament + Schemas/Tables
│   └── Widgets/         Widgets du tableau de bord
├── Helpers/             Helper.php (QR, tokens), PermissionHelper.php
├── Http/
│   ├── Controllers/     Api\ (clients, appareils, FedaPay) + AMAPI
│   ├── Middleware/      AdminAuthMiddleware, DeviceAuthMiddleware
│   ├── Requests/        Form Requests
│   └── Resources/       Resources de sérialisation JSON
├── Models/              15 modèles métier
├── Notifications/       FcmPushNotification, AmapiSyncFailedNotification
├── Observers/           DeviceObserver (décrémente le stock)
├── Policies/            ClientPolicy, DevicePolicy, … (Spatie Permission)
└── Services/            Logique métier (voir §12 et §13)

routes/                  api.php, web.php, console.php
config/                  14 fichiers de configuration
database/                31 migrations, 3 seeders, 2 factories
resources/views/         vues Blade, dont resources/views/vendor/filament
apk/                    app-release.apk — APK managé versionné (ce n'est pas un artefact de build)
```

---

## 7. Modèle de données

### Tables métier

| Table | Rôle |
|---|---|
| `clients` | Acheteurs : identité, référence unique à 5 chiffres, pièce d'identité, garant |
| `garants` | Garant : identité, NPI/CIP, photo de la pièce |
| `phones` | Catalogue : marque, modèle, prix, stock, statut |
| `devices` | Appareils : IMEI, FCM token, statut, `last_seen_at`, `public_id` UUID |
| `registration_tokens` | Jetons d'inscription client, expirables |
| `financing_plans` | Plan de financement : prix, acompte, solde, mensualité, intervalle, échéance suivante, code de déverrouillage |
| `installments` | Échéances : date, montant, reste à payer, statut `pending`/`paid`/`overdue` |
| `penalties` | Pénalités de retard : montant, type (`fixed_5000`, `fixed_10000`, `variable_5pct`), motif |
| `payments` | Paiements : montant, moyen, `transaction_id` unique, statut, date |
| `tauxes` | Barèmes de référence |
| `stock_movements` | Entrées/sorties de stock, rattachées à un utilisateur |
| `amapi_devices` | Miroir AMAPI d'un appareil : `amapi_device_id`, `amapi_state`, `amapi_policy_id`, `enrollment_mode`, `amapi_released_at` |
| `amapi_sync_logs` | Journal des synchronisations AMAPI et de leurs tentatives |
| `device_lock_histories` | Journal d'audit des commandes de verrouillage/déverrouillage/libération |
| `amapi_config` | Table clé/valeur de configuration AMAPI |

### Tables support

`users`, `personal_access_tokens`, `sessions`, `password_reset_tokens`, `cache`,
`cache_locks`, `jobs`, `job_batches`, `failed_jobs`, et les 5 tables Spatie Permission
(`permissions`, `roles`, `model_has_permissions`, `model_has_roles`, `role_has_permissions`).

### Points d'attention sur `amapi_devices`

| Colonne | Rôle |
|---|---|
| `amapi_state` | État renvoyé par Google : `PROVISIONING`, `ACTIVE`, `DISABLED`, `AWAITING_DEVICE_ACTIVATION`, `UNENROLLED`, `DELETED`, `LIBERATED` |
| `amapi_policy_id` | Policy **courante** : elle est réécrite à chaque commande (enrôlement, verrouillage, déverrouillage) |
| `enrollment_mode` | **Mode d'enrôlement** : `FULLY_MANAGED` ou `COPE`. Écrit une seule fois, jamais réécrit |
| `amapi_released_at` | Horodatage de la libération définitive, posé seulement si l'appel AMAPI a réussi |

> `amapi_policy_id` ne dit **pas** le mode d'enrôlement : un appareil COPE verrouillé
> affiche `locked_policy`. Seul `enrollment_mode` est fiable.

---

## 8. Authentification et guards

Trois guards cohabitent (`config/auth.php`) :

| Guard | Driver | Provider | Modèle | Utilisé par |
|---|---|---|---|---|
| `web` | `session` | `users` | `App\Models\User` | Panel Filament (`/admin`) |
| `admin-api` | `sanctum` | `users` | `App\Models\User` | Middleware `auth:admin-api` |
| `device-api` | `sanctum` | `devices` | `App\Models\Device` | Middleware `auth:device-api` + `device.auth` |

### Middleware personnalisés (`bootstrap/app.php`)

| Alias | Comportement |
|---|---|
| `admin.auth` | 401 JSON si l'utilisateur est absent ou si le token ne porte pas l'ability `admin:*` |
| `device.auth` | 401 JSON si l'appareil est absent ou sans ability `device:*` ; **met à jour `devices.last_seen_at`** |

L'accès au panel est filtré par `User::canAccessPanel()`, qui exige l'une des permissions
d'administration (Spatie Permission).

### Émission des tokens

```php
// Token appareil, ability device:*
$device->createToken('device-token', ['device:*'])->plainTextToken;
```

Envoyez le jeton en `Authorization: Bearer <token>` ou en `X-Device-Token`. Les abilities
sont vérifiées par `tokenCan()` dans les middlewares.

---

## 9. API REST

Préfixe `/api`. Format JSON.

| Méthode | Route | Contrôleur | Auth |
|---|---|---|---|
| `GET` | `/api/payasgo` | closure (ping) | — |
| `POST` | `/api/client/register` | `ClientController::store` | — |
| `POST` | `/api/client/financing-plan` | `FinancingPlanController::store` | — |
| `POST` | `/api/users/getuser` | `ClientController::getUserByDeviceToken` | — |
| `POST` | `/api/auth` | `DeviceController::refresh` | — |
| `GET` | `/api/user` | closure | `auth:sanctum` |
| `GET` | `/api/user-profile` | `DeviceController::userProfile` | `auth:device-api`, `device.auth` |
| `GET` | `/api/device/status` | `DeviceStatusController::status` | `auth:device-api`, `device.auth` |
| `POST` | `/api/webhook` | `FedapayWebhookController::webhook` (nom `fedapay.webhook`) | signature FedaPay |
| `POST` | `/api/webhooks/amapi` | `AMAPIWebhookController::handleWebhook` | signature Google |

`device.auth` met à jour `last_seen_at` à chaque appel : c'est ce signal qui alimente la
règle d'inactivité de 14 jours (§12).

### Webhook AMAPI

`POST /api/webhooks/amapi` vérifie la signature du payload puis route selon `eventType` :

| Événement | Traitement |
|---|---|
| `ENROLLMENT` | L'appareil est enregistré côté Google |
| `STATUS_REPORT` | Mise à jour de `last_seen_at` |
| `COMPLIANCE_REPORT` | Journalisation de la conformité |
| `COMMAND_COMPLETED` | Mise à jour du statut de la commande |

---

## 10. Routes web

| Méthode | Route | Rôle |
|---|---|---|
| `GET` | `/` | Redirige vers `/admin` |
| `GET` | `/paiement/{imat?}` | Formulaire de règlement, trouvé le client par sa référence |
| `POST` | `/paiement` | Création de la transaction FedaPay et redirection |
| `GET` | `/regularisation/{imat?}` | Idem, pour le cas « régularisation » |
| `POST` | `/regularisation` | Idem |
| `GET` | `/fedapay/finish` | Callback navigateur, affiche le résultat |
| `GET` | `/amapi` | Callback d'enrôlement AMAPI |
| `GET` | `/generate-signup-url` | Génère l'URL d'inscription Google |
| `GET` | `/information/{imat}` | Fiche client |
| `GET` | `/compliance/{imat}` | État de conformité |
| `GET` | `/cron/sync-data` | Lance `devices:sync-amapi-devices` |
| `GET` | `/cron/verify-all-devices` | Lance `devices:check-lock-status` |

### Routes cron

`/cron/*` exigent l'en-tête `X-CRON-SECRET` contenant exactement la valeur de `CRON_SECRET`,
sinon la requête est rejetée en `403`. Ces routes évitent d'exposer les commandes artisan à
`schedule:run` sur un hébergeur mutualisé.

---

## 11. Panel d'administration Filament

Monté sur **`/admin`**, thème personnalisé `css/filament/admin/theme.css`.

### Ressources

| Ressource | Contenu |
|---|---|
| **Clients** | Fiche client, garant, appareils, plan de financement, QR d'enrôlement |
| **Appareils** | Liste, QR, historique de verrouillage (modale de lecture seule), actions de verrouillage / déverrouillage / libération |
| **Plans de financement** | Échéances, paiements, pénalités |
| **Téléphones** | Catalogue, stock, entrées/sorties |
| **Utilisateurs** | Comptes administrateurs |
| **Rôles** | Rôles & permissions (Spatie) |
| **Permissions** | Liste des permissions |
| **Pénalités** | Consultation, groupe « Statistiques » |

### Pages

- **Tableau de bord** — widgets `PortfolioKpis` (indicateurs du portefeuille),
  `ContractsDevicesOverview` (contrats et appareils) et `AlertsOverview` (alertes).
- **Fiche de vente** — tableau des paiements filtrable par période, export PDF.

### Rôles et permissions

5 rôles préchargés par `RolesAndPermissionsSeeder` :

| Rôle | Périmètre |
|---|---|
| `super-admin` | Accès total, y compris la gestion des rôles |
| `admin` | Tout sauf `manage-roles` et `delete-users` |
| `commercial` | Consultation clients, contrats et ventes |
| `gerant` | Encadrement commercial |
| `support` | Support client et appareils |

23 permissions au format `view|create|edit|delete-{clients,devices,financing-plans,phones,users}`,
plus `manage-roles`, `view-dashboard` et `view-sales-report`. Les libellés français sont
centralisés dans `app/Helpers/PermissionHelper.php`.

---

## 12. Cycle de financement

```
1. Vente          Le commercial enregistre le client, le téléphone et le plan
                  (prix total, acompte, mensualité, intervalle en jours)
        │
2. Enrôlement     Un jeton AMAPI est créé → QR code affiché dans le panel
                  Le client scanne le QR depuis l'écran d'accueil Android
        │
3. Acompte        Enregistré comme paiement « manual » sur le plan
        │
4. Échéancier     Une ligne Installment par échéance, 30 jours par défaut
        │
5. Réglement      FedaPay → webhook → imputation sur l'échéance la plus
                  ancienne non soldée, pénalités déduites en priorité
        │
6. Solde          Plan → paid_in_full → libération de l'appareil (§13)
```

### Règles de verrouillage

`DeviceMonitoringService` décide chaque jour si un appareil doit être verrouillé ou
déverrouillé :

| Règle | Condition | Motif enregistré |
|---|---|---|
| Retard de paiement | L'échéance impayée la plus ancienne est dépassée | `PAYMENT_OVERDUE` |
| Inactivité | Aucune activité depuis 14 jours | `INACTIVITY_14_DAYS` |

Avant de conclure à l'inactivité, le service **resynchronise l'appareil depuis AMAPI** dès
12 jours d'absence : `last_seen_at` peut être à jour côté Google alors que l'appareil n'a
pas encore rappelé notre API. En cas d'échec de cet appel, la décision se rabat sur la
donnée locale.

### Barème des pénalités

Appliqué par `PenaltyService` sur l'échéance en retard :

| Palier | Retard | Montant |
|---|---|---|
| 1 | 1 à 7 jours | 0 FCFA |
| 2 | 8 à 15 jours | 5 000 FCFA forfaitaires |
| 3 | 16 jours et plus | 10 000 FCFA par bloc de 30 jours **+** 5 % du montant de l'échéance par cycle de 14 jours |

### Imputation d'un paiement

`FinancingPlanService::savePayment()` parcourt les échéances de la plus ancienne à la plus
récente, solde les pénalités en premier, puis le capital. Le solde du plan, l'échéance
suivante et le statut de l'appareil sont recalculés à chaque fois. Un paiement reçu solde le
plan → l'appareil est alors libéré.

---

## 13. Android Management API (AMAPI)

### Politiques

Trois politiques sont créées chez Google par `amapi:create-policies` :

| Policy | Usage |
|---|---|
| `default_policy` | Appareil Fully Managed en fonctionnement normal |
| `cope_policy` | Appareil COPE en fonctionnement normal |
| `locked_policy` | Appareil verrouillé — appliquée aux **deux** modes |

### Modes d'enrôlement

| Mode | Propriété | Comportement | Libération |
|---|---|---|---|
| **Fully Managed** | Google | L'appareil reste un terminal géré | Suppression de l'enterprise |
| **COPE** (Customer-Owned, Person-Owned) | Le client | Le client peut retrouver son appareil après une réinitialisation | Cession via `RELINQUISH_OWNERSHIP` |

Le mode par défaut au provisionnement est **COPE**. Il est enregistré une fois pour toutes
dans `amapi_devices.enrollment_mode` et sert de source de vérité pour toutes les décisions
ultérieures.

### Cycle de commandes

| Opération | Appel AMAPI | Policy appliquée | Effet |
|---|---|---|---|
| Provisionnement | `POST /enrollmentTokens` | `cope_policy` ou `default_policy` selon le mode | `amapi_state = PROVISIONING` |
| Verrouillage | `PATCH /devices/{id}` + `state: DISABLED` | `locked_policy` | `amapi_state = DISABLED`, `devices.status = locked` |
| Déverrouillage | `PATCH /devices/{id}` + `state: ACTIVE` | `cope_policy` (COPE) ou `default_policy` (FM) | `amapi_state = ACTIVE`, `devices.status = active` |
| Libération COPE | `PATCH` + `commands: [RELINQUISH_OWNERSHIP]` | policy conservée | `amapi_state = LIBERATED` |
| Libération FM | `DELETE /devices/{id}` | — | appareil retiré de l'enterprise |

Le déverrouillage choisit sa policy à partir de `enrollment_mode` et **enregistre en base
exactement la policy envoyée à Google**, ce qui évite toute divergence entre les deux
référentiels lors des synchronisations suivantes.

### Routage de la libération

`AMAPIClientService::releaseDevice()` est le point d'entrée unique de la libération, appelé
depuis le paiement, le retry de synchronisation et le panel :

```php
$released = $amapiDevice->isCopeEnrolled()
    ? $this->relinquishOwnership($device, $reason, $userId)
    : $this->deleteDevice($device, $reason, $userId);

if ($released) {
    $amapiDevice->update(['amapi_released_at' => now()]);
}
```

`amapi_released_at` n'est posé que si l'appel AMAPI a réussi. Comme la suppression d'un
appareil Fully Managed laisse sa ligne dans son état antérieur, cet horodatage est le seul
marqueur fiable de libération — sans lui, `Device::isLiberated()` resterait faux et l'action
« Désinstaller AMAPI » resterait proposée en boucle dans le panel.

`Device::isLiberated()` est vrai quand le plan est soldé **et** que l'appareil n'est plus
sous contrôle AMAPI, c'est-à-dire :

```php
$amapiDevice->isReleased()   // amapi_released_at non nul, ou amapi_state = LIBERATED
```

### Synchronisation

| Méthode | Rôle |
|---|---|
| `syncAMAPIDevices()` | Liste les appareils de l'enterprise, rapproche les `amapi_device_id` via l'`enrollmentTokenData`, met à jour l'état |
| `syncDeviceStatus($device)` | Rafraîchit l'état et les métadonnées d'un appareil, et aligne `last_seen_at` sur `lastStatusReportTime` |

Google préfixe ses énumérations par `STATE_` (`STATE_ACTIVE`, …). `normalizeState()`
retire ce préfixe et **conserve la valeur précédente** si le résultat ne correspond à aucun
état connu : une réponse inattendue ne peut donc pas corrompre la base.

### Journal d'audit

`device_lock_histories` conserve chaque commande : action, motif, statut
(`PENDING`/`SUCCESS`/`FAILED`), solde restant, jours de retard, auteur, `amapi_command_id`
et horodatage d'exécution. L'historique est consultable en lecture seule depuis la fiche
d'un appareil dans le panel.

Références complémentaires : **`amapi.md`** (architecture et choix) et **`amapi_cope.md`**
(migration COPE) à la racine du dépôt.

---

## 14. FedaPay

Flux de règlement, en XOF :

1. **Formulaire** — `GET /paiement/{imat}` retrouve le client par sa référence, son dernier
   appareil et son plan non soldé, puis affiche l'échéance courante et le détail des
   pénalités.
2. **Création de la transaction** — `POST /paiement` crée une `Transaction` FedaPay avec une
   `callback_url` vers `/fedapay/finish`, enregistre un `Payment` en statut `pending` et
   redirige vers la page de paiement FedaPay.
3. **Retour navigateur** — `/fedapay/finish` affiche le résultat à l'utilisateur. Cette
   page est purement informative : elle ne modifie rien en base.
4. **Webhook serveur** — `POST /api/webhook` fait foi. La signature est vérifiée via
   `FedaPay\Webhook::constructEvent()` à partir de l'en-tête `X-FEDAPAY-SIGNATURE` et de la
   clé `FEDAPAY_WEBHOOK_SIGNATURE_KEY`. Les événements autres que `transaction.approved` sont
   ignorés. La transaction est ensuite retrouvée par `transaction->reference`, les pénalités
   sont calculées, puis `FinancingPlanService::savePayment()` impute le paiement.

**Déclarez `POST /api/webhook` comme URL de webhook dans votre tableau de bord FedaPay.**
C'est la seule route qui déclenche l'imputation.

`PaymentService` protège contre les doublons via l'unicité de `payments.transaction_id` :
un webhook rejoué ne crédite pas deux fois le plan.

---

## 15. File d'attente et notifications

| Élément | Détail |
|---|---|
| Driver | `database` (table `jobs`) |
| Écoute en développement | `php artisan queue:listen` (inclus dans `composer dev`) |
| En production | `php artisan queue:work` via le planificateur de l'hébergeur, ou `/cron/*` |
| Jobs en échec | Table `failed_jobs`, liste avec `php artisan queue:failed` |

### Notifications

- **FCM** — `FcmPushNotification` utilise les `fcm_token` stockés sur les appareils.
  `Device::routeNotificationForFcm()` expose `getDeviceTokens()`, qui retourne le ou les
  tokens enregistrés pour l'appareil. Le rappel de paiement est envoyé à J-5 et J-1 de
  l'échéance (`app:send-payment-reminders`).
- **Échecs AMAPI** — `AmapiSyncFailedNotification` est un e-mail mis en file d'attente,
  envoyé après épuisement des tentatives de synchronisation.

---

## 16. Commandes artisan et planification

### Android Management API

| Commande | Rôle |
|---|---|
| `amapi:enable` | Vérifie que l'API Android Management est active sur le projet |
| `amapi:create-enterprise {--name=}` | Crée l'enterprise Google |
| `amapi:create-policies` | Crée `default_policy`, `cope_policy` et `locked_policy` |
| `amapi:test` | Vérifie l'authentification du compte de service |
| `amapi:retry-failed-syncs {--max-attempts=3}` | Rejoue les synchronisations en échec |

### Appareils

| Commande | Rôle |
|---|---|
| `devices:sync-amapi-devices` | Synchronise tout le parc avec l'enterprise Google |
| `devices:check-lock-status` | Applique les règles de verrouillage et de déverrouillage |

### Métier

| Commande | Rôle |
|---|---|
| `app:send-payment-reminders` | Rappels FCM à J-5 et J-1 |

### Planification

| Commande | Fréquence |
|---|---|
| `devices:sync-amapi-devices` | Toutes les heures, sans chevauchement |
| `devices:check-lock-status` | Tous les jours à 00:00, sans chevauchement |
| `app:send-payment-reminders` | Tous les jours à 09:00 |
| `media-library:delete-old-temporary-uploads` | Tous les jours |

```bash
php artisan schedule:list      # vérifier
php artisan schedule:run       # exécution manuelle
```

Sur un hébergeur mutualisé sans cron système, déclenchez `/cron/sync-data` et
`/cron/verify-all-devices` depuis le planificateur de l'hébergeur, avec l'en-tête
`X-CRON-SECRET`.

---

## 17. Tests, style et déploiement

### Tests

```bash
composer test                              # vide le cache de config puis lance la suite
php artisan test
php artisan test --filter=DeviceReleaseTest
```

`phpunit.xml` surcharge `DB_CONNECTION=sqlite` et `DB_DATABASE=:memory:` : la suite ne
touche jamais la base de développement. `tests/TestCase.php` applique `RefreshDatabase`.

**Aucun test ne doit atteindre le vrai service AMAPI.** Les tests de `DeviceReleaseTest`
surchargent `getAccessToken()` pour renvoyer un jeton fictif et interceptent le reste avec
`Http::fake()`, ce qui permet d'observer les requêtes envoyées sans appel réseau.

| Suite | Couverture |
|---|---|
| `DeviceReleaseTest` | `AMAPIClientService` : verrouillage, déverrouillage, libération FM/COPE, provisionnement, normalisation des états |
| `DeviceLockHistoryTest` | Modale d'historique de verrouillage |
| `DashboardServiceTest` / `DashboardWidgetsTest` | Agrégats et rendu des widgets |
| `ManualPaymentTest` | Imputation manuelle des paiements et création d'échéances |
| `RoleManagementTest` / `PermissionManagementTest` | Rôles et permissions |
| `PermissionHelperTest` | Libellés des permissions |

### Style de code

```bash
vendor/bin/pint            # formater
vendor/bin/pint --test     # vérifier sans écrire
```

- 4 espaces, fins de ligne LF (`.editorconfig`)
- Aucun commentaire superflu
- **Ne jamais modifier une migration existante** : toute évolution de schéma passe par un
  nouveau fichier de migration
- Après toute modification de Blade ou de Tailwind : `npm run filament:theme`, et
  `public/css/filament/admin/theme.css` doit rester versionné

### CI/CD

| Workflow | Déclencheur | Rôle |
|---|---|---|
| `.github/workflows/ci.yml` | push et PR sur `main` / `develop` | Tests sur SQLite |
| `.github/workflows/cd.yml` | push sur `main` | Déploiement FTP sur LWS cPanel, secrets `CPANEL_TRUELINE_*` |
| `.github/workflows/test_cd.yml` | push sur `develop` | Déploiement vers l'environnement de test, secrets `CPANEL_*_TEST` |

`composer install` déclenche `artisan filament:upgrade` via `post-autoload-dump`.

### Documentation complémentaire

| Fichier | Contenu |
|---|---|
| `AGENTS.md` | Consignes de travail de l'assistant, gotchas du projet |
| `amapi.md` | Architecture AMAPI, choix techniques, sécurité anti-factory-reset |
| `amapi_cope.md` | Migration vers le mode COPE |
