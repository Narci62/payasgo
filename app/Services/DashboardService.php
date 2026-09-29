<?php

namespace App\Services;

use App\Filament\Resources\Devices\DeviceResource;
use App\Filament\Resources\FinancingPlans\FinancingPlanResource;
use App\Models\AmapiDevice;
use App\Models\Device;
use App\Models\Financing_plan;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Agrégations en lecture seule pour la page d'accueil de l'admin.
 *
 * Aucune écriture, aucun appel AMAPI : toutes les données proviennent
 * directement des tables devices / amapi_devices / financing_plans / payments.
 */
class DashboardService
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAID_IN_FULL = 'paid_in_full';

    public const STATUS_DEFAULTED = 'defaulted';

    /**
     * Indicateurs clés de la page d'accueil.
     *
     * @return array<int, array{key: string, label: string, value: string, hint: string, icon: string, tone: string}>
     */
    public function kpis(): array
    {
        return [
            [
                'key' => 'outstanding',
                'label' => 'Encours à recouvrer',
                'value' => $this->formatAmount($this->outstandingBalance()),
                'hint' => $this->outstandingContractsCount().' contrat(s) non soldé(s)',
                'icon' => 'heroicon-o-banknotes',
                'tone' => 'primary',
            ],
            [
                'key' => 'active',
                'label' => 'Contrats actifs',
                'value' => $this->activeContractsQuery()->count(),
                'hint' => 'Mensualités en cours',
                'icon' => 'heroicon-o-document-text',
                'tone' => 'info',
            ],
            [
                'key' => 'overdue',
                'label' => 'Contrats en retard',
                'value' => $this->overdueContractsQuery()->count(),
                'hint' => 'Échéance dépassée',
                'icon' => 'heroicon-o-exclamation-triangle',
                'tone' => 'danger',
            ],
            [
                'key' => 'collected',
                'label' => 'Montant encaissé',
                'value' => $this->formatAmount($this->collectedAmount()),
                'hint' => 'Acomptes et versements',
                'icon' => 'heroicon-o-check-circle',
                'tone' => 'success',
            ],
            [
                'key' => 'locked',
                'label' => 'Appareils verrouillés',
                'value' => $this->lockedDevicesQuery()->count(),
                'hint' => $this->enrolledDevicesCount().' appareil(s) enrôlé(s)',
                'icon' => 'heroicon-o-lock-closed',
                'tone' => 'danger',
            ],
            [
                'key' => 'enrolled',
                'label' => 'Parc enrôlé',
                'value' => $this->enrolledDevicesCount(),
                'hint' => 'Contrôlés via AMAPI',
                'icon' => 'heroicon-o-device-phone-mobile',
                'tone' => 'primary',
            ],
        ];
    }

    /**
     * Solde restant cumulé sur les contrats non soldés.
     */
    public function outstandingBalance(): float
    {
        return (float) Financing_plan::query()
            ->where('status', '!=', self::STATUS_PAID_IN_FULL)
            ->sum('remaining_balance');
    }

    public function outstandingContractsCount(): int
    {
        return Financing_plan::query()
            ->where('status', '!=', self::STATUS_PAID_IN_FULL)
            ->count();
    }

    /**
     * Total réellement encaissé : l'acompte de départ est lui aussi
     * enregistré dans la table payments, il n'est donc pas compté deux fois.
     */
    public function collectedAmount(): float
    {
        return (float) Payment::query()
            ->where('status', 'completed')
            ->sum('amount');
    }

    public function enrolledDevicesCount(): int
    {
        return AmapiDevice::query()
            ->where('amapi_state', '!=', 'DELETED')
            ->count();
    }

    public function activeContractsQuery()
    {
        return Financing_plan::query()->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Contrats actifs dont l'échéance est déjà dépassée.
     */
    public function overdueContractsQuery()
    {
        return $this->activeContractsQuery()
            ->whereNotNull('next_payment_due_date')
            ->where('next_payment_due_date', '<', Carbon::now());
    }

    /**
     * Un appareil est considéré verrouillé si son statut local est
     * "locked" ou si AMAPI renvoie un état DISABLED.
     */
    public function lockedDevicesQuery()
    {
        return Device::query()
            ->where(function ($query) {
                $query->where('status', 'locked')
                    ->orWhereHas('amapiDevice', fn ($amapi) => $amapi->where('amapi_state', 'DISABLED'));
            });
    }

    /**
     * Tableau unifié : une ligne par contrat, avec le statut de son appareil.
     */
    public function contractsWithDevices(int $perPage = 15, ?string $pageName = null): LengthAwarePaginator
    {
        return Financing_plan::query()
            ->with([
                'registrationToken.client',
                'device.phone',
                'device.amapiDevice',
            ])
            ->latest('created_at')
            ->paginate($perPage, ['*'], $pageName ?? 'contracts-page');
    }

    /**
     * Statut lisible du contrat.
     *
     * On lit la valeur brute : l'accessor Financing_plan::getStatusAttribute()
     * réécrit le statut à la lecture et retournerait "actif"/"soldé"/"En attente".
     */
    public function contractStatusLabel(?Financing_plan $plan): string
    {
        if (! $plan) {
            return 'Inconnu';
        }

        return match ($plan->getRawOriginal('status')) {
            self::STATUS_ACTIVE => 'Actif',
            self::STATUS_PAID_IN_FULL => 'Soldé',
            self::STATUS_DEFAULTED => 'En attente',
            default => 'Inconnu',
        };
    }

    /**
     * Statut lisible de l'appareil rattaché au contrat.
     */
    public function deviceStatusLabel(?Financing_plan $plan): string
    {
        $device = $plan?->device;

        if (! $device) {
            return 'Aucun appareil';
        }

        $amapiState = $device->amapiDevice?->amapi_state;

        if ($device->status === 'disabled' || $amapiState === 'DISABLED' || $device->status === 'locked') {
            return 'Verrouillé';
        }

        if ($this->isLiberated($plan, $device)) {
            return 'Libéré';
        }

        return match ($amapiState) {
            'ACTIVE' => 'Actif',
            'PROVISIONING' => 'Enrôlement',
            null, '' => 'Non enrôlé',
            default => 'Inconnu',
        };
    }

    /**
     * Reprend la même logique que Device::isLiberated() sans passer par
     * shouldBeLocked(), qui résoudrait le service de monitoring AMAPI.
     */
    public function isLiberated(Financing_plan $plan, Device $device): bool
    {
        return $plan->getRawOriginal('status') === self::STATUS_PAID_IN_FULL
            && (float) ($plan->remaining_balance ?? 0) == 0.0
            && ! $device->amapiDevice;
    }

    /**
     * Groupes d'alertes du bloc bas de page.
     *
     * @return array<int, array{key: string, tone: string, icon: string, title: string, count: int, detail: string, url: string|null}>
     */
    public function alerts(): array
    {
        $alerts = [];

        $overdue = $this->overdueContractsQuery()->count();

        $alerts[] = [
            'key' => 'overdue',
            'tone' => $overdue > 0 ? 'danger' : 'success',
            'icon' => 'heroicon-o-clock',
            'title' => 'Contrats en retard de paiement',
            'count' => $overdue,
            'detail' => $overdue > 0
                ? 'Échéance dépassée : relance client ou verrouillage à prévoir.'
                : 'Aucune échéance dépassée.',
            'url' => $overdue > 0
                ? FinancingPlanResource::getUrl('index')
                : null,
        ];

        $syncFailed = Financing_plan::query()
            ->where('amapi_sync_status', 'failed')
            ->count();

        $alerts[] = [
            'key' => 'sync_failed',
            'tone' => $syncFailed > 0 ? 'warning' : 'success',
            'icon' => 'heroicon-o-arrow-path',
            'title' => 'Échecs de synchronisation AMAPI',
            'count' => $syncFailed,
            'detail' => $syncFailed > 0
                ? 'La dernière commande envoyée à Google a échoué.'
                : 'Aucune commande en échec.',
            'url' => $syncFailed > 0
                ? DeviceResource::getUrl('index')
                : null,
        ];

        $locked = $this->lockedDevicesQuery()->count();

        $alerts[] = [
            'key' => 'locked',
            'tone' => $locked > 0 ? 'danger' : 'success',
            'icon' => 'heroicon-o-lock-closed',
            'title' => 'Appareils verrouillés',
            'count' => $locked,
            'detail' => $locked > 0
                ? 'Appareils sous contrôle actif ou désactivés par AMAPI.'
                : 'Aucun appareil verrouillé.',
            'url' => $locked > 0
                ? DeviceResource::getUrl('index')
                : null,
        ];

        $unprovisioned = Device::query()
            ->where(function ($query) {
                $query->whereNull('last_seen_at')
                    ->orWhere('last_seen_at', '<', Carbon::now()->subDays(7));
            })
            ->whereDoesntHave('amapiDevice')
            ->count();

        $alerts[] = [
            'key' => 'offline',
            'tone' => $unprovisioned > 0 ? 'warning' : 'success',
            'icon' => 'heroicon-o-signal-slash',
            'title' => 'Appareils injoignables ou non enrôlés',
            'count' => $unprovisioned,
            'detail' => 'Jamais connectés ou sans contact depuis plus de 7 jours.',
            'url' => $unprovisioned > 0
                ? DeviceResource::getUrl('index')
                : null,
        ];

        $dueSoon = $this->activeContractsQuery()
            ->whereNotNull('next_payment_due_date')
            ->whereBetween('next_payment_due_date', [Carbon::now(), Carbon::now()->addDays(7)])
            ->count();

        $alerts[] = [
            'key' => 'due_soon',
            'tone' => 'info',
            'icon' => 'heroicon-o-calendar-days',
            'title' => 'Échéances sous 7 jours',
            'count' => $dueSoon,
            'detail' => 'Contrats dont la prochaine échéance approche.',
            'url' => $dueSoon > 0
                ? FinancingPlanResource::getUrl('index')
                : null,
        ];

        $pendingUninstall = Financing_plan::query()
            ->where('status', self::STATUS_PAID_IN_FULL)
            ->where(fn ($query) => $query->whereNull('amapi_sync_status')->orWhere('amapi_sync_status', '!=', 'synced'))
            ->count();

        $alerts[] = [
            'key' => 'pending_uninstall',
            'tone' => $pendingUninstall > 0 ? 'info' : 'success',
            'icon' => 'heroicon-o-sparkles',
            'title' => 'Contrats soldés à libérer',
            'count' => $pendingUninstall,
            'detail' => 'Soldés mais pas encore désenrôlés d’AMAPI.',
            'url' => $pendingUninstall > 0
                ? FinancingPlanResource::getUrl('index')
                : null,
        ];

        return $alerts;
    }

    /**
     * Met en forme un montant en francs CFA (XOF, sans décimales).
     */
    public function formatAmount(float|string|null $amount): string
    {
        return number_format((float) $amount, 0, ',', ' ').' FCFA';
    }

    /**
     * Nombre de contrats soldés, utilisé comme repère dans les tests.
     */
    public function settledContractsCount(): int
    {
        return Financing_plan::query()
            ->where('status', self::STATUS_PAID_IN_FULL)
            ->count();
    }

    /**
     * @param  Collection<int, Financing_plan>  $plans
     * @return Collection<int, array<string, mixed>>
     */
    public function decoratePlans(Collection $plans): Collection
    {
        return $plans->map(fn (Financing_plan $plan) => [
            'plan' => $plan,
            'client' => $plan->registrationToken?->client?->full_name ?? 'Client inconnu',
            'phone' => $this->phoneLabel($plan),
            'contract_status' => $this->contractStatusLabel($plan),
            'contract_tone' => $this->contractStatusTone($plan),
            'device_status' => $this->deviceStatusLabel($plan),
            'device_tone' => $this->deviceStatusTone($plan),
            'overdue' => $this->isOverdue($plan),
            'remaining_balance' => $this->formatAmount($plan->remaining_balance),
            'next_payment_due_date' => $plan->next_payment_due_date,
            'unlock_code' => $plan->next_offline_unlock_code,
            'edit_url' => FinancingPlanResource::getUrl('edit', ['record' => $plan]),
        ]);
    }

    public function contractStatusTone(?Financing_plan $plan): string
    {
        return match ($plan?->getRawOriginal('status')) {
            self::STATUS_ACTIVE => 'success',
            self::STATUS_PAID_IN_FULL => 'primary',
            self::STATUS_DEFAULTED => 'warning',
            default => 'gray',
        };
    }

    public function deviceStatusTone(?Financing_plan $plan): string
    {
        $label = $this->deviceStatusLabel($plan);

        return match ($label) {
            'Verrouillé' => 'danger',
            'Libéré' => 'primary',
            'Actif' => 'success',
            'Enrôlement' => 'info',
            'Non enrôlé' => 'warning',
            default => 'gray',
        };
    }

    public function isOverdue(?Financing_plan $plan): bool
    {
        if (! $plan || ! $plan->next_payment_due_date) {
            return false;
        }

        return $plan->getRawOriginal('status') === self::STATUS_ACTIVE
            && Carbon::parse($plan->next_payment_due_date)->isPast();
    }

    public function phoneLabel(Financing_plan $plan): string
    {
        $phone = $plan->device?->phone;

        if (! $phone) {
            return $plan->device?->device_name ?? '—';
        }

        return trim($phone->brand.' '.$phone->model) ?: $plan->device?->device_name ?? '—';
    }
}
