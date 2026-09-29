<?php

namespace Tests\Feature;

use App\Models\AmapiDevice;
use App\Models\Client;
use App\Models\Device;
use App\Models\Financing_plan;
use App\Models\Payment;
use App\Models\Phone;
use App\Models\Registration_token;
use App\Services\DashboardService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

/**
 * Couverture de DashboardService : agrégations de la page d'accueil.
 *
 * Lecture seule sur la base : aucun appel AMAPI, aucune écriture métier.
 */
class DashboardServiceTest extends TestCase
{
    private DashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(DashboardService::class);
    }

    public function test_kpis_expose_the_six_expected_indicators(): void
    {
        $this->makeContract();

        $kpis = collect($this->service->kpis())->keyBy('key');

        $this->assertSame(
            ['outstanding', 'active', 'overdue', 'collected', 'locked', 'enrolled'],
            $kpis->keys()->all(),
        );

        foreach ($kpis as $kpi) {
            $this->assertArrayHasKey('label', $kpi);
            $this->assertArrayHasKey('value', $kpi);
            $this->assertArrayHasKey('hint', $kpi);
            $this->assertArrayHasKey('icon', $kpi);
            $this->assertContains($kpi['tone'], ['primary', 'success', 'warning', 'danger', 'info']);
        }
    }

    public function test_outstanding_balance_ignores_contracts_paid_in_full(): void
    {
        $this->makeContract(remainingBalance: 120_000, status: 'active');
        $this->makeContract(remainingBalance: 45_000, status: 'paid_in_full');
        $this->makeContract(remainingBalance: 30_000, status: 'defaulted');

        $this->assertSame(150_000.0, $this->service->outstandingBalance());
        $this->assertSame(2, $this->service->outstandingContractsCount());
    }

    public function test_collected_amount_only_sums_completed_payments(): void
    {
        $plan = $this->makeContract(remainingBalance: 100_000);

        $this->makePayment($plan, 25_000, 'completed');
        $this->makePayment($plan, 10_000, 'completed');
        $this->makePayment($plan, 99_999, 'pending');
        $this->makePayment($plan, 99_999, 'failed');

        $this->assertSame(35_000.0, $this->service->collectedAmount());
    }

    public function test_active_and_overdue_queries_use_the_raw_status_column(): void
    {
        $this->makeContract(nextDueDate: Carbon::now()->addDays(10), status: 'active');
        $this->makeContract(nextDueDate: Carbon::now()->subDays(3), status: 'active');
        $this->makeContract(nextDueDate: Carbon::now()->subDays(30), status: 'paid_in_full');
        $this->makeContract(nextDueDate: Carbon::now()->subDays(30), status: 'defaulted');

        $this->assertSame(2, $this->service->activeContractsQuery()->count());
        $this->assertSame(1, $this->service->overdueContractsQuery()->count());
        $this->assertSame(1, $this->service->settledContractsCount());
    }

    public function test_locked_devices_query_matches_local_status_and_amapi_state(): void
    {
        $this->makeDevice(status: 'locked');
        $this->makeDevice(status: 'active', amapiState: 'DISABLED');
        $this->makeDevice(status: 'active', amapiState: 'ACTIVE');
        $this->makeDevice(status: 'payment_due');

        $this->assertSame(2, $this->service->lockedDevicesQuery()->count());
    }

    public function test_enrolled_devices_excludes_deleted_amapi_records(): void
    {
        $this->makeDevice(status: 'active', amapiState: 'ACTIVE');
        $this->makeDevice(status: 'active', amapiState: 'DELETED');
        $this->makeDevice(status: 'pending_registration');

        $this->assertSame(1, $this->service->enrolledDevicesCount());
    }

    public function test_soft_deleted_records_are_excluded_from_every_aggregate(): void
    {
        $plan = $this->makeContract(remainingBalance: 90_000, status: 'active');
        $plan->delete();

        $device = $this->makeDevice(status: 'locked');
        $device->delete();

        $this->assertSame(0.0, $this->service->outstandingBalance());
        $this->assertSame(0, $this->service->lockedDevicesQuery()->count());
        $this->assertSame(0, $this->service->activeContractsQuery()->count());
    }

    /**
     * Financing_plan::getStatusAttribute() réécrit la valeur à la lecture.
     * Le service doit rendre la même information, mais sous une forme
     * exploitable par l'IHM plutôt que "actif" / "soldé" / "En attente".
     */
    public function test_contract_status_labels_bypass_the_status_accessor(): void
    {
        $active = $this->makeContract(status: 'active');
        $settled = $this->makeContract(status: 'paid_in_full');
        $pending = $this->makeContract(status: 'defaulted');

        $this->assertSame('actif', $active->fresh()->status, "L'accessor du modèle doit rester inchangé.");
        $this->assertSame('Actif', $this->service->contractStatusLabel($active->fresh()));
        $this->assertSame('Soldé', $this->service->contractStatusLabel($settled->fresh()));
        $this->assertSame('En attente', $this->service->contractStatusLabel($pending->fresh()));
        $this->assertSame('Inconnu', $this->service->contractStatusLabel(null));
    }

    public function test_contract_status_tones(): void
    {
        $this->assertSame('success', $this->service->contractStatusTone($this->makeContract(status: 'active')));
        $this->assertSame('primary', $this->service->contractStatusTone($this->makeContract(status: 'paid_in_full')));
        $this->assertSame('warning', $this->service->contractStatusTone($this->makeContract(status: 'defaulted')));
        $this->assertSame('gray', $this->service->contractStatusTone(null));
    }

    public function test_device_status_label_covers_every_case(): void
    {
        $locked = $this->makeContract(status: 'active', deviceStatus: 'locked');
        $this->assertSame('Verrouillé', $this->service->deviceStatusLabel($locked));

        $amapiDisabled = $this->makeContract(status: 'active', amapiState: 'DISABLED');
        $this->assertSame('Verrouillé', $this->service->deviceStatusLabel($amapiDisabled));

        $active = $this->makeContract(status: 'active', amapiState: 'ACTIVE');
        $this->assertSame('Actif', $this->service->deviceStatusLabel($active));

        $provisioning = $this->makeContract(status: 'active', amapiState: 'PROVISIONING');
        $this->assertSame('Enrôlement', $this->service->deviceStatusLabel($provisioning));

        $notEnrolled = $this->makeContract(status: 'active');
        $this->assertSame('Non enrôlé', $this->service->deviceStatusLabel($notEnrolled));

        $liberated = $this->makeContract(
            status: 'paid_in_full',
            remainingBalance: 0,
        );
        $this->assertSame('Libéré', $this->service->deviceStatusLabel($liberated));

        $withoutDevice = $this->makeContract(status: 'active', withDevice: false);
        $this->assertSame('Aucun appareil', $this->service->deviceStatusLabel($withoutDevice));
    }

    public function test_a_settled_contract_with_an_amapi_record_is_not_liberated(): void
    {
        $plan = $this->makeContract(
            status: 'paid_in_full',
            remainingBalance: 0,
            amapiState: 'ACTIVE',
        );

        $this->assertFalse($this->service->isLiberated($plan, $plan->device));
        $this->assertSame('Actif', $this->service->deviceStatusLabel($plan));
    }

    public function test_is_overdue_only_flags_active_contracts(): void
    {
        $past = Carbon::now()->subDay();

        $this->assertTrue($this->service->isOverdue($this->makeContract(nextDueDate: $past, status: 'active')));
        $this->assertFalse($this->service->isOverdue($this->makeContract(nextDueDate: $past, status: 'paid_in_full')));
        $this->assertFalse($this->service->isOverdue($this->makeContract(nextDueDate: Carbon::now()->addDay(), status: 'active')));
        $this->assertFalse($this->service->isOverdue($this->makeContract(nextDueDate: null, status: 'active')));
        $this->assertFalse($this->service->isOverdue(null));
    }

    public function test_alerts_report_the_expected_counts(): void
    {
        // 1 contrat actif en retard + 1 appareil verrouillé + 1 sync failed
        $this->makeContract(
            nextDueDate: Carbon::now()->subDays(2),
            status: 'active',
            deviceStatus: 'locked',
            amapiSyncStatus: 'failed',
        );

        $alerts = collect($this->service->alerts())->keyBy('key');

        $this->assertSame(
            ['overdue', 'sync_failed', 'locked', 'offline', 'due_soon', 'pending_uninstall'],
            $alerts->keys()->all(),
        );

        $this->assertSame(1, $alerts['overdue']['count']);
        $this->assertSame('danger', $alerts['overdue']['tone']);
        $this->assertSame(1, $alerts['sync_failed']['count']);
        $this->assertSame(1, $alerts['locked']['count']);
        $this->assertSame(0, $alerts['due_soon']['count']);

        foreach ($alerts as $alert) {
            $this->assertContains($alert['tone'], ['primary', 'success', 'warning', 'danger', 'info']);
            $this->assertNotEmpty($alert['title']);
        }
    }

    public function test_alerts_turn_green_when_nothing_needs_attention(): void
    {
        $this->makeContract(
            nextDueDate: Carbon::now()->addDays(5),
            status: 'active',
            amapiSyncStatus: 'synced',
        );

        $alerts = collect($this->service->alerts())->keyBy('key');

        $this->assertSame('success', $alerts['overdue']['tone']);
        $this->assertSame('success', $alerts['sync_failed']['tone']);
        $this->assertSame('success', $alerts['locked']['tone']);
        $this->assertSame(1, $alerts['due_soon']['count']);
        $this->assertNull($alerts['overdue']['url'], "Aucun lien quand il n'y a rien à traiter.");
    }

    public function test_settled_contracts_awaiting_amapi_uninstall_are_counted(): void
    {
        $this->makeContract(status: 'paid_in_full', remainingBalance: 0, amapiSyncStatus: 'pending');
        $this->makeContract(status: 'paid_in_full', remainingBalance: 0, amapiSyncStatus: 'synced');

        $alerts = collect($this->service->alerts())->keyBy('key');

        $this->assertSame(1, $alerts['pending_uninstall']['count']);
    }

    public function test_contracts_table_eager_loads_and_decorates_its_rows(): void
    {
        $plan = $this->makeContract(
            nextDueDate: Carbon::now()->subDays(1),
            status: 'active',
            deviceStatus: 'locked',
        );

        $paginator = $this->service->contractsWithDevices(15, 'pg-contracts');

        $this->assertSame(1, $paginator->total());
        $this->assertSame('pg-contracts', $paginator->getPageName());

        $row = $this->service->decoratePlans($paginator->getCollection())->first();

        $this->assertSame($plan->registrationToken->client->full_name, $row['client']);
        $this->assertSame('Actif', $row['contract_status']);
        $this->assertSame('Verrouillé', $row['device_status']);
        $this->assertSame('danger', $row['device_tone']);
        $this->assertTrue($row['overdue']);
        $this->assertStringContainsString('FCFA', $row['remaining_balance']);
        $this->assertStringContainsString('/admin/financing-plans/', $row['edit_url']);
    }

    public function test_decorated_rows_fall_back_when_the_device_is_missing(): void
    {
        $plan = $this->makeContract(status: 'active', withDevice: false);

        $row = $this->service->decoratePlans(collect([$plan]))->first();

        $this->assertSame('Aucun appareil', $row['device_status']);
        $this->assertSame('gray', $row['device_tone']);
        $this->assertSame('—', $row['phone']);
    }

    public function test_amounts_are_formatted_in_francs_without_decimals(): void
    {
        $this->assertSame('0 FCFA', $this->service->formatAmount(0));
        $this->assertSame('1 250 000 FCFA', $this->service->formatAmount(1250000));
        $this->assertSame('1 250 000 FCFA', $this->service->formatAmount('1250000.49'));
        $this->assertSame('0 FCFA', $this->service->formatAmount(null));
    }

    public function test_phone_label_combines_brand_and_model(): void
    {
        $plan = $this->makeContract(status: 'active');

        $this->assertSame('Tecno Spark 30', $this->service->phoneLabel($plan));
    }

    private function makeContract(
        string $status = 'active',
        ?float $remainingBalance = 100_000,
        ?Carbon $nextDueDate = null,
        ?string $deviceStatus = null,
        ?string $amapiState = null,
        ?string $amapiSyncStatus = 'synced',
        bool $withDevice = true,
    ): Financing_plan {
        $client = Client::create(['full_name' => 'Awa Diop']);

        $token = Registration_token::create([
            'client_id' => $client->id,
            'token' => 'tok-'.uniqid(),
            'expires_at' => Carbon::now()->addDay(),
        ]);

        $device = null;

        if ($withDevice) {
            $phone = Phone::create([
                'brand' => 'Tecno',
                'model' => 'Spark 30',
                'stock' => 5,
                'price' => 150_000,
            ]);

            $device = Device::create([
                'client_id' => $client->id,
                'phone_id' => $phone->id,
                'device_name' => 'Tecno Spark 30',
                'device_id' => 'dev-'.uniqid(),
                'status' => $deviceStatus ?? 'active',
            ]);

            if ($amapiState !== null) {
                $this->makeAmapiDevice($device, $amapiState);
            }
        }

        return Financing_plan::create([
            'device_id' => $device?->id,
            'registration_token_id' => $token->id,
            'total_price' => 200_000,
            'down_payment' => 50_000,
            'remaining_balance' => $remainingBalance ?? 100_000,
            'installment_amount' => 25_000,
            'status' => $status,
            'amapi_sync_status' => $amapiSyncStatus ?? 'synced',
            'days_interval' => 30,
            'next_payment_due_date' => $nextDueDate,
            'next_offline_unlock_code' => '1234-5678',
        ]);
    }

    private function makeDevice(?string $status = 'active', ?string $amapiState = null): Device
    {
        $client = Client::create(['full_name' => 'Modou Fall']);

        $device = Device::create([
            'client_id' => $client->id,
            'device_name' => 'Samsung A15',
            'device_id' => 'dev-'.uniqid(),
            'status' => $status ?? 'active',
        ]);

        if ($amapiState !== null) {
            $this->makeAmapiDevice($device, $amapiState);
        }

        return $device;
    }

    private function makeAmapiDevice(Device $device, string $state): AmapiDevice
    {
        return AmapiDevice::create([
            'device_id' => $device->id,
            'amapi_enterprise_id' => 'enterprises/payasgo-test',
            'amapi_state' => $state,
        ]);
    }

    private function makePayment(Model $plan, float $amount, string $status): Payment
    {
        return Payment::create([
            'financing_plan_id' => $plan->id,
            'amount' => $amount,
            'method' => 'manual',
            'transaction_id' => 'txn-'.uniqid(),
            'status' => $status,
            'paid_at' => Carbon::now(),
        ]);
    }
}
