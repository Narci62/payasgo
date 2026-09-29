<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ORIGINAL_STATES = ['ACTIVE', 'DISABLED', 'DELETED', 'PROVISIONING'];

    private const STATES = [
        'ACTIVE',
        'DISABLED',
        'DELETED',
        'PROVISIONING',
        'LIBERATED',
        'UNENROLLED',
        'AWAITING_DEVICE_ACTIVATION',
    ];

    public function up(): void
    {
        if ($this->isSqlite()) {
            $this->rebuildTableForSqlite(self::STATES, withEnrollmentMode: true);
        } else {
            Schema::table('amapi_devices', function (Blueprint $table) {
                $table->enum('amapi_state', self::STATES)->change();
            });

            $this->addEnrollmentModeColumn();
        }

        $this->backfillEnrollmentMode();
    }

    public function down(): void
    {
        if (Schema::hasColumn('amapi_devices', 'enrollment_mode')) {
            // Les appareils cédés n'existaient pas avant cette migration : on les
            // ramène à DELETED pour ne pas laisser de ligne hors enum.
            DB::table('amapi_devices')
                ->where('enrollment_mode', '!=', 'FULLY_MANAGED')
                ->update(['amapi_state' => 'DELETED']);
        }

        if ($this->isSqlite()) {
            $this->rebuildTableForSqlite(self::ORIGINAL_STATES, withEnrollmentMode: false);

            return;
        }

        Schema::table('amapi_devices', function (Blueprint $table) {
            $table->dropColumn(['enrollment_mode', 'amapi_released_at']);
        });

        Schema::table('amapi_devices', function (Blueprint $table) {
            $table->enum('amapi_state', self::ORIGINAL_STATES)->change();
        });
    }

    private function isSqlite(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }

    private function addEnrollmentModeColumn(): void
    {
        Schema::table('amapi_devices', function (Blueprint $table) {
            $table->enum('enrollment_mode', ['FULLY_MANAGED', 'COPE'])
                ->default('FULLY_MANAGED')
                ->after('amapi_state')
                ->comment('Mode d\'enrôlement AMAPI figé au provisioning');

            $table->timestamp('amapi_released_at')
                ->nullable()
                ->after('enrollment_mode')
                ->comment('Libération effectuée côté AMAPI (suppression FM ou abandon COPE)');
        });
    }

    /**
     * Les lignes déjà portées en cope_policy ont été enrôlées en COPE : les
     * classer en Fully Managed les ferait supprimer de l'enterprise au lieu
     * de leur céder la propriété lors d'une libération.
     */
    private function backfillEnrollmentMode(): void
    {
        $copePolicy = config('services.amapi.policies.cope', 'cope_policy');

        DB::table('amapi_devices')->update([
            'enrollment_mode' => DB::raw(
                "CASE WHEN amapi_policy_id = '{$copePolicy}' THEN 'COPE' ELSE 'FULLY_MANAGED' END"
            ),
        ]);
    }

    /**
     * SQLite ne traduit pas enum() en type natif mais en varchar assorti d'une
     * contrainte CHECK, et ne sait ni ajouter ni retirer une contrainte. La
     * colonne comme la contrainte sont donc gérées ensemble par reconstruction
     * de la table, selon la procédure officielle de SQLite.
     *
     * @param  array<int, string>  $states
     */
    private function rebuildTableForSqlite(array $states, bool $withEnrollmentMode): void
    {
        $values = implode(', ', array_map(fn (string $value) => "'".$value."'", $states));

        $enrollmentModeColumn = $withEnrollmentMode
            ? '"enrollment_mode" varchar check ("enrollment_mode" in (\'FULLY_MANAGED\', \'COPE\')) not null default \'FULLY_MANAGED\','
            : '';

        $releasedAtColumn = $withEnrollmentMode
            ? '"amapi_released_at" datetime,'
            : '';

        DB::statement('PRAGMA foreign_keys=off');

        // Une migration interrompue laisse la table temporaire derrière elle :
        // sans ce DROP, la reprise du rollback échouerait sur un nom déjà pris.
        DB::statement('DROP TABLE IF EXISTS "amapi_devices_new"');

        DB::statement('CREATE TABLE "amapi_devices_new" (
            "id" integer primary key autoincrement not null,
            "device_id" integer not null,
            "amapi_device_id" varchar not null,
            "amapi_enterprise_id" varchar not null,
            "amapi_policy_id" varchar,
            "amapi_state" varchar check ("amapi_state" in ('.$values.')) not null default \'PROVISIONING\',
            '.$enrollmentModeColumn.'
            '.$releasedAtColumn.'
            "enrollment_token" varchar,
            "enrolled_at" datetime,
            "qr_code_data" text,
            "last_command_sent_at" datetime,
            "last_command_type" varchar,
            "last_command_status" varchar check ("last_command_status" in (\'PENDING\', \'SUCCESS\', \'FAILED\')),
            "last_command_error" text,
            "last_amapi_sync_at" datetime,
            "amapi_metadata" text,
            "created_at" datetime,
            "updated_at" datetime,
            "deleted_at" datetime,
            foreign key("device_id") references "devices"("id") on delete cascade
        )');

        $columns = '"id", "device_id", "amapi_device_id", "amapi_enterprise_id", "amapi_policy_id", "amapi_state"';
        $select = '"id", "device_id", "amapi_device_id", "amapi_enterprise_id", "amapi_policy_id", "amapi_state"';

        if ($withEnrollmentMode) {
            // La table source ne contient pas encore ces colonnes. SQLite
            // résout un identifiant entre guillemets doubles introuvable comme
            // une chaîne littérale, il faut donc passer par des littéraux
            // explicites, sans quoi la contrainte CHECK est violée dès que la
            // table contient des lignes.
            $columns .= ', "enrollment_mode", "amapi_released_at"';
            $select .= ', \'FULLY_MANAGED\', NULL';
        }

        $columns .= ', "enrollment_token", "enrolled_at", "qr_code_data", "last_command_sent_at", "last_command_type", "last_command_status", "last_command_error", "last_amapi_sync_at", "amapi_metadata", "created_at", "updated_at", "deleted_at"';
        $select .= ', "enrollment_token", "enrolled_at", "qr_code_data", "last_command_sent_at", "last_command_type", "last_command_status", "last_command_error", "last_amapi_sync_at", "amapi_metadata", "created_at", "updated_at", "deleted_at"';

        DB::statement('INSERT INTO "amapi_devices_new" ('.$columns.') SELECT '.$select.' FROM "amapi_devices"');

        DB::statement('DROP TABLE "amapi_devices"');
        DB::statement('ALTER TABLE "amapi_devices_new" RENAME TO "amapi_devices"');

        DB::statement('CREATE UNIQUE INDEX "amapi_devices_amapi_device_id_unique" on "amapi_devices" ("amapi_device_id")');
        DB::statement('CREATE INDEX "amapi_devices_amapi_device_id_amapi_state_index" on "amapi_devices" ("amapi_device_id", "amapi_state")');

        DB::statement('PRAGMA foreign_keys=on');
    }
};
