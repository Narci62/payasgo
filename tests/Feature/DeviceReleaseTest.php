<?php

namespace Tests\Feature;

use App\Models\AmapiDevice;
use App\Models\Client;
use App\Models\Device;
use App\Models\DeviceLockHistory;
use App\Models\Financing_plan;
use App\Models\Phone;
use App\Models\Registration_token;
use App\Services\AMAPIClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeviceReleaseTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = 'https://androidmanagement.googleapis.com/androidmanagement/v1';

    private string $enterpriseId = 'enterprises/test-enterprise';

    /**
     * Subclass neutralisant l'authentification : aucun appel réseau ne part vers
     * Google, seules les requêtes Http::fake() sont observées.
     */
    private function service(): AMAPIClientService
    {
        config()->set('services.amapi.base_url', $this->baseUrl);
        config()->set('services.amapi.enterprise_id', $this->enterpriseId);

        return new class extends AMAPIClientService
        {
            protected function getAccessToken(): string
            {
                return 'test-token';
            }
        };
    }

    private function makeDevice(string $enrollmentMode, string $amapiState = 'ACTIVE'): Device
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
            'device_id' => 'dev-'.$enrollmentMode,
            'status' => 'active',
        ]);

        AmapiDevice::create([
            'device_id' => $device->id,
            'amapi_device_id' => 'devices/'.strtolower($enrollmentMode),
            'amapi_enterprise_id' => $this->enterpriseId,
            'amapi_policy_id' => $enrollmentMode === 'COPE' ? 'cope_policy' : 'default_policy',
            'amapi_state' => $amapiState,
            'enrollment_mode' => $enrollmentMode,
        ]);

        return $device->fresh();
    }

    private function makeRegistrationToken(Device $device): Registration_token
    {
        return Registration_token::create([
            'client_id' => $device->client_id,
            'token' => 'tok-'.uniqid(),
            'expires_at' => \Carbon\Carbon::now()->addDay(),
        ]);
    }

    private function markPaidInFull(Device $device): Financing_plan
    {
        $plan = Financing_plan::create([
            'device_id' => $device->id,
            'registration_token_id' => $this->makeRegistrationToken($device)->id,
            'total_price' => 200_000,
            'down_payment' => 50_000,
            'remaining_balance' => 0,
            'installment_amount' => 25_000,
            'status' => 'paid_in_full',
        ]);

        return $plan->fresh();
    }

    public function test_release_device_deletes_fully_managed_device(): void
    {
        $device = $this->makeDevice('FULLY_MANAGED');

        Http::fake([
            '*/devices/*' => Http::response(['name' => 'devices/fm'], 200),
        ]);

        $this->assertTrue($this->service()->releaseDevice($device, 'ADMIN_UNINSTALL', null));

        Http::assertSent(function (Request $request) {
            return $request->method() === 'DELETE';
        });

        $amapiDevice = $device->amapiDevice->fresh();

        // deleteDevice() conserve sa bookkeeping historique : c'est
        // amapi_released_at qui marque la libération.
        $this->assertNotNull(
            $amapiDevice->amapi_released_at,
            'releaseDevice() doit estampiller la libération'
        );
        $this->assertTrue($amapiDevice->isReleased());

        $history = DeviceLockHistory::where('device_id', $device->id)->latest('id')->first();

        $this->assertSame('UNLOCK', $history->action);
        $this->assertSame('SUCCESS', $history->status);
        $this->assertSame('ADMIN_UNINSTALL', $history->trigger_reason);
    }

    public function test_release_device_does_not_stamp_when_the_call_fails(): void
    {
        $device = $this->makeDevice('FULLY_MANAGED');

        Http::fake(['*/devices/*' => Http::response(['error' => 'boom'], 500)]);

        $this->assertFalse($this->service()->releaseDevice($device, 'ADMIN_UNINSTALL', null));

        $this->assertNull(
            $device->amapiDevice->fresh()->amapi_released_at,
            'Une libération en échec ne doit pas être enregistrée'
        );
    }

    public function test_release_device_relinquishes_ownership_for_cope_device(): void
    {
        $device = $this->makeDevice('COPE');

        Http::fake([
            '*/devices/*' => Http::response(['name' => 'operations/op-1'], 200),
        ]);

        $this->assertTrue($this->service()->releaseDevice($device, 'ADMIN_UNINSTALL', null));

        Http::assertSent(function (Request $request) {
            return $request->method() === 'PATCH'
                && $request['commands'][0]['type'] === 'RELINQUISH_OWNERSHIP';
        });

        $amapiDevice = $device->amapiDevice->fresh();

        $this->assertSame('LIBERATED', $amapiDevice->amapi_state);
        $this->assertSame('RELINQUISH_OWNERSHIP', $amapiDevice->last_command_type);
        $this->assertSame('cope_policy', $amapiDevice->amapi_policy_id, 'La policy COPE doit être conservée');
        $this->assertNotNull($amapiDevice->amapi_released_at);

        $history = DeviceLockHistory::where('device_id', $device->id)->latest('id')->first();

        $this->assertSame('RELINQUISH_OWNERSHIP', $history->action);
        $this->assertSame('SUCCESS', $history->status);
    }

    public function test_release_device_routes_on_enrollment_mode_not_on_current_policy(): void
    {
        // Un appareil COPE dont amapi_policy_id a été réécrit par un verrouillage
        // doit toujours être libéré par RELINQUISH_OWNERSHIP.
        $device = $this->makeDevice('COPE');
        $device->amapiDevice->update(['amapi_policy_id' => 'locked_policy', 'amapi_state' => 'DISABLED']);

        Http::fake(['*/devices/*' => Http::response(['name' => 'operations/op-2'], 200)]);

        $this->service()->releaseDevice($device, 'RETRY_SYNC', null);

        Http::assertSent(fn (Request $request) => $request->method() === 'PATCH');

        $this->assertSame('LIBERATED', $device->amapiDevice->fresh()->amapi_state);
    }

    public function test_release_device_throws_when_device_is_not_enrolled(): void
    {
        $client = Client::create(['full_name' => 'Awa Ndiaye']);

        $device = Device::create([
            'client_id' => $client->id,
            'device_name' => 'Sans AMAPI',
            'device_id' => 'dev-none',
            'status' => 'active',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Device not enrolled in AMAPI');

        $this->service()->releaseDevice($device, 'ADMIN_UNINSTALL', null);
    }

    public function test_unlock_device_applies_the_cope_policy_for_a_cope_device(): void
    {
        $device = $this->makeDevice('COPE', 'DISABLED');
        $device->update(['status' => 'locked']);

        Http::fake(['*/devices/*' => Http::response(['name' => 'operations/unlock-1'], 200)]);

        $this->assertTrue($this->service()->unlockDevice($device, 'PAYMENT_RECEIVED', null));

        Http::assertSent(function (Request $request) {
            return $request->method() === 'PATCH'
                && $request['policyName'] === "enterprises/{$this->enterpriseId}/policies/cope_policy"
                && $request['state'] === 'ACTIVE';
        });

        $amapiDevice = $device->amapiDevice->fresh();

        $this->assertSame('ACTIVE', $amapiDevice->amapi_state);
        $this->assertSame('cope_policy', $amapiDevice->amapi_policy_id);

        $history = DeviceLockHistory::where('device_id', $device->id)->latest('id')->first();

        $this->assertSame('UNLOCK', $history->action);
        $this->assertSame('SUCCESS', $history->status);
        $this->assertSame('active', $device->fresh()->status);
    }

    public function test_unlock_device_applies_the_default_policy_for_a_fully_managed_device(): void
    {
        // Avant le correctif, cope_policy était envoyé à Google pour un
        // appareil FM alors que default_policy était enregistré en base.
        $device = $this->makeDevice('FULLY_MANAGED', 'DISABLED');
        $device->update(['status' => 'locked']);

        Http::fake(['*/devices/*' => Http::response(['name' => 'operations/unlock-2'], 200)]);

        $this->assertTrue($this->service()->unlockDevice($device, 'PAYMENT_RECEIVED', null));

        Http::assertSent(function (Request $request) {
            return $request->method() === 'PATCH'
                && $request['policyName'] === "enterprises/{$this->enterpriseId}/policies/default_policy";
        });

        $amapiDevice = $device->amapiDevice->fresh();

        $this->assertSame('ACTIVE', $amapiDevice->amapi_state);
        $this->assertSame('default_policy', $amapiDevice->amapi_policy_id);
    }

    public function test_unlock_device_keeps_the_cope_policy_for_a_locked_cope_device(): void
    {
        // Un verrouillage a réécrit amapi_policy_id : le déverrouillage doit
        // s'appuyer sur enrollment_mode, pas sur la policy courante.
        $device = $this->makeDevice('COPE', 'DISABLED');
        $device->amapiDevice->update(['amapi_policy_id' => 'locked_policy']);

        Http::fake(['*/devices/*' => Http::response(['name' => 'operations/unlock-3'], 200)]);

        $this->service()->unlockDevice($device, 'MANUAL_ADMIN', null);

        $this->assertSame('cope_policy', $device->amapiDevice->fresh()->amapi_policy_id);
    }

    public function test_unlock_device_does_not_update_local_state_when_the_call_fails(): void
    {
        $device = $this->makeDevice('COPE', 'DISABLED');
        $device->update(['status' => 'locked']);

        Http::fake(['*/devices/*' => Http::response(['error' => 'boom'], 500)]);

        $this->assertFalse($this->service()->unlockDevice($device, 'MANUAL_ADMIN', null));

        $amapiDevice = $device->amapiDevice->fresh();

        $this->assertSame('DISABLED', $amapiDevice->amapi_state);
        $this->assertSame('cope_policy', $amapiDevice->amapi_policy_id);
        $this->assertSame('locked', $device->fresh()->status);

        $history = DeviceLockHistory::where('device_id', $device->id)->latest('id')->first();

        $this->assertSame('UNLOCK_ATTEMPT', $history->action);
        $this->assertSame('FAILED', $history->status);
    }

    public function test_unlock_device_throws_when_device_is_not_enrolled(): void
    {
        $client = Client::create(['full_name' => 'Awa Ndiaye']);

        $device = Device::create([
            'client_id' => $client->id,
            'device_name' => 'Sans AMAPI',
            'device_id' => 'dev-none',
            'status' => 'active',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Device not enrolled in AMAPI');

        $this->service()->unlockDevice($device, 'MANUAL_ADMIN', null);
    }

    public function test_is_liberated_is_true_for_paid_cope_device_in_liberted_state(): void
    {
        $device = $this->makeDevice('COPE');
        $plan = $this->markPaidInFull($device);

        $this->assertFalse($device->isLiberated(), 'Un appareil COPE encore géré ne doit pas être considéré libéré');

        $device->amapiDevice->update(['amapi_state' => 'LIBERATED']);

        $this->assertTrue($device->fresh()->isLiberated());
    }

    public function test_is_liberated_is_true_for_paid_fully_managed_device_once_released(): void
    {
        $device = $this->makeDevice('FULLY_MANAGED');
        $this->markPaidInFull($device);

        $this->assertFalse($device->isLiberated());

        $device->amapiDevice->update(['amapi_released_at' => now()]);

        $this->assertTrue($device->fresh()->isLiberated());
    }

    public function test_is_liberated_is_false_for_paid_fully_managed_device_still_in_amapi(): void
    {
        // deleteDevice() laisse la ligne en ACTIVE : sans le marqueur de
        // libération, l'appareil ne doit pas passer pour libéré.
        $device = $this->makeDevice('FULLY_MANAGED');
        $this->markPaidInFull($device);

        $device->amapiDevice->update(['amapi_state' => 'ACTIVE']);

        $this->assertFalse($device->fresh()->isLiberated());
    }

    public function test_is_liberated_remains_false_for_unpaid_liberted_device(): void
    {
        $device = $this->makeDevice('COPE');
        Financing_plan::create([
            'device_id' => $device->id,
            'registration_token_id' => $this->makeRegistrationToken($device)->id,
            'total_price' => 200_000,
            'down_payment' => 50_000,
            'remaining_balance' => 125_000,
            'installment_amount' => 25_000,
            'status' => 'active',
        ]);

        $device->amapiDevice->update(['amapi_state' => 'LIBERATED']);

        $this->assertFalse($device->fresh()->isLiberated());
    }

    public function test_provisioning_defaults_to_cope_and_persists_the_mode(): void
    {
        config()->set('services.amapi.policies.cope', 'cope_policy');
        config()->set('services.amapi.policies.default', 'default_policy');

        $device = $this->makeDevice('FULLY_MANAGED');

        Http::fake([
            '*/enrollmentTokens' => Http::response([
                'name' => 'enrollmentTokens/tok-1',
                'qrCode' => 'https://qr.test/abc',
            ], 200),
        ]);

        $this->service()->generateProvisioningQRCode($device);

        Http::assertSent(function (Request $request) {
            return str_ends_with($request->url(), '/enrollmentTokens')
                && str_contains($request['policyName'], 'cope_policy');
        });

        $amapiDevice = $device->amapiDevice->fresh();

        $this->assertSame('COPE', $amapiDevice->enrollment_mode);
        $this->assertSame('cope_policy', $amapiDevice->amapi_policy_id);
        $this->assertSame('PROVISIONING', $amapiDevice->amapi_state);
    }

    public function test_provisioning_can_be_forced_to_fully_managed(): void
    {
        config()->set('services.amapi.policies.cope', 'cope_policy');
        config()->set('services.amapi.policies.default', 'default_policy');

        $device = $this->makeDevice('COPE');

        Http::fake([
            '*/enrollmentTokens' => Http::response([
                'name' => 'enrollmentTokens/tok-2',
                'qrCode' => 'https://qr.test/def',
            ], 200),
        ]);

        $this->service()->generateProvisioningQRCode(
            $device,
            [],
            AMAPIClientService::ENROLLMENT_FULLY_MANAGED
        );

        Http::assertSent(function (Request $request) {
            return str_contains($request['policyName'], 'default_policy');
        });

        $this->assertSame('FULLY_MANAGED', $device->amapiDevice->fresh()->enrollment_mode);
    }

    public function test_sync_normalizes_prefixed_state_from_amapi(): void
    {
        $device = $this->makeDevice('COPE');

        Http::fake([
            '*/devices/*' => Http::response([
                'state' => 'STATE_ACTIVE',
                'lastStatusReportTime' => '2026-09-29T10:00:00Z',
            ], 200),
        ]);

        $this->service()->syncDeviceStatus($device);

        $this->assertSame('ACTIVE', $device->amapiDevice->fresh()->amapi_state);
    }

    public function test_sync_keeps_previous_state_on_unknown_amapi_value(): void
    {
        $device = $this->makeDevice('COPE', 'PROVISIONING');

        Http::fake([
            '*/devices/*' => Http::response(['state' => 'STATE_UNSPECIFIED'], 200),
        ]);

        $this->service()->syncDeviceStatus($device);

        $this->assertSame(
            'PROVISIONING',
            $device->amapiDevice->fresh()->amapi_state,
            'Un état AMAPI inconnu ne doit pas écraser la valeur courante'
        );
    }

    public function test_sync_accepts_awaiting_device_activation_state(): void
    {
        $device = $this->makeDevice('COPE', 'PROVISIONING');

        Http::fake([
            '*/devices/*' => Http::response(['state' => 'AWAITING_DEVICE_ACTIVATION'], 200),
        ]);

        $this->service()->syncDeviceStatus($device);

        $this->assertSame(
            'AWAITING_DEVICE_ACTIVATION',
            $device->amapiDevice->fresh()->amapi_state
        );
    }
}
