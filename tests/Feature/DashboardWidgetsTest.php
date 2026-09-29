<?php

namespace Tests\Feature;

use App\Filament\Widgets\AlertsOverview;
use App\Filament\Widgets\ContractsDevicesOverview;
use App\Filament\Widgets\PortfolioKpis;
use App\Models\AmapiDevice;
use App\Models\Client;
use App\Models\Device;
use App\Models\Financing_plan;
use App\Models\Payment;
use App\Models\Phone;
use App\Models\Registration_token;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Livewire\Mechanisms\ComponentRegistry;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Rendu des trois widgets de la page d'accueil.
 *
 * Ne déclenche aucun appel AMAPI : seules des lectures de tables sont faites.
 */
class DashboardWidgetsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    public function test_kpi_widget_renders_all_six_indicators(): void
    {
        $this->makeContract(remainingBalance: 120_000);

        Livewire::test(PortfolioKpis::class)
            ->assertOk()
            ->assertSee('Encours à recouvrer')
            ->assertSee('120 000 FCFA')
            ->assertSee('Contrats actifs')
            ->assertSee('Contrats en retard')
            ->assertSee('Montant encaissé')
            ->assertSee('Appareils verrouillés')
            ->assertSee('Parc enrôlé');
    }

    public function test_kpi_widget_renders_on_an_empty_database(): void
    {
        Livewire::test(PortfolioKpis::class)
            ->assertOk()
            ->assertSee('Encours à recouvrer')
            ->assertSee('0 FCFA');
    }

    public function test_contracts_widget_renders_the_unified_contract_and_device_columns(): void
    {
        $this->makeContract(
            status: 'active',
            nextDueDate: Carbon::now()->subDays(2),
            deviceStatus: 'locked',
            clientName: 'Awa Diop',
        );

        Livewire::test(ContractsDevicesOverview::class)
            ->assertOk()
            ->assertSee('Contrats &amp; appareils', escape: false)
            ->assertSee('Awa Diop')
            ->assertSee('Tecno Spark 30')
            ->assertSee('Actif')
            ->assertSee('Verrouillé')
            ->assertSee('Échéance dépassée')
            ->assertSee('1234-5678');
    }

    public function test_contracts_widget_shows_an_empty_state_without_contracts(): void
    {
        Livewire::test(ContractsDevicesOverview::class)
            ->assertOk()
            ->assertSee('Aucun contrat enregistré');
    }

    public function test_contracts_widget_paginates(): void
    {
        for ($i = 0; $i < 16; $i++) {
            $this->makeContract(clientName: 'Client '.$i);
        }

        $component = Livewire::test(ContractsDevicesOverview::class)->assertOk();

        $this->assertCount(15, $component->viewData('rows'));
        $this->assertSame(16, $component->viewData('contracts')->total());
    }

    public function test_alerts_widget_lists_every_alert_group(): void
    {
        $this->makeContract(
            status: 'active',
            nextDueDate: Carbon::now()->subDays(1),
            deviceStatus: 'locked',
            amapiSyncStatus: 'failed',
        );

        Livewire::test(AlertsOverview::class)
            ->assertOk()
            ->assertSee('Points de vigilance')
            ->assertSee('Contrats en retard de paiement')
            ->assertSee('Échecs de synchronisation AMAPI')
            ->assertSee('Appareils verrouillés')
            ->assertSee('Appareils injoignables ou non enrôlés')
            ->assertSee('Échéances sous 7 jours')
            ->assertSee('Contrats soldés à libérer');
    }

    public function test_widgets_are_registered_on_the_admin_panel(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertSame('PayasGo', $panel->getBrandName());
        $this->assertContains(PortfolioKpis::class, $panel->getWidgets());
        $this->assertContains(ContractsDevicesOverview::class, $panel->getWidgets());
        $this->assertContains(AlertsOverview::class, $panel->getWidgets());
    }

    public function test_dashboard_page_stacks_the_three_blocks(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $page = Livewire::test(\App\Filament\Pages\Dashboard::class)->assertOk();

        // Filament 4 rend les widgets via un composant de schéma : on vérifie
        // l'ordre d'empilement plutôt que le HTML rendu.
        $this->assertSame(
            [
                \Filament\Widgets\AccountWidget::class,
                PortfolioKpis::class,
                ContractsDevicesOverview::class,
                AlertsOverview::class,
            ],
            array_values($page->instance()->getWidgets()),
        );

        $this->assertSame(1, $page->instance()->getColumns());
    }

    public function test_dashboard_widgets_are_resolvable_from_their_livewire_name(): void
    {
        $registry = app(ComponentRegistry::class);

        foreach ([PortfolioKpis::class, ContractsDevicesOverview::class, AlertsOverview::class] as $widget) {
            $name = $registry->getName($widget);

            $this->assertTrue(
                $registry->isDiscoverable($name),
                "Le composant Livewire [{$name}] doit être résolvable depuis son nom. Sans cela, toute requete AJAX renvoie une ComponentNotFoundException.",
            );
        }
    }

    public function test_paginated_widget_survives_a_livewire_update_request(): void
    {
        for ($i = 0; $i < 16; $i++) {
            $this->makeContract(clientName: 'Client '.$i);
        }

        $user = $this->makePanelUser();
        $snapshot = $this->livewireSnapshotFor(
            $this->actingAs($user)->get('/admin')->assertOk()->getContent(),
            'app.filament.widgets.contracts-devices-overview',
        );

        $this->forgetRegisteredLivewireAliases();

        $response = $this->actingAs($user)->postJson('/livewire/update', [
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => ['paginators' => ['pg-contracts' => ['page' => 2]]],
                'calls' => [],
            ]],
        ]);

        $response->assertOk();

        $updated = json_decode($response->json('components.0.snapshot'), true);

        $this->assertSame('app.filament.widgets.contracts-devices-overview', $updated['memo']['name']);
        $this->assertNotEmpty($response->json('components.0.effects.html'));
    }

    public function test_compiled_theme_asset_is_committed(): void
    {
        $path = public_path('css/filament/admin/theme.css');

        $this->assertFileExists($path, 'Le CSS du thème doit être versionné, le CD ne lance pas npm run build.');
        $this->assertStringContainsString('.pg-kpi', file_get_contents($path));
        $this->assertStringContainsString('.pg-table', file_get_contents($path));
    }

    private function makePanelUser(): User
    {
        $role = Role::create(['name' => 'super-admin', 'guard_name' => 'web']);

        $role->givePermissionTo(...collect([
            'view-dashboard', 'view-clients', 'view-devices', 'view-phones', 'view-financing-plans',
            'view-users', 'manage-roles', 'view-sales-report',
        ])->map(fn (string $name) => Permission::create(['name' => $name, 'guard_name' => 'web'])));

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function forgetRegisteredLivewireAliases(): void
    {
        $aliases = new \ReflectionProperty(ComponentRegistry::class, 'aliases');
        $aliases->setValue(app(ComponentRegistry::class), []);
    }

    private function livewireSnapshotFor(string $html, string $componentName): string
    {
        preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);

        foreach ($matches[1] as $encoded) {
            $snapshot = json_decode(html_entity_decode($encoded), true);

            if (($snapshot['memo']['name'] ?? null) === $componentName) {
                return html_entity_decode($encoded);
            }
        }

        $this->fail("Aucun composant Livewire [{$componentName}] present dans le rendu de /admin.");
    }

    private function makeContract(
        string $status = 'active',
        ?float $remainingBalance = 100_000,
        ?Carbon $nextDueDate = null,
        ?string $deviceStatus = 'active',
        ?string $amapiState = null,
        string $amapiSyncStatus = 'synced',
        string $clientName = 'Client Test',
    ): Financing_plan {
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
            'status' => $deviceStatus ?? 'active',
        ]);

        if ($amapiState !== null) {
            AmapiDevice::create([
                'device_id' => $device->id,
                'amapi_enterprise_id' => 'enterprises/payasgo-test',
                'amapi_state' => $amapiState,
            ]);
        }

        $plan = Financing_plan::create([
            'device_id' => $device->id,
            'registration_token_id' => $token->id,
            'total_price' => 200_000,
            'down_payment' => 50_000,
            'remaining_balance' => $remainingBalance ?? 100_000,
            'installment_amount' => 25_000,
            'status' => $status,
            'amapi_sync_status' => $amapiSyncStatus,
            'days_interval' => 30,
            'next_payment_due_date' => $nextDueDate,
            'next_offline_unlock_code' => '1234-5678',
        ]);

        Payment::create([
            'financing_plan_id' => $plan->id,
            'amount' => 50_000,
            'method' => 'manual',
            'transaction_id' => 'txn-'.uniqid(),
            'status' => 'completed',
            'paid_at' => Carbon::now(),
        ]);

        return $plan;
    }
}
