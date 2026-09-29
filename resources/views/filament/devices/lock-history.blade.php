@php
    $actionLabels = [
        'LOCK' => ['label' => 'Verrouillé', 'color' => 'danger', 'icon' => 'heroicon-m-lock-closed'],
        'UNLOCK' => ['label' => 'Déverrouillé', 'color' => 'success', 'icon' => 'heroicon-m-lock-open'],
        'LOCK_ATTEMPT' => ['label' => 'Tentative de verrouillage', 'color' => 'danger', 'icon' => 'heroicon-m-arrow-path'],
        'UNLOCK_ATTEMPT' => ['label' => 'Tentative de déverrouillage', 'color' => 'success', 'icon' => 'heroicon-m-arrow-path'],
        'DELETE_ATTEMPT' => ['label' => 'Tentative de désinstallation', 'color' => 'warning', 'icon' => 'heroicon-m-no-symbol'],
        'RELINQUISH_OWNERSHIP' => ['label' => 'Propriété cédée', 'color' => 'info', 'icon' => 'heroicon-m-arrow-right-on-rectangle'],
        'RELINQUISH_OWNERSHIP_ATTEMPT' => ['label' => 'Tentative de cession', 'color' => 'info', 'icon' => 'heroicon-m-arrow-path'],
    ];

    $reasonLabels = [
        'PAYMENT_OVERDUE' => ['label' => 'Retard de paiement', 'color' => 'danger'],
        'INACTIVITY_14_DAYS' => ['label' => 'Inactivité 14 jours', 'color' => 'warning'],
        'MANUAL_ADMIN' => ['label' => 'Action manuelle', 'color' => 'info'],
        'ADMIN_OVERRIDE' => ['label' => 'Override administrateur', 'color' => 'info'],
        'ADMIN_UNINSTALL' => ['label' => 'Désinstallation admin', 'color' => 'warning'],
        'RETRY_SYNC' => ['label' => 'Nouvelle tentative de synchronisation', 'color' => 'warning'],
        'PAYMENT_RECEIVED' => ['label' => 'Paiement reçu', 'color' => 'success'],
    ];

    $statusLabels = [
        'PENDING' => ['label' => 'En attente', 'color' => 'warning'],
        'SUCCESS' => ['label' => 'Réussi', 'color' => 'success'],
        'FAILED' => ['label' => 'Échec', 'color' => 'danger'],
    ];
@endphp

<div class="pg-history">
    @if ($lockHistory->isEmpty())
        <div class="pg-history-empty">
            <span class="pg-history-empty-icon">
                <x-filament::icon icon="heroicon-o-clock" class="size-5" />
            </span>

            <p class="pg-history-empty-title">Aucun verrouillage enregistré</p>

            <p class="pg-history-empty-text">
                Cet appareil n'a encore fait l'objet d'aucune demande de verrouillage ou de déverrouillage.
            </p>
        </div>
    @else
        <div class="pg-history-scroll">
            <table class="pg-history-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Action</th>
                        <th>Raison</th>
                        <th>Statut</th>
                        <th class="text-right">Encours</th>
                        <th>Client</th>
                        <th>Déclenché par</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lockHistory as $history)
                        @php
                            $action = $actionLabels[$history->action] ?? ['label' => (string) $history->action, 'color' => 'gray'];
                            $reason = $reasonLabels[$history->trigger_reason] ?? ['label' => (string) $history->trigger_reason, 'color' => 'gray'];
                            $status = $statusLabels[$history->status] ?? ['label' => (string) $history->status, 'color' => 'gray'];
                        @endphp

                        <tr>
                            <td class="pg-history-date">
                                {{ ($history->executed_at ?? $history->created_at)?->format('d/m/Y H:i') ?? '—' }}
                            </td>

                            <td class="pg-history-cell">
                                <x-filament::badge :color="$action['color']" :icon="$action['icon'] ?? null">
                                    {{ $action['label'] }}
                                </x-filament::badge>

                                @if (filled($history->error_message))
                                    <p class="pg-history-error">{{ $history->error_message }}</p>
                                @endif
                            </td>

                            <td class="pg-history-cell">
                                <x-filament::badge :color="$reason['color']">
                                    {{ $reason['label'] }}
                                </x-filament::badge>
                            </td>

                            <td class="pg-history-cell">
                                <x-filament::badge :color="$status['color']">
                                    {{ $status['label'] }}
                                </x-filament::badge>
                            </td>

                            <td class="pg-history-cell text-right tabular-nums">
                                @if ($history->remaining_balance !== null)
                                    <span class="font-medium text-gray-950 dark:text-white">
                                        {{ number_format((float) $history->remaining_balance, 0, ',', ' ') }} FCFA
                                    </span>
                                @else
                                    <span class="text-gray-400 dark:text-gray-500">—</span>
                                @endif
                            </td>

                            <td>
                                <span class="pg-history-client">
                                    {{ $history->device?->client?->full_name ?? '—' }}
                                </span>
                            </td>

                            <td class="pg-history-cell">
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $history->triggeredByUser?->name ?? 'Automatique' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
