<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Horodatage du commit basculant le provisionnement de default_policy
     * vers cope_policy. Les enrôlements antérieurs sont des Fully Managed,
     * les suivants des COPE.
     */
    private const COPE_SWITCHED_AT = '2026-09-14 17:19:34';

    public function up(): void
    {
        $this->reclassify();
    }

    public function down(): void
    {
        $this->reclassify();
    }

    /**
     * Remplace le backfill de la migration 000002, qui classait sur
     * amapi_policy_id. Ce champ est réécrit à chaque commande : un appareil
     * COPE verrouILLé y affiche locked_policy et était donc pris pour un
     * Fully Managed, puis libéré par suppression de l'enterprise au lieu
     * d'une cession de propriété.
     *
     * On se fonde sur enrolled_at, écrit une seule fois par le webhook
     * d'enrôlement. created_at sert de repli lorsque le webhook n'a jamais
     * été reçu, ce qui est le cas des appareils encore en provisioning.
     */
    private function reclassify(): void
    {
        DB::table('amapi_devices')
            ->update([
                'enrollment_mode' => DB::raw(
                    "CASE WHEN COALESCE(enrolled_at, created_at) < '".self::COPE_SWITCHED_AT."'
                        THEN 'FULLY_MANAGED'
                        ELSE 'COPE'
                    END"
                ),
            ]);
    }
};
