# AGENTS.md — PayasGo API

## Project

Laravel 12 REST API + Filament 4 admin panel for device financing (buy-now-pay-later for phones). Integrates FedaPay (payments), Google Android Management API (AMAPI — device lock/unlock), FCM, and QR code enrollment.

## Requirements

- PHP 8.3
- Composer
- Node.js (for Vite frontend build)
- MySQL (production/local) — tests use SQLite in-memory

## Commands

```bash
composer dev          # runs: artisan serve + queue:listen + pail + npm run dev concurrently
composer test         # clears config cache, then runs artisan test (PHPUnit)

php artisan test      # run all tests
php artisan test --filter=TestClassName      # single test class
php artisan test --filter=TestClassName::testMethodName  # single test

php artisan migrate   # run migrations
php artisan db:seed   # seed database
```

Tests use an **in-memory SQLite** database (`phpunit.xml` overrides `DB_CONNECTION` and `DB_DATABASE`). The `.env.testing` file exists but is not auto-loaded by phpunit.xml — the XML env overrides take precedence.

`app/Helpers/Helper.php` is **auto-loaded in tests** via `composer.json` `autoload-dev.files`. It is not auto-loaded in production.

## Architecture

### Auth guards (config/auth.php)

| Guard | Driver | Provider | Model | Used by |
|---|---|---|---|---|
| `web` | session | users | User | Filament admin panel |
| `admin-api` | sanctum | users | User | `auth:admin-api` middleware |
| `device-api` | sanctum | devices | Device | `auth:device-api` middleware |

Custom middleware aliases registered in `bootstrap/app.php`:
- `admin.auth` → checks `tokenCan('admin:*')`
- `device.auth` → checks `tokenCan('device:*')` and updates `last_seen_at`

### Key directories

- `app/Filament/` — Admin panel (Resources: Clients, Devices, FinancingPlans, Phones; Pages: Dashboard, SalesReport)
- `app/Services/` — Business logic (AMAPIClientService, ClientService, DeviceService, FinancingPlanService, PaymentService, etc.)
- `app/Console/Commands/` — Artisan commands including `devices:sync-amapi-devices` and `devices:check-lock-status`
- `routes/api.php` — Public + authenticated API routes
- `routes/web.php` — Payment forms, AMAPI callbacks, cron endpoints
- `apk/` — Contains `app-release.apk` (managed APK, not a build artifact)

### External services

- **FedaPay**: Two configurations — `fedapay` (live) and `fedapayT` (sandbox). Controlled by `FEDAPAY_MODE` env.
- **AMAPI**: Google Android Management API. Service account JSON expected at `storage/app/public/`. Config in `config/services.php` under `amapi`.
- **CRON_SECRET**: Webhook/cron routes (`/cron/*`) are protected by `X-CRON-SECRET` header matching `env('CRON_SECRET')`.

## Gotchas

- **CSRF exception**: Only `api/webhooks/fedapay` is excluded from CSRF in `bootstrap/app.php`. AMAPI webhook (`/webhooks/amapi`) is **not** excluded — it may need CSRF bypass if POST requests fail.
- **Filament admin panel** runs on session auth at `/admin`, separate from API token auth. Don't confuse the two.
- **CD workflow** generates an `artisan-commands.sh` file but does not execute it — artisan cache/migrate commands are not actually run on the server.
- **`apk/` directory** contains a release APK tracked in git — it's not a build output, don't delete it.
- Database defaults to `sqlite` in `config/database.php` but `.env.example` sets MySQL — follow `.env.example` for local dev.
- `filament:upgrade` runs on every `composer install` via post-autoload-dump script.
- `media-library:delete-old-temporary-uploads` is scheduled daily (in `bootstrap/app.php`).

## Code style

- 4 spaces indentation, LF line endings (`.editorconfig`)
- PHP-CS-Fixer via **Laravel Pint** (`vendor/bin/pint`)
- No comments unless asked — follow existing patterns
- French appears in some code comments and user-facing strings (this is a French-market product)
