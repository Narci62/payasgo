<?php

namespace Tests\Feature;

use App\Models\AmapiDevice;
use App\Models\Client;
use App\Models\Device;
use App\Models\Phone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Vérifie le up() réel de la migration 000002 en le rejouant depuis le vrai état
 * pré-migration. RefreshDatabase étant inopérant ici (on doit intervenir avant
 * que 000002 ne soit appliquée), on construit une base SQLite temporaire sur
 * laquelle on migre en excluant volontairement ce fichier.
 */
class EnrollmentModeBackfillTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = '2026_09_29_000002_add_enrollment_mode_to_amapi_devices_table.php';

    private const RECLASSIFICATION = '2026_09_29_000003_reclassify_enrollment_mode_by_enrollment_date.php';

    private const CONNECTION = 'backfill_probe';

    private string $migrationsPath;

    private string $databasePath;

    private string $previousDefaultConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrationsPath = storage_path('framework/testing/migrations-enrollment-mode');
        $this->databasePath = storage_path('framework/testing/enrollment-mode.sqlite');
        $this->previousDefaultConnection = DB::getDefaultConnection();

        File::deleteDirectory($this->migrationsPath);
        File::ensureDirectoryExists($this->migrationsPath);
        File::ensureDirectoryExists(dirname($this->databasePath));
        File::delete($this->databasePath);
        File::put($this->databasePath, '');
    }

    protected function tearDown(): void
    {
        DB::setDefaultConnection($this->previousDefaultConnection);

        File::deleteDirectory($this->migrationsPath);
        File::delete($this->databasePath);

        parent::tearDown();
    }

    /**
     * Migre une base SQLite temporaire en excluant la migration sous test, puis
     * bascule la connexion par défaut dessus.
     */
    private function bootWithoutEnrollmentModeMigration(): void
    {
        foreach (File::files(database_path('migrations')) as $file) {
            if (! in_array($file->getFilename(), [self::MIGRATION, self::RECLASSIFICATION], true)) {
                File::copy($file->getPathname(), $this->migrationsPath.'/'.$file->getFilename());
            }
        }

        config()->set('database.connections.'.self::CONNECTION, [
            'driver' => 'sqlite',
            'database' => $this->databasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        DB::setDefaultConnection(self::CONNECTION);
        DB::purge(self::CONNECTION);

        $exitCode = Artisan::call('migrate', [
            '--path' => $this->migrationsPath,
            '--realpath' => true,
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode, 'La migration de la sonde doit s\'appliquer : '.Artisan::output());

        $this->assertFalse(
            Schema::hasColumn('amapi_devices', 'enrollment_mode'),
            'La sonde doit démarrer sans la colonne enrollment_mode'
        );
    }

    private function migration(): Migration
    {
        return require database_path('migrations/'.self::MIGRATION);
    }

    private function makeAmapiDevice(string $amapiDeviceId, ?string $policyId, ?string $enrolledAt = null, ?string $createdAt = null): void
    {
        $client = Client::create(['full_name' => 'Awa Ndiaye']);

        $phone = Phone::create([
            'brand' => 'Samsung',
            'model' => 'A15',
            'stock' => 5,
            'price' => 150_000,
        ]);

        $device = Device::create([
            'client_id' => $client->id,
            'phone_id' => $phone->id,
            'device_name' => 'Galaxy A15',
            'device_id' => 'dev-'.$amapiDeviceId,
            'status' => 'active',
        ]);

        $attributes = [
            'device_id' => $device->id,
            'amapi_device_id' => $amapiDeviceId,
            'amapi_enterprise_id' => 'enterprises/test',
            'amapi_policy_id' => $policyId,
            'amapi_state' => 'PROVISIONING',
            'enrolled_at' => $enrolledAt,
        ];

        if ($createdAt) {
            $attributes['created_at'] = $createdAt;
        }

        AmapiDevice::create($attributes);
    }

    private function reclassificationMigration(): Migration
    {
        return require database_path('migrations/'.self::RECLASSIFICATION);
    }

    private function enrollmentModeOf(string $amapiDeviceId): ?string
    {
        return AmapiDevice::where('amapi_device_id', $amapiDeviceId)->value('enrollment_mode');
    }

    public function test_backfill_classifies_cope_policy_rows_as_cope(): void
    {
        config()->set('services.amapi.policies.cope', 'cope_policy');

        $this->bootWithoutEnrollmentModeMigration();

        $this->makeAmapiDevice('amapi_cope', 'cope_policy');
        $this->makeAmapiDevice('amapi_default', 'default_policy');
        $this->makeAmapiDevice('amapi_locked', 'locked_policy');
        $this->makeAmapiDevice('amapi_null_policy', null);

        $this->migration()->up();

        $this->assertSame('COPE', $this->enrollmentModeOf('amapi_cope'), 'Une ligne en cope_policy est un COPE');
        $this->assertSame('FULLY_MANAGED', $this->enrollmentModeOf('amapi_default'));
        $this->assertSame('FULLY_MANAGED', $this->enrollmentModeOf('amapi_locked'));
        $this->assertSame('FULLY_MANAGED', $this->enrollmentModeOf('amapi_null_policy'));
    }

    public function test_backfill_follows_the_configured_cope_policy_name(): void
    {
        config()->set('services.amapi.policies.cope', 'ma_policy_renommee');

        $this->bootWithoutEnrollmentModeMigration();

        $this->makeAmapiDevice('amapi_custom', 'ma_policy_renommee');
        $this->makeAmapiDevice('amapi_cope_default', 'cope_policy');

        $this->migration()->up();

        $this->assertSame('COPE', $this->enrollmentModeOf('amapi_custom'));
        $this->assertSame(
            'FULLY_MANAGED',
            $this->enrollmentModeOf('amapi_cope_default'),
            'Seule la policy COPE configurée doit classer en COPE'
        );
    }

    public function test_backfill_leaves_the_rest_of_the_row_untouched(): void
    {
        config()->set('services.amapi.policies.cope', 'cope_policy');

        $this->bootWithoutEnrollmentModeMigration();

        $this->makeAmapiDevice('amapi_cope', 'cope_policy');

        $this->migration()->up();

        $amapiDevice = AmapiDevice::where('amapi_device_id', 'amapi_cope')->firstOrFail();

        $this->assertSame('amapi_cope', $amapiDevice->amapi_device_id);
        $this->assertSame('cope_policy', $amapiDevice->amapi_policy_id);
        $this->assertSame('PROVISIONING', $amapiDevice->amapi_state);
        $this->assertNull($amapiDevice->amapi_released_at);
    }

    public function test_up_is_replayable_after_rollback(): void
    {
        config()->set('services.amapi.policies.cope', 'cope_policy');

        $this->bootWithoutEnrollmentModeMigration();

        $this->makeAmapiDevice('amapi_cope', 'cope_policy');

        $migration = $this->migration();
        $migration->up();

        $this->assertSame('COPE', $this->enrollmentModeOf('amapi_cope'));

        $migration->down();

        $this->assertFalse(
            Schema::hasColumn('amapi_devices', 'enrollment_mode'),
            'Le down() doit retirer les colonnes ajoutées'
        );

        $migration->up();

        $this->assertSame('COPE', $this->enrollmentModeOf('amapi_cope'), 'Le up() rejoué doit rester correct');
    }

    /**
     * La 000003 corrige le critère de la 000002 : elle porte sur des lignes
     * déjà migrées, RefreshDatabase suffit donc, sans base temporaire.
     */
    public function test_locked_cope_device_is_reclassified_as_cope(): void
    {
        // amapi_policy_id vaut locked_policy car l'appareil a été verrouillé
        // depuis son enrôlement. C'est le cas que le critère de la 000002
        // classait à tort en Fully Managed.
        $this->makeAmapiDevice(
            'amapi_locked_cope',
            'locked_policy',
            '2026-09-20 10:00:00'
        );

        AmapiDevice::where('amapi_device_id', 'amapi_locked_cope')
            ->update(['enrollment_mode' => 'FULLY_MANAGED', 'amapi_state' => 'DISABLED']);

        $this->reclassificationMigration()->up();

        $this->assertSame(
            'COPE',
            $this->enrollmentModeOf('amapi_locked_cope'),
            'Un COPE verrouillé reste un COPE'
        );
    }

    public function test_fully_managed_device_from_before_the_cope_switch_stays_fully_managed(): void
    {
        $this->makeAmapiDevice(
            'amapi_legacy_fm',
            'default_policy',
            '2026-09-01 09:00:00'
        );

        $this->reclassificationMigration()->up();

        $this->assertSame('FULLY_MANAGED', $this->enrollmentModeOf('amapi_legacy_fm'));
    }

    public function test_reclassification_falls_back_on_created_at_when_never_enrolled(): void
    {
        // Jamais enrôlé : le webhook n'a pas écrit enrolled_at.
        $this->makeAmapiDevice('amapi_provisioning', 'cope_policy', null, '2026-09-25 08:00:00');

        $this->assertNull(AmapiDevice::where('amapi_device_id', 'amapi_provisioning')->value('enrolled_at'));

        $this->reclassificationMigration()->up();

        $this->assertSame('COPE', $this->enrollmentModeOf('amapi_provisioning'));
    }

    public function test_reclassification_uses_created_at_when_never_enrolled_before_the_switch(): void
    {
        $this->makeAmapiDevice('amapi_legacy_pending', 'default_policy', null, '2026-09-05 08:00:00');

        $this->reclassificationMigration()->up();

        $this->assertSame('FULLY_MANAGED', $this->enrollmentModeOf('amapi_legacy_pending'));
    }

    public function test_reclassification_is_idempotent(): void
    {
        $this->makeAmapiDevice('amapi_cope', 'cope_policy', '2026-09-20 10:00:00');
        $this->makeAmapiDevice('amapi_legacy_fm', 'default_policy', '2026-09-01 09:00:00');

        $migration = $this->reclassificationMigration();
        $migration->up();
        $migration->up();
        $migration->down();

        $this->assertSame('COPE', $this->enrollmentModeOf('amapi_cope'));
        $this->assertSame('FULLY_MANAGED', $this->enrollmentModeOf('amapi_legacy_fm'));
    }
}
