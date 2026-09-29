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

        $this->assertCount(15, $component->instance()->getTable()->getRecords()->all());
        $this->assertSame(16, $component->instance()->getTable()->getQuery()->count());
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
        $html = $this->actingAs($user)->get('/admin')->assertOk()->getContent();

        // Les widgets Filament sont montés en lazy : le dashboard livre un
        // placeholder, et le navigateur appelle __lazyLoad avant toute
        // interaction. Un update envoyé sur le placeholder (sans ce passage)
        // represents un état que le navigateur ne produit jamais.
        [$snapshot, $encoded] = $this->lazyWidgetSnapshot($html, 'app.filament.widgets.contracts-devices-overview');

        $this->forgetRegisteredLivewireAliases();

        $loaded = $this->actingAs($user)->postJson('/livewire/update', [
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => [],
                'calls' => [['path' => '', 'method' => '__lazyLoad', 'params' => [$encoded]]],
            ]],
        ]);

        $loaded->assertOk();
        $this->assertStringContainsString('fi-wi-table', (string) $loaded->json('components.0.effects.html'));

        $paged = $this->actingAs($user)->postJson('/livewire/update', [
            'components' => [[
                'snapshot' => html_entity_decode((string) $loaded->json('components.0.snapshot')),
                'updates' => [],
                'calls' => [['path' => '', 'method' => 'gotoPage', 'params' => [2]]],
            ]],
        ]);

        $paged->assertOk();

        $updated = json_decode($paged->json('components.0.snapshot'), true);

        $this->assertSame('app.filament.widgets.contracts-devices-overview', $updated['memo']['name']);
        $this->assertNotEmpty($paged->json('components.0.effects.html'));
    }

    public function test_contracts_widget_filters_by_contract_status(): void
    {
        $active = $this->makeContract(status: 'active', clientName: 'Awa Diop');
        $this->makeContract(status: 'paid_in_full', clientName: 'Moussa Fall');

        Livewire::test(ContractsDevicesOverview::class)
            ->assertOk()
            ->filterTable('status', 'active')
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([Financing_plan::where('status', 'paid_in_full')->first()]);
    }

    public function test_contracts_widget_filters_by_device_status(): void
    {
        $locked = $this->makeContract(deviceStatus: 'locked', clientName: 'Awa Diop');
        $this->makeContract(deviceStatus: 'active', clientName: 'Moussa Fall');

        Livewire::test(ContractsDevicesOverview::class)
            ->assertOk()
            ->filterTable('device_status', 'locked')
            ->assertCanSeeTableRecords([$locked])
            ->assertDontSee('Moussa Fall');
    }

    public function test_contracts_widget_searches_client_phone_imei_and_unlock_code(): void
    {
        $byClient = $this->makeContract(clientName: 'Awa Diop', brand: 'Tecno', model: 'Spark 30');
        $byImei = $this->makeContract(clientName: 'Moussa Fall', imei: '990000862471854');
        $byCode = $this->makeContract(clientName: 'Fatou Sarr', unlockCode: '7788-1122');

        $component = fn () => Livewire::test(ContractsDevicesOverview::class)->assertOk();

        $component()->filterTable('search', ['term' => 'Awa'])->assertCanSeeTableRecords([$byClient]);
        $component()->filterTable('search', ['term' => '990000862471854'])->assertCanSeeTableRecords([$byImei]);
        $component()->filterTable('search', ['term' => '7788-1122'])->assertCanSeeTableRecords([$byCode]);
    }

    public function test_contracts_widget_search_is_ignored_when_blank(): void
    {
        $first = $this->makeContract(clientName: 'Awa Diop');
        $second = $this->makeContract(clientName: 'Moussa Fall');

        Livewire::test(ContractsDevicesOverview::class)
            ->assertOk()
            ->filterTable('search', ['term' => '   '])
            ->assertCanSeeTableRecords([$first, $second]);
    }

    public function test_contracts_widget_filters_overdue_contracts(): void
    {
        $overdue = $this->makeContract(status: 'active', nextDueDate: Carbon::now()->subDays(3), clientName: 'Awa Diop');
        $this->makeContract(status: 'active', nextDueDate: Carbon::now()->addDays(10), clientName: 'Moussa Fall');

        Livewire::test(ContractsDevicesOverview::class)
            ->assertOk()
            ->filterTable('overdue')
            ->assertCanSeeTableRecords([$overdue])
            ->assertDontSee('Moussa Fall');
    }

    public function test_contracts_widget_filters_by_registration_period(): void
    {
        $recent = $this->makeContract(clientName: 'Awa Diop', createdAt: Carbon::now()->subDays(2));
        $this->makeContract(clientName: 'Moussa Fall', createdAt: Carbon::now()->subDays(120));

        Livewire::test(ContractsDevicesOverview::class)
            ->assertOk()
            ->filterTable('created_period', ['isActive' => true, 'range' => '7d'])
            ->assertCanSeeTableRecords([$recent])
            ->assertDontSee('Moussa Fall');
    }

    public function test_contracts_widget_sorts_and_paginates_through_the_table(): void
    {
        for ($i = 0; $i < 16; $i++) {
            $this->makeContract(clientName: sprintf('Client %02d', $i), createdAt: Carbon::now()->subDays($i));
        }

        Livewire::test(ContractsDevicesOverview::class)
            ->assertOk()
            ->sortTable('created_at', 'desc')
            ->assertSee('Client 00')
            ->call('gotoPage', 2)
            ->assertOk()
            ->assertSee('Client 15');
    }

    public function test_contract_can_be_edited_from_the_dashboard_modal(): void
    {
        $this->actingAs($this->makePanelUser());

        $plan = $this->makeContract(status: 'active', clientName: 'Awa Diop');

        Livewire::test(ContractsDevicesOverview::class)
            ->assertOk()
            ->mountTableAction('edit', $plan)
            ->setTableActionData([
                'remaining_balance' => 42_000,
                'installment_amount' => 21_000,
                'status' => 'paid_in_full',
                'next_payment_due_date' => null,
            ])
            ->callMountedTableAction()
            ->assertHasNoActionErrors();

        $plan->refresh();

        $this->assertSame(42_000.0, (float) $plan->remaining_balance);
        $this->assertSame(21_000.0, (float) $plan->installment_amount);
        $this->assertSame('paid_in_full', $plan->getRawOriginal('status'));
    }

    public function test_contract_modal_keeps_the_raw_status_and_never_writes_the_translated_label(): void
    {
        $this->actingAs($this->makePanelUser());

        $plan = $this->makeContract(status: 'active');

        $component = Livewire::test(ContractsDevicesOverview::class)
            ->assertOk()
            ->mountTableAction('edit', $plan)
            // La modale doit se remplir avec l'enum brut, pas le libellé
            // traduit par l'accessor du modèle.
            ->assertTableActionDataSet(['status' => 'active']);

        $component
            ->setTableActionData(['status' => 'defaulted'])
            ->callMountedTableAction();

        $this->assertSame('defaulted', $plan->refresh()->getRawOriginal('status'));
        $this->assertDatabaseMissing('financing_plans', ['id' => $plan->id, 'status' => 'En attente']);
    }

    public function test_contract_edit_action_is_hidden_from_non_admin_users(): void
    {
        $this->actingAs(User::factory()->create());

        $plan = $this->makeContract();

        Livewire::test(ContractsDevicesOverview::class)
            ->assertOk()
            ->assertTableActionHidden('edit', $plan);
    }

    public function test_contract_edit_action_is_available_to_admin_users(): void
    {
        $this->actingAs($this->makePanelUser());

        $plan = $this->makeContract();

        Livewire::test(ContractsDevicesOverview::class)
            ->assertOk()
            ->assertTableActionVisible('edit', $plan);
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

    /**
     * @return array{0: string, 1: string}
     */
    private function lazyWidgetSnapshot(string $html, string $componentName): array
    {
        foreach (array_reverse(preg_split('/(?=<div[^>]*wire:snapshot=)/', $html, -1, PREG_SPLIT_NO_EMPTY)) as $chunk) {
            if (! preg_match('/wire:snapshot="([^"]+)"/', $chunk, $matches)) {
                continue;
            }

            $snapshot = json_decode(html_entity_decode($matches[1]), true);

            if (($snapshot['memo']['name'] ?? null) !== $componentName) {
                continue;
            }

            if (! preg_match('/__lazyLoad\((?:&#039;|\')([^\']+?)(?:&#039;|\')\)/', $chunk, $encoded)) {
                $this->fail("Le composant [{$componentName}] n'est pas monte en lazy : le scenario de test ne reproduirait pas le navigateur.");
            }

            return [html_entity_decode($matches[1]), $encoded[1]];
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
        string $brand = 'Tecno',
        string $model = 'Spark 30',
        string $deviceName = 'Tecno Spark 30',
        ?string $imei = null,
        ?Carbon $createdAt = null,
        string $unlockCode = '1234-5678',
    ): Financing_plan {
        $client = Client::create(['full_name' => $clientName]);

        $token = Registration_token::create([
            'client_id' => $client->id,
            'token' => 'tok-'.uniqid(),
            'expires_at' => Carbon::now()->addDay(),
        ]);

        $phone = Phone::create([
            'brand' => $brand,
            'model' => $model,
            'stock' => 3,
            'price' => 150_000,
        ]);

        $device = Device::create([
            'client_id' => $client->id,
            'phone_id' => $phone->id,
            'device_name' => $deviceName,
            'device_id' => 'dev-'.uniqid(),
            'imei' => $imei,
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
            'next_offline_unlock_code' => $unlockCode,
        ]);

        if ($createdAt !== null) {
            $plan->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        }

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
