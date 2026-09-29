<?php

namespace Tests\Feature;

use App\Filament\Widgets\ActiveContractsWidget;
use App\Filament\Widgets\AllContractsWidget;
use App\Filament\Widgets\FinishedContractsWidget;
use App\Models\Client;
use App\Models\Device;
use App\Models\Financing_plan;
use App\Models\Phone;
use App\Models\Registration_token;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Les trois widgets de contrats sont dormants (non listés sur la page
 * d'accueil) mais restent atteignables : ils sont testés pour éviter qu'une
 * réactivation ne casse l'édition d'un contrat.
 *
 * Aucun appel AMAPI : seules des lectures et écritures de tables locales.
 */
class ContractWidgetsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    public static function widgetProvider(): array
    {
        return [
            'contrats en cours' => [ActiveContractsWidget::class, 'active'],
            'en attente de liaison' => [AllContractsWidget::class, 'defaulted'],
            'contrats soldés' => [FinishedContractsWidget::class, 'paid_in_full'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('widgetProvider')]
    public function test_widget_only_lists_contracts_matching_its_status(string $widget, string $status): void
    {
        $this->actingAs($this->makeAdmin());

        $plan = $this->makeContract($status, clientName: 'Awa Diop');
        $this->makeContract($status === 'active' ? 'paid_in_full' : 'active', clientName: 'Moussa Fall');

        Livewire::test($widget)
            ->assertOk()
            ->assertCanSeeTableRecords([$plan])
            ->assertSee('Awa Diop')
            ->assertDontSee('Moussa Fall');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('widgetProvider')]
    public function test_contract_can_be_edited_from_every_contract_widget(string $widget, string $status): void
    {
        $this->actingAs($this->makeAdmin());

        $plan = $this->makeContract($status);

        // Régression : l'EditAction n'avait aucun formulaire. La modale ne
        // montrait aucun champ et l'enregistrement ne pouvait pas aboutir.
        Livewire::test($widget)
            ->assertOk()
            ->mountTableAction('edit', $plan)
            ->assertTableActionDataSet([
                'status' => $status,
                'remaining_balance' => 100_000,
            ])
            ->setTableActionData([
                'remaining_balance' => 12_000,
                'status' => 'active',
            ])
            ->callMountedTableAction()
            ->assertHasNoActionErrors();

        $plan->refresh();

        $this->assertSame(12_000.0, (float) $plan->remaining_balance);
        $this->assertSame('active', $plan->getRawOriginal('status'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('widgetProvider')]
    public function test_recent_contracts_filter_is_inactive_by_default(string $widget, string $status): void
    {
        $this->actingAs($this->makeAdmin());

        $old = $this->makeContract($status, clientName: 'Awa Diop', createdAt: Carbon::now()->subDays(60));

        // Régression : le callback ne testait pas isActive, le filtre
        // « 7 derniers jours » s'appliquait en permanence et masquait tous
        // les contrats plus anciens.
        Livewire::test($widget)
            ->assertOk()
            ->assertCanSeeTableRecords([$old])
            ->filterTable('created_at')
            ->assertCanNotSeeTableRecords([$old]);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('widgetProvider')]
    public function test_recent_contracts_filter_keeps_recent_contracts(string $widget, string $status): void
    {
        $this->actingAs($this->makeAdmin());

        $recent = $this->makeContract($status, clientName: 'Awa Diop', createdAt: Carbon::now()->subDays(2));
        $this->makeContract($status, clientName: 'Moussa Fall', createdAt: Carbon::now()->subDays(60));

        Livewire::test($widget)
            ->assertOk()
            ->filterTable('created_at')
            ->assertCanSeeTableRecords([$recent])
            ->assertSee('Awa Diop')
            ->assertDontSee('Moussa Fall');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('widgetProvider')]
    public function test_edit_action_is_hidden_from_non_admin_users(string $widget, string $status): void
    {
        $this->actingAs(User::factory()->create());

        $plan = $this->makeContract($status);

        Livewire::test($widget)
            ->assertOk()
            ->assertTableActionHidden('edit', $plan);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('widgetProvider')]
    public function test_bulk_delete_is_reserved_to_admins(string $widget, string $status): void
    {
        $plan = $this->makeContract($status);

        $this->actingAs(User::factory()->create());

        Livewire::test($widget)
            ->assertOk()
            ->assertTableBulkActionHidden('delete');

        $this->actingAs($this->makeAdmin());

        Livewire::test($widget)
            ->assertOk()
            ->assertTableBulkActionVisible('delete');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('widgetProvider')]
    public function test_payment_history_opens_a_read_only_modal(string $widget, string $status): void
    {
        $this->actingAs($this->makeAdmin());

        $plan = $this->makeContract($status);

        // Régression : l'action portait un ->action() vide qui fermait la
        // modale au clic sans jamais afficher l'historique.
        Livewire::test($widget)
            ->assertOk()
            ->mountTableAction('view_payments', $plan)
            ->assertMountedActionModalSee('Historique des paiements');
    }

    private function makeAdmin(): User
    {
        $role = Role::create(['name' => 'super-admin', 'guard_name' => 'web']);

        $role->givePermissionTo(...collect([
            'view-dashboard', 'view-clients', 'view-devices', 'view-phones', 'view-financing-plans',
            'view-users', 'manage-roles', 'view-sales-report',
        ])->map(fn (string $name) => Permission::create(['name' => $name, 'guard_name' => 'web'])));

        return tap(User::factory()->create())->assignRole($role);
    }

    private function makeContract(string $status, string $clientName = 'Awa Diop', ?Carbon $createdAt = null): Financing_plan
    {
        $client = Client::create(['full_name' => $clientName]);

        $token = Registration_token::create([
            'client_id' => $client->id,
            'token' => 'tok-'.uniqid(),
            'expires_at' => Carbon::now()->addDay(),
        ]);

        $phone = Phone::create(['brand' => 'Tecno', 'model' => 'Spark 30', 'stock' => 3, 'price' => 150_000]);

        $device = Device::create([
            'client_id' => $client->id,
            'phone_id' => $phone->id,
            'device_name' => 'Tecno Spark 30',
            'device_id' => 'dev-'.uniqid(),
            'status' => 'active',
        ]);

        $plan = Financing_plan::create([
            'device_id' => $device->id,
            'registration_token_id' => $token->id,
            'total_price' => 200_000,
            'down_payment' => 100_000,
            'remaining_balance' => 100_000,
            'installment_amount' => 25_000,
            'status' => $status,
            'amapi_sync_status' => 'synced',
            'days_interval' => 30,
            'next_offline_unlock_code' => '1234-5678',
        ]);

        if ($createdAt !== null) {
            $plan->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        }

        return $plan;
    }
}
