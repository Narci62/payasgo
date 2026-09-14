# AMAPI — Migration COPE

> Dernière mise à jour : 2026-09-01

## Contexte

Passage de **Fully Managed (Device Owner)** à **COPE (Corporate-Owned, Personally-Enabled)** pour les futurs enrollments AMAPI. Les devices existants restent en Fully Managed.

**Règle Google** : on ne peut PAS convertir un device FM en COPE sans factory reset. Seuls les **nouveaux enrollments** peuvent être en COPE.

## Ce qui a été fait

### Code (livré)

| Fichier | Changement |
|---|---|
| `config/services.php` | Ajout `cope` dans `policies` |
| `app/Console/Commands/CreateAMAPIPolicies.php` | Ajout `getCopePolicyConfig()` + `createCopePolicy()`. Fix `debuggingFeaturesAllowed` → `false`. Ajout `usagesDisabled` dans `locked_policy`. |
| `app/Services/AMAPIClientService.php` | `generateProvisioningQRCode()` → `cope_policy`. Ajout `relinquishOwnership()`. |

### Politique COPE créée

```json
{
  "applications": [{ "packageName": "com.trueline.mdm", "installType": "FORCE_INSTALLED", "defaultPermissionPolicy": "GRANT" }],
  "workProfilePolicy": {
    "workProfileWidgetsEnabled": false,
    "crossProfileCallerIdDisabled": true,
    "crossProfileContactsSearchDisabled": true,
    "showWorkContactsInPersonalContacts": false
  },
  "ensureVerificationAgent": true,
  "playStoreMode": "BLACKLIST",
  "factoryResetDisabled": true,
  "debuggingFeaturesAllowed": false,
  "uninstallAppsDisabled": true,
  "frpAdminEmails": ["etstrueline@gmail.com"],
  "appAutoUpdatePolicy": "ALWAYS",
  "locationMode": "HIGH_ACCURACY",
  "systemUpdate": { "type": "AUTOMATIC" }
}
```

## Étapes restantes

### 1. Serveur (Production)

```bash
# Créer la cope_policy dans AMAPI
php artisan amapi:create-policies

# Vérifier dans le .env
AMAPI_POLICYCOPE=cope_policy
```

### 2. Ajouter `LIBERATED` à l'enum `amapi_state`

Optionnel. Le string fonctionne déjà, mais un cast enum propre est recommandé.

```php
// Migration ou dans AmapiDevice::class
'amapi_state' => ['ACTIVE', 'DISABLED', 'PROVISIONING', 'UNENROLLED', 'DELETED', 'LIBERATED'],
```

### 3. Interface admin — bouton "Liberate device"

Ajouter un bouton dans la page Device ou le DeviceResource Filament :

```php
Action::make('relinquishOwnership')
    ->label('Liberate device')
    ->requiresConfirmation()
    ->action(fn (Device $record) => app(AMAPIClientService::class)->relinquishOwnership($record, 'admin_liberation', auth()->id()))
```

### 4. Nettoyage debug `default_policy` (existant)

`debuggingFeaturesAllowed` est passé de `true` à `false`. À appliquer sur le serveur :

```bash
php artisan amapi:create-policies
```

Cela met à jour la `default_policy` existante avec `debuggingFeaturesAllowed: false`.

## Comportement des policies

| Policy | Cible | Work Profile | usagesDisabled | État device |
|---|---|---|---|---|
| `default_policy` | Devices FM existants | Non | Non | ACTIVE |
| `cope_policy` | Futurs enrollments | Oui | Non | ACTIVE |
| `locked_policy` | Verrouillage (les deux) | Non (neutre sur FM) | Oui (bloque sur COPE) | DISABLED |

## FAQ

**Q : Les devices FM existants vont-ils être impactés ?**
Non. La `default_policy` n'est modifiée que pour `debuggingFeaturesAllowed: false`.

**Q : Un device FM peut-il devenir COPE ?**
Non, sauf factory reset + ré-enrôlement. C'est une limitation Google.

**Q : `RELINQUISH_OWNERSHIP` fonctionne sur FM ?**
Oui. C'est une commande AMAPI qui retire le Device Owner. Le device passe en mode non géré.

**Q : Le work profile est-il visible sur FM ?**
Non. `workProfilePolicy` est ignoré sur les devices FM. Il n'a d'effet que sur COPE.
Prochaines étapes (pour plus tard)
1. Lancer php artisan amapi:create-policies sur le serveur pour créer la cope_policy dans AMAPI
2. Ajouter .env : AMAPI_POLICYCOPE=cope_policy
3. Ajouter LIBERATED à la migration amapi_devices si vous voulez un cast enum strict (optionnel, le string fonctionne)
4. Dans l'interface admin, ajouter un bouton "Liberate device" qui appelle relinquishOwnership() (fin de cycle client)
