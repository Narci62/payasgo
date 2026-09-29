@php
    use App\Filament\Resources\FinancingPlans\FinancingPlanResource;
@endphp

<x-filament-widgets::widget>
    <div class="pg-panel">
        <div class="pg-panel-header">
            <div>
                <h2 class="pg-panel-title">
                    <x-filament::icon icon="heroicon-o-device-phone-mobile" class="size-5 text-primary-600 dark:text-primary-400" />
                    Contrats &amp; appareils
                </h2>
                <p class="pg-panel-subtitle mt-0.5">
                    Vue unifiée : chaque ligne associe un plan de financement à son appareil.
                </p>
            </div>

            <x-filament::button
                tag="a"
                :href="FinancingPlanResource::getUrl('index')"
                icon="heroicon-m-arrow-right"
                color="gray"
                size="sm"
            >
                Tous les plans
            </x-filament::button>
        </div>

        @if ($rows->isEmpty())
            <div class="pg-empty">
                <x-filament::icon icon="heroicon-o-inbox" class="size-8 text-gray-400 dark:text-gray-600" />
                <p class="pg-empty-title">Aucun contrat enregistré</p>
                <p class="pg-empty-text">Les ventes financées apparaîtront ici avec le statut de leur appareil.</p>
            </div>
        @else
            <div class="pg-panel-body">
                <table class="pg-table">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Appareil</th>
                            <th>Contrat</th>
                            <th>Appareil</th>
                            <th class="pg-num">Solde restant</th>
                            <th>Prochaine échéance</th>
                            <th>Code déverrouillage</th>
                            <th class="pg-cell-actions"><span class="fi-sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr @class(['pg-overdue' => $row['overdue']])>
                                <td>
                                    <span class="pg-cell-strong">{{ $row['client'] }}</span>
                                </td>

                                <td>
                                    <span class="pg-cell-strong">{{ $row['phone'] }}</span>
                                </td>

                                <td>
                                    <x-filament::badge :color="$row['contract_tone']">
                                        {{ $row['contract_status'] }}
                                    </x-filament::badge>
                                </td>

                                <td>
                                    <x-filament::badge :color="$row['device_tone']">
                                        {{ $row['device_status'] }}
                                    </x-filament::badge>
                                </td>

                                <td class="pg-num">{{ $row['remaining_balance'] }}</td>

                                <td>
                                    @if ($row['next_payment_due_date'])
                                        <span @class(['pg-cell-strong', 'text-danger-600 dark:text-danger-400' => $row['overdue']])>
                                            {{ \Illuminate\Support\Carbon::parse($row['next_payment_due_date'])->format('d/m/Y') }}
                                        </span>

                                        @if ($row['overdue'])
                                            <span class="pg-cell-muted block">Échéance dépassée</span>
                                        @endif
                                    @else
                                        <span class="pg-cell-muted">—</span>
                                    @endif
                                </td>

                                <td>
                                    @if ($row['unlock_code'])
                                        <code class="pg-cell-muted">{{ $row['unlock_code'] }}</code>
                                    @else
                                        <span class="pg-cell-muted">—</span>
                                    @endif
                                </td>

                                <td class="pg-cell-actions">
                                    <x-filament::icon-button
                                        tag="a"
                                        :href="$row['edit_url']"
                                        icon="heroicon-m-pencil-square"
                                        color="gray"
                                        size="sm"
                                        label="Ouvrir le contrat"
                                    />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($contracts->hasPages())
                <div class="pg-footer">
                    <span class="pg-cell-muted">
                        {{ $contracts->total() }} contrat(s) au total
                    </span>

                    <x-filament::pagination :paginator="$contracts" />
                </div>
            @endif
        @endif
    </div>
</x-filament-widgets::widget>
