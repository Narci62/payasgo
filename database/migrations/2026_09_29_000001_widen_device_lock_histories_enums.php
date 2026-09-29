<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Colonnes concernées et valeurs autorisées.
     *
     * AMAPIClientService::deleteDevice() écrit 'DELETE_ATTEMPT', deleteDevice() /
     * relinquishOwnership() reçoivent 'ADMIN_UNINSTALL' et 'RETRY_SYNC' : ces
     * valeurs n'existaient pas dans les enums créés par la migration initiale et
     * étaient rejetées par MySQL en mode strict.
     */
    private const COLUMNS = [
        'action' => [
            'LOCK',
            'UNLOCK',
            'LOCK_ATTEMPT',
            'UNLOCK_ATTEMPT',
            'DELETE_ATTEMPT',
            'RELINQUISH_OWNERSHIP_ATTEMPT',
            'RELINQUISH_OWNERSHIP',
        ],
        'trigger_reason' => [
            'PAYMENT_OVERDUE',
            'INACTIVITY_14_DAYS',
            'MANUAL_ADMIN',
            'PAYMENT_RECEIVED',
            'ADMIN_OVERRIDE',
            'ADMIN_UNINSTALL',
            'RETRY_SYNC',
        ],
    ];

    private const ORIGINAL_COLUMNS = [
        'action' => [
            'LOCK',
            'UNLOCK',
            'LOCK_ATTEMPT',
            'UNLOCK_ATTEMPT',
        ],
        'trigger_reason' => [
            'PAYMENT_OVERDUE',
            'INACTIVITY_14_DAYS',
            'MANUAL_ADMIN',
            'PAYMENT_RECEIVED',
            'ADMIN_OVERRIDE',
        ],
    ];

    private const INDEX = 'device_lock_histories_device_id_action_created_at_index';

    public function up(): void
    {
        $this->rewriteEnums(self::COLUMNS);
    }

    public function down(): void
    {
        $this->rewriteEnums(self::ORIGINAL_COLUMNS);
    }

    /**
     * @param  array<string, array<int, string>>  $columns
     */
    private function rewriteEnums(array $columns): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->rebuildTableForSqlite($columns);

            return;
        }

        foreach ($columns as $column => $allowed) {
            Schema::table('device_lock_histories', function (Blueprint $table) use ($column, $allowed) {
                $table->enum($column, $allowed)->change();
            });
        }
    }

    /**
     * SQLite ne traduit pas enum() en type natif : la colonne est un varchar
     * assorti d'une contrainte CHECK. Comme SQLite ne sait pas modifier une
     * contrainte, la table est reconstruite selon la procedure officielle.
     *
     * @param  array<string, array<int, string>>  $columns
     */
    private function rebuildTableForSqlite(array $columns): void
    {
        $definitions = [];

        foreach ($columns as $column => $allowed) {
            $values = implode(', ', array_map(fn (string $value) => "'".$value."'", $allowed));

            $definitions[$column] = '"'.$column.'" varchar check ("'.$column.'" in ('.$values.')) not null';
        }

        DB::statement('PRAGMA foreign_keys=off');

        DB::statement('CREATE TABLE "device_lock_histories_new" (
            "id" integer primary key autoincrement not null,
            "device_id" integer not null,
            "financing_plan_id" integer,
            '.$definitions['action'].',
            '.$definitions['trigger_reason'].',
            "status" varchar check ("status" in (\'PENDING\', \'SUCCESS\', \'FAILED\')) not null default \'PENDING\',
            "error_message" text,
            "remaining_balance" numeric,
            "days_overdue" integer,
            "days_inactive" integer,
            "triggered_by_user_id" integer,
            "amapi_command_id" varchar,
            "executed_at" datetime,
            "created_at" datetime,
            "updated_at" datetime,
            foreign key("device_id") references "devices"("id") on delete cascade,
            foreign key("financing_plan_id") references "financing_plans"("id"),
            foreign key("triggered_by_user_id") references "users"("id")
        )');

        DB::statement('INSERT INTO "device_lock_histories_new" SELECT * FROM "device_lock_histories"');

        DB::statement('DROP TABLE "device_lock_histories"');
        DB::statement('ALTER TABLE "device_lock_histories_new" RENAME TO "device_lock_histories"');

        DB::statement('CREATE INDEX "'.self::INDEX.'" on "device_lock_histories" ("device_id", "action", "created_at")');

        DB::statement('PRAGMA foreign_keys=on');
    }
};
