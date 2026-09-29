<?php

namespace Tests\Feature;

use App\Filament\Resources\Devices\Pages\ListDevices;
use App\Helpers\PermissionHelper;
use App\Models\Client;
use App\Models\Device;
use App\Models\DeviceLockHistory;
use App\Models\Financing_plan;
use App\Models\Phone;
use App\Models\Registration_token;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Rendu de la modale « Historique de verrouillage » de /admin/devices.
 *
 * Lecture seule sur la base : aucun appel AMAPI, aucun verrouillage déclenché.
 */
class DeviceLockHistoryTest extends TestCase
{
    public function test_it_renders_the_every_column_of_a_failed_lock_attempt(): void
    {
        $device = $this->makeDevice(clientName: 'Awa Diop');
        $admin = User::factory()->create(['name' => 'Admin PayasGo']);

        DeviceLockHistory::create([
            'device_id' => $device->id,
            'financing_plan_id' => $device->financingPlan->id,
            'action' => 'LOCK_ATTEMPT',
            'trigger_reason' => 'PAYMENT_OVERDUE',
            'status' => 'FAILED',
            'error_message' => 'The device does not exist.',
            'remaining_balance' => 125_000,
            'days_overdue' => 12,
            'triggered_by_user_id' => $admin->id,
            'executed_at' => Carbon::parse('2026-09-24 08:30:00'),
        ]);

        $html = $this->renderHistory($device);

        $this->assertStringContainsString('24/09/2026 08:30', $html);
        $this->assertStringContainsString('Tentative de verrouillage', $html);
        $this->assertStringContainsString('Retard de paiement', $html);
        $this->assertStringContainsString('Échec', $html);
        $this->assertStringContainsString('125 000 FCFA', $html);
        $this->assertStringContainsString('Awa Diop', $html);
        $this->assertStringContainsString('Admin PayasGo', $html);
        $this->assertStringContainsString('The device does not exist.', $html);
    }

    public function test_it_falls_back_on_the_creation_date_and_marks_automatic_actions(): void
    {
        $device = $this->makeDevice();

        DeviceLockHistory::create([
            'device_id' => $device->id,
            'action' => 'UNLOCK',
            'trigger_reason' => 'PAYMENT_RECEIVED',
            'status' => 'SUCCESS',
            'remaining_balance' => null,
            'executed_at' => null,
        ]);

        $this->travelTo(Carbon::parse('2026-09-26 10:01:33'));
        $device->lockHistory()->latest()->first()->forceFill(['created_at' => Carbon::now()])->save();

        $html = $this->renderHistory($device);

        $this->assertStringContainsString('26/09/2026 10:01', $html);
        $this->assertStringContainsString('Déverrouillé', $html);
        $this->assertStringContainsString('Paiement reçu', $html);
        $this->assertStringContainsString('Réussi', $html);
        $this->assertStringContainsString('Automatique', $html);
    }

    public function test_it_shows_an_empty_state_when_no_lock_history_exists(): void
    {
        $html = $this->renderHistory($this->makeDevice());

        $this->assertStringContainsString('Aucun verrouillage enregistré', $html);
        $this->assertStringNotContainsString('<table', $html);
    }

    public function test_it_never_leaks_the_raw_enum_values(): void
    {
        $device = $this->makeDevice();

        DeviceLockHistory::create([
            'device_id' => $device->id,
            'action' => 'UNLOCK_ATTEMPT',
            'trigger_reason' => 'INACTIVITY_14_DAYS',
            'status' => 'PENDING',
        ]);

        $html = $this->renderHistory($device);

        foreach (['UNLOCK_ATTEMPT', 'INACTIVITY_14_DAYS', 'PENDING'] as $rawValue) {
            $this->assertStringNotContainsString($rawValue, $html);
        }

        $this->assertStringContainsString('Tentative de déverrouillage', $html);
        $this->assertStringContainsString('Inactivité 14 jours', $html);
        $this->assertStringContainsString('En attente', $html);
    }

    public function test_the_enum_accepts_every_action_written_by_the_amapi_client(): void
    {
        $device = $this->makeDevice();

        $actions = [
            'LOCK',
            'UNLOCK',
            'LOCK_ATTEMPT',
            'UNLOCK_ATTEMPT',
            'DELETE_ATTEMPT',
            'RELINQUISH_OWNERSHIP_ATTEMPT',
            'RELINQUISH_OWNERSHIP',
        ];

        foreach ($actions as $action) {
            $history = DeviceLockHistory::create([
                'device_id' => $device->id,
                'action' => $action,
                'trigger_reason' => 'MANUAL_ADMIN',
                'status' => 'PENDING',
            ]);

            $this->assertSame($action, $history->fresh()->action);
        }
    }

    public function test_the_enum_accepts_every_trigger_reason_written_by_the_amapi_client(): void
    {
        $device = $this->makeDevice();

        $reasons = [
            'PAYMENT_OVERDUE',
            'INACTIVITY_14_DAYS',
            'MANUAL_ADMIN',
            'PAYMENT_RECEIVED',
            'ADMIN_OVERRIDE',
            'ADMIN_UNINSTALL',
            'RETRY_SYNC',
        ];

        foreach ($reasons as $reason) {
            $history = DeviceLockHistory::create([
                'device_id' => $device->id,
                'action' => 'LOCK_ATTEMPT',
                'trigger_reason' => $reason,
                'status' => 'PENDING',
            ]);

            $this->assertSame($reason, $history->fresh()->trigger_reason);
        }
    }

    public function test_the_enum_still_rejects_an_unknown_value(): void
    {
        $device = $this->makeDevice();

        $this->expectException(QueryException::class);

        DeviceLockHistory::create([
            'device_id' => $device->id,
            'action' => 'FACTORY_RESET',
            'trigger_reason' => 'MANUAL_ADMIN',
            'status' => 'PENDING',
        ]);
    }

    public function test_it_translates_the_uninstall_and_relinquish_entries(): void
    {
        $device = $this->makeDevice();

        DeviceLockHistory::create([
            'device_id' => $device->id,
            'action' => 'DELETE_ATTEMPT',
            'trigger_reason' => 'ADMIN_UNINSTALL',
            'status' => 'PENDING',
        ]);

        DeviceLockHistory::create([
            'device_id' => $device->id,
            'action' => 'RELINQUISH_OWNERSHIP',
            'trigger_reason' => 'RETRY_SYNC',
            'status' => 'SUCCESS',
        ]);

        $html = $this->renderHistory($device);

        foreach ([
            'DELETE_ATTEMPT',
            'RELINQUISH_OWNERSHIP',
            'ADMIN_UNINSTALL',
            'RETRY_SYNC',
        ] as $rawValue) {
            $this->assertStringNotContainsString($rawValue, $html);
        }

        $this->assertStringContainsString('Tentative de désinstallation', $html);
        $this->assertStringContainsString('Propriété cédée', $html);
        $this->assertStringContainsString('Désinstallation admin', $html);
        $this->assertStringContainsString('Nouvelle tentative de synchronisation', $html);
    }

    public function test_the_lock_history_action_is_registered_only_once(): void
    {
        Filament::setCurrentPanel('admin');

        $role = Role::create(['name' => 'super-admin', 'guard_name' => 'web']);
        $role->givePermissionTo(...collect(PermissionHelper::getLabels())
            ->keys()
            ->map(fn (string $name) => Permission::create(['name' => $name, 'guard_name' => 'web'])));

        $user = User::factory()->create();
        $user->assignRole($role);

        $this->makeDevice();

        $this->actingAs($user);

        $component = Livewire::test(ListDevices::class)->assertOk();

        $names = array_map(
            fn (Action $action) => $action->getName(),
            $component->instance()->getTable()->getFlatActions(),
        );

        $this->assertSame(1, count(array_filter($names, fn ($name) => $name === 'lock_history')));
        $this->assertSame(1, substr_count($component->html(), 'Historique de verrouillage'));
    }

    public function test_the_theme_styles_the_lock_history_modal(): void
    {
        $css = file_get_contents(public_path('css/filament/admin/theme.css'));

        foreach ([
            'pg-history-table',
            'pg-history-empty',
            'pg-history-error',
            'pg-history-client',
        ] as $class) {
            $this->assertStringContainsString('.'.$class, $css, "La classe {$class} doit etre compilee dans le CSS versionne.");
        }
    }

    private function renderHistory(Device $device): string
    {
        return view('filament.devices.lock-history', [
            'lockHistory' => $device->lockHistory()
                ->with(['device.client', 'triggeredByUser'])
                ->latest()
                ->limit(20)
                ->get(),
        ])->render();
    }

    private function makeDevice(string $clientName = 'Client Test'): Device
    {
        $client = Client::create(['full_name' => $clientName]);

        $token = Registration_token::create([
            'client_id' => $client->id,
            'token' => 'tok-'.uniqid(),
            'expires_at' => Carbon::now()->addDay(),
        ]);

        $phone = Phone::create([
            'brand' => 'Tecno',
            'model' => 'Spark 30',
            'stock' => 3,
            'price' => 150_000,
        ]);

        $device = Device::create([
            'client_id' => $client->id,
            'phone_id' => $phone->id,
            'device_name' => 'Tecno Spark 30',
            'device_id' => 'dev-'.uniqid(),
            'status' => 'active',
        ]);

        Financing_plan::create([
            'device_id' => $device->id,
            'registration_token_id' => $token->id,
            'total_price' => 200_000,
            'down_payment' => 50_000,
            'remaining_balance' => 125_000,
            'installment_amount' => 25_000,
            'status' => 'active',
            'amapi_sync_status' => 'synced',
            'days_interval' => 30,
            'next_payment_due_date' => Carbon::now()->addDays(10),
            'next_offline_unlock_code' => '1234-5678',
        ]);

        return $device->fresh();
    }
}
