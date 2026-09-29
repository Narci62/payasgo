<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\Pages\ListClients;
use App\Filament\Resources\Devices\Pages\ListDevices;
use App\Filament\Resources\Penalties\Pages\ListPenalties;
use App\Filament\Resources\Phones\Pages\ListPhones;
use App\Helpers\PermissionHelper;
use App\Models\AmapiDevice;
use App\Models\Client;
use App\Models\Device;
use App\Models\DeviceLockHistory;
use App\Models\Financing_plan;
use App\Models\Garant;
use App\Models\Penalty;
use App\Models\Phone;
use App\Models\Registration_token;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Filtres ajoutés sur les pages de liste du back-office.
 *
 * Chaque test applique un filtre puis vérifie les enregistrements réellement
 * retenus par la requête filtrée, et non le rendu HTML. Lecture seule :
 * aucun appel AMAPI.
 */
class ResourceFiltersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $role = Role::create(['name' => 'super-admin', 'guard_name' => 'web']);
        $role->givePermissionTo(...collect(PermissionHelper::getLabels())
            ->keys()
            ->map(fn (string $name) => Permission::create(['name' => $name, 'guard_name' => 'web'])));

        $this->actingAs(User::factory()->create()->assignRole($role));
    }

    // ---------------------------------------------------------------- Clients

    public function test_clients_can_be_filtered_by_overdue_installment(): void
    {
        $this->makePlan(clientName: 'Awa Diop', status: 'active', nextDueDate: Carbon::now()->subDays(3));
        $this->makePlan(clientName: 'Modou Fall', status: 'active', nextDueDate: Carbon::now()->addDays(10));

        $this->assertSame(
            ['Awa Diop'],
            $this->filtered(ListClients::class, ['overdue' => ['value' => 'overdue']])
        );

        $this->assertSame(
            ['Modou Fall'],
            $this->filtered(ListClients::class, ['overdue' => ['value' => 'not_overdue']])
        );
    }

    public function test_clients_can_be_filtered_by_contract_status(): void
    {
        $this->makePlan(clientName: 'Actif', status: 'active');
        $this->makePlan(clientName: 'Solde', status: 'paid_in_full');
        Client::create(['full_name' => 'Aucun contrat']);

        $this->assertSame(
            ['Actif'],
            $this->filtered(ListClients::class, ['contract_status' => ['values' => ['active']]])
        );

        $this->assertSame(
            ['Solde'],
            $this->filtered(ListClients::class, ['contract_status' => ['values' => ['paid_in_full']]])
        );
    }

    public function test_clients_can_be_filtered_by_absence_of_contract(): void
    {
        $this->makePlan(clientName: 'Avec contrat');
        Client::create(['full_name' => 'Sans contrat']);

        $this->assertSame(
            ['Avec contrat'],
            $this->filtered(ListClients::class, ['has_contract' => ['value' => 'with']])
        );

        $this->assertSame(
            ['Sans contrat'],
            $this->filtered(ListClients::class, ['has_contract' => ['value' => 'without']])
        );
    }

    public function test_clients_can_be_filtered_by_device_status(): void
    {
        $this->makePlan(clientName: 'Bloque', deviceStatus: 'locked');
        $this->makePlan(clientName: 'Normal', deviceStatus: 'active');

        $this->assertSame(
            ['Bloque'],
            $this->filtered(ListClients::class, ['device_status' => ['values' => ['locked']]])
        );
    }

    public function test_clients_can_be_filtered_by_garant_presence(): void
    {
        // Le FK garant_id vit sur clients : le garant se crée d'abord, puis est
        // rattaché. Un create() via la relation BelongsTo ne le renseigne pas.
        $garant = Garant::create(['nom' => 'Tuteur', 'prenom' => 'Awa', 'telephone' => '77']);
        Client::create(['full_name' => 'Avec garant', 'garant_id' => $garant->id]);

        Client::create(['full_name' => 'Sans garant']);

        $this->assertSame(
            ['Avec garant'],
            $this->filtered(ListClients::class, ['has_garant' => ['value' => 'yes']])
        );

        $this->assertSame(
            ['Sans garant'],
            $this->filtered(ListClients::class, ['has_garant' => ['value' => 'no']])
        );
    }

    // ---------------------------------------------------------------- Devices

    public function test_devices_can_be_filtered_by_status(): void
    {
        $this->makePlan(clientName: 'Verrouille', deviceStatus: 'locked');
        $this->makePlan(clientName: 'Actif', deviceStatus: 'active');

        $this->assertSame(
            ['Verrouille'],
            $this->filtered(
                ListDevices::class,
                ['status' => ['values' => ['locked']]],
                'client.full_name'
            )
        );
    }

    public function test_devices_locked_filter_catches_amapi_disabled_even_when_status_is_active(): void
    {
        // Statut « active » en base mais verrouillé via AMAPI : seul le filtre
        // croisant device.status et amapi_devices.amapi_state le détecte.
        $this->makePlan(clientName: 'Verrouille AMAPI', deviceStatus: 'active', amapiState: 'DISABLED');
        $this->makePlan(clientName: 'Sain', deviceStatus: 'active', amapiState: 'ACTIVE');

        $this->assertSame(
            ['Verrouille AMAPI'],
            $this->filtered(
                ListDevices::class,
                ['is_locked' => ['isActive' => true]],
                'client.full_name'
            )
        );
    }

    public function test_devices_can_be_filtered_by_amapi_enrollment(): void
    {
        $this->makePlan(clientName: 'Enrole', amapiState: 'ACTIVE');
        $this->makePlan(clientName: 'Pas enrole', amapiState: null);

        $this->assertSame(
            ['Enrole'],
            $this->filtered(
                ListDevices::class,
                ['amapi_enrollment' => ['value' => 'enrolled']],
                'client.full_name'
            )
        );

        $this->assertSame(
            ['Pas enrole'],
            $this->filtered(
                ListDevices::class,
                ['amapi_enrollment' => ['value' => 'not_enrolled']],
                'client.full_name'
            )
        );
    }

    public function test_devices_can_be_filtered_by_enrollment_mode(): void
    {
        $this->makePlan(clientName: 'COPE', amapiState: 'ACTIVE', enrollmentMode: 'COPE');
        $this->makePlan(clientName: 'FM', amapiState: 'ACTIVE', enrollmentMode: 'FULLY_MANAGED');

        $this->assertSame(
            ['COPE'],
            $this->filtered(
                ListDevices::class,
                ['enrollment_mode' => ['values' => ['COPE']]],
                'client.full_name'
            )
        );

        $this->assertSame(
            ['FM'],
            $this->filtered(
                ListDevices::class,
                ['enrollment_mode' => ['values' => ['FULLY_MANAGED']]],
                'client.full_name'
            )
        );
    }

    public function test_devices_can_be_filtered_by_liberation(): void
    {
        // Soldé et sans AmapiDevice : libéré.
        $this->makePlan(clientName: 'Libere', status: 'paid_in_full', remainingBalance: 0, amapiState: null);
        // Soldé avec date de libération renseignée : libéré également.
        $this->makePlan(
            clientName: 'Libere date',
            status: 'paid_in_full',
            remainingBalance: 0,
            amapiState: 'LIBERATED',
            amapiReleasedAt: Carbon::now()->subDay(),
        );
        // Encore sous contrôle.
        $this->makePlan(clientName: 'Sous controle', status: 'active', amapiState: 'ACTIVE');
        // Soldé mais toujours enrôlé, sans date de libération.
        $this->makePlan(clientName: 'Pas encore libere', status: 'paid_in_full', remainingBalance: 0, amapiState: 'ACTIVE');

        $liberated = $this->filtered(
            ListDevices::class,
            ['liberated' => ['value' => 'liberated']],
            'client.full_name'
        );
        sort($liberated);

        $this->assertSame(['Libere', 'Libere date'], $liberated);

        // Un appareil AMAPI toujours enrôlé reste sous contrôle, même si le
        // contrat est soldé : la libération n'a pas encore été effectuée.
        $notLiberated = $this->filtered(
            ListDevices::class,
            ['liberated' => ['value' => 'not_liberated']],
            'client.full_name'
        );
        sort($notLiberated);

        $this->assertSame(['Pas encore libere', 'Sous controle'], $notLiberated);
    }

    public function test_devices_can_be_filtered_by_lock_duration(): void
    {
        $old = $this->makePlan(clientName: 'Verrouille depuis 30 jours', deviceStatus: 'locked');
        $this->makePlan(clientName: 'Verrouille aujourdhui', deviceStatus: 'locked');

        DeviceLockHistory::create([
            'device_id' => $old->device_id,
            'financing_plan_id' => $old->id,
            'action' => 'LOCK',
            'trigger_reason' => 'MANUAL_ADMIN',
            'status' => 'SUCCESS',
            'created_at' => Carbon::now()->subDays(30),
        ]);

        $this->assertSame(
            ['Verrouille depuis 30 jours'],
            $this->filtered(
                ListDevices::class,
                ['locked_since' => ['isActive' => true, 'days' => 7], 'is_locked' => ['isActive' => true]],
                'client.full_name'
            )
        );
    }

    public function test_devices_can_be_filtered_by_last_seen_period(): void
    {
        $recent = $this->makePlan(clientName: 'Vu hier');
        $recent->device->update(['last_seen_at' => Carbon::now()->subHours(5)]);

        $stale = $this->makePlan(clientName: 'Vu il y a un mois');
        $stale->device->update(['last_seen_at' => Carbon::now()->subDays(40)]);

        $this->assertSame(
            ['Vu hier'],
            $this->filtered(
                ListDevices::class,
                ['last_seen' => ['isActive' => true, 'range' => '24h']],
                'client.full_name'
            )
        );

        $this->assertSame(
            ['Vu hier'],
            $this->filtered(
                ListDevices::class,
                ['last_seen' => ['isActive' => true, 'range' => '7d']],
                'client.full_name'
            )
        );
    }

    // ----------------------------------------------------------------- Phones

    public function test_phones_can_be_filtered_by_stock_state(): void
    {
        Phone::create(['brand' => 'Tecno', 'model' => 'Rupture', 'price' => 100_000, 'stock' => 0]);
        Phone::create(['brand' => 'Tecno', 'model' => 'Bas', 'price' => 100_000, 'stock' => 2]);
        Phone::create(['brand' => 'Samsung', 'model' => 'Plein', 'price' => 100_000, 'stock' => 40]);

        $this->assertSame(
            ['Rupture'],
            $this->filtered(ListPhones::class, ['stock_alert' => ['isActive' => true, 'state' => 'out']], 'model')
        );

        $this->assertSame(
            ['Bas'],
            $this->filtered(ListPhones::class, ['stock_alert' => [
                'isActive' => true,
                'state' => 'low',
                'threshold' => 3,
            ]], 'model')
        );

        $this->assertCount(2, $this->filtered(ListPhones::class, ['stock_alert' => ['isActive' => true, 'state' => 'in_stock']], 'model'));
    }

    public function test_phones_can_be_filtered_by_price_range(): void
    {
        Phone::create(['brand' => 'A', 'model' => 'Pas cher', 'price' => 50_000, 'stock' => 1]);
        Phone::create(['brand' => 'A', 'model' => 'Moyen', 'price' => 150_000, 'stock' => 1]);
        Phone::create(['brand' => 'A', 'model' => 'Cher', 'price' => 500_000, 'stock' => 1]);

        $this->assertSame(
            ['Moyen'],
            $this->filtered(ListPhones::class, ['price_range' => [
                'isActive' => true,
                'min' => 100_000,
                'max' => 200_000,
            ]], 'model')
        );
    }

    public function test_phones_can_be_filtered_by_brand(): void
    {
        Phone::create(['brand' => 'Tecno', 'model' => 'Spark', 'price' => 100_000, 'stock' => 1]);
        Phone::create(['brand' => 'Samsung', 'model' => 'A15', 'price' => 100_000, 'stock' => 1]);

        $this->assertSame(
            ['A15'],
            $this->filtered(ListPhones::class, ['brand' => ['values' => ['Samsung']]], 'model')
        );
    }

    // -------------------------------------------------------------- Penalties

    public function test_penalties_can_be_filtered_by_type(): void
    {
        $this->makePenalty(clientName: 'Fixe', type: 'fixed_5000');
        $this->makePenalty(clientName: 'Variable', type: 'variable_5pct');

        $this->assertSame(
            ['Fixe'],
            $this->filtered(
                ListPenalties::class,
                ['type' => ['values' => ['fixed_5000']]],
                'financingPlan.registrationToken.client.full_name'
            )
        );
    }

    public function test_penalties_can_be_filtered_by_client(): void
    {
        $this->makePenalty(clientName: 'Awa Diop');
        $clientId = Client::where('full_name', 'Awa Diop')->value('id');
        $this->makePenalty(clientName: 'Modou Fall');

        $this->assertSame(
            ['Awa Diop'],
            $this->filtered(
                ListPenalties::class,
                ['client' => ['values' => [$clientId]]],
                'financingPlan.registrationToken.client.full_name'
            )
        );
    }

    public function test_penalties_can_be_filtered_by_amount_range(): void
    {
        $this->makePenalty(clientName: 'Petit', type: 'fixed_5000', amount: 5_000);
        $this->makePenalty(clientName: 'Grand', type: 'fixed_10000', amount: 10_000);

        $this->assertSame(
            ['Grand'],
            $this->filtered(
                ListPenalties::class,
                ['amount_range' => ['isActive' => true, 'min' => 8_000]],
                'financingPlan.registrationToken.client.full_name'
            )
        );
    }

    public function test_penalties_can_be_filtered_by_contract_status(): void
    {
        $this->makePenalty(clientName: 'Actif', planStatus: 'active');
        $this->makePenalty(clientName: 'Solde', planStatus: 'paid_in_full');

        $this->assertSame(
            ['Actif'],
            $this->filtered(
                ListPenalties::class,
                ['contract_status' => ['values' => ['active']]],
                'financingPlan.registrationToken.client.full_name'
            )
        );
    }

    // ------------------------------------------------------------------ Utils

    /**
     * Applique un filtre de table puis retourne les valeurs de $column
     * réellement retenues par la requête filtrée.
     *
     * @param  array<string, mixed>  $filters
     * @return list<string>
     */
    private function filtered(string $page, array $filters, string $column = 'full_name'): array
    {
        $component = Livewire::test($page)->assertOk();

        $component->set('tableFilters', $filters);

        $query = $component->instance()->getFilteredTableQuery();

        $this->assertInstanceOf(Builder::class, $query, 'La requête filtrée est injoignable.');

        return $query
            ->with($this->relationsFor($column))
            ->get()
            ->map(fn ($record) => data_get($record, $column))
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function relationsFor(string $column): array
    {
        return match ($column) {
            'client.full_name' => ['client'],
            'financingPlan.registrationToken.client.full_name' => ['financingPlan.registrationToken.client'],
            default => [],
        };
    }

    private function makePlan(
        string $clientName,
        string $status = 'active',
        float $remainingBalance = 100_000,
        ?Carbon $nextDueDate = null,
        string $deviceStatus = 'active',
        ?string $amapiState = null,
        ?string $enrollmentMode = null,
        ?Carbon $amapiReleasedAt = null,
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
            'device_name' => 'Appareil de '.$clientName,
            'device_id' => 'dev-'.uniqid(),
            'status' => $deviceStatus,
        ]);

        if ($amapiState !== null) {
            AmapiDevice::create([
                'device_id' => $device->id,
                'amapi_enterprise_id' => 'enterprises/payasgo-test',
                'amapi_state' => $amapiState,
                'enrollment_mode' => $enrollmentMode ?? 'FULLY_MANAGED',
                'amapi_released_at' => $amapiReleasedAt,
            ]);
        }

        return Financing_plan::create([
            'device_id' => $device->id,
            'registration_token_id' => $token->id,
            'total_price' => 200_000,
            'down_payment' => 50_000,
            'remaining_balance' => $remainingBalance,
            'installment_amount' => 25_000,
            'status' => $status,
            'amapi_sync_status' => 'synced',
            'days_interval' => 30,
            'next_payment_due_date' => $nextDueDate,
        ]);
    }

    private function makePenalty(
        string $clientName,
        string $type = 'fixed_5000',
        int $amount = 5_000,
        string $planStatus = 'active',
    ): Penalty {
        $plan = $this->makePlan(clientName: $clientName, status: $planStatus);

        return Penalty::create([
            'financing_plan_id' => $plan->id,
            'type' => $type,
            'amount' => $amount,
            'reason' => 'Test',
        ]);
    }
}
