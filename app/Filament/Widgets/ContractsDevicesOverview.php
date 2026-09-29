<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\EditsFinancingPlan;
use App\Models\Financing_plan;
use App\Services\DashboardService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Vue unifiée contrat + appareil, avec filtres et édition en modale.
 *
 * Ancien tableau Blade artisanal, remplacé par un TableWidget afin de disposer
 * des filtres, du tri et de la pagination natifs de Filament.
 */
class ContractsDevicesOverview extends TableWidget
{
    use EditsFinancingPlan;

    protected static ?string $heading = 'Contrats & appareils';

    protected static ?string $description = 'Vue unifiée : chaque ligne associe un plan de financement à son appareil.';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        $service = app(DashboardService::class);

        return $table
            ->query(fn (): Builder => Financing_plan::query()->with([
                'registrationToken.client',
                'device.phone',
                'device.amapiDevice',
            ]))
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(15)
            ->paginationPageOptions([15, 30, 50])
            // Libellés repris de l'ancien Blade pour conserver l'affichage du back-office.
            ->emptyStateHeading('Aucun contrat enregistré')
            ->emptyStateDescription('Les ventes financées apparaîtront ici avec le statut de leur appareil.')
            ->columns([
                TextColumn::make('registrationToken.client.full_name')
                    ->label('Client')
                    ->sortable()
                    ->searchable()
                    ->default('Client inconnu'),

                TextColumn::make('device.phone.brand')
                    ->label('Marque')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('device.device_name')
                    ->label('Appareil')
                    ->sortable()
                    ->searchable()
                    ->default('—')
                    ->description(fn (Financing_plan $record): ?string => $service->phoneLabel($record)),

                TextColumn::make('status')
                    ->label('Contrat')
                    ->badge()
                    ->color(fn (Financing_plan $record): string => $service->contractStatusTone($record))
                    // Relecture de la valeur brute : l'accessor du modèle
                    // renvoie le libellé accentué au lieu de l'enum.
                    ->formatStateUsing(fn (Financing_plan $record): string => $service->contractStatusLabel($record))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('device')
                    ->label('État appareil')
                    ->badge()
                    ->color(fn (Financing_plan $record): string => $service->deviceStatusTone($record))
                    ->formatStateUsing(fn (Financing_plan $record): string => $service->deviceStatusLabel($record)),

                TextColumn::make('remaining_balance')
                    ->label('Solde restant')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => $service->formatAmount($state)),

                TextColumn::make('next_payment_due_date')
                    ->label('Prochaine échéance')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn (Financing_plan $record): ?string => $service->isOverdue($record) ? 'danger' : null)
                    ->description(fn (Financing_plan $record): ?string => $service->isOverdue($record) ? 'Échéance dépassée' : null)
                    ->placeholder('—'),

                TextColumn::make('next_offline_unlock_code')
                    ->label('Code déverrouillage')
                    ->copyable()
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('Inscrit le')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut du contrat')
                    ->options([
                        'active' => 'Actif',
                        'paid_in_full' => 'Soldé',
                        'defaulted' => 'En attente',
                    ])
                    ->multiple(),

                SelectFilter::make('device_status')
                    ->label('État de l\'appareil')
                    ->options([
                        'locked' => 'Verrouillé',
                        'liberated' => 'Libéré',
                        'active' => 'Actif',
                        'provisioning' => 'Enrôlement',
                        'not_enrolled' => 'Non enrôlé',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $values = (array) ($data['values'] ?? $data['value'] ?? []);

                        if (empty($values)) {
                            return $query;
                        }

                        return collect($values)->reduce(function (Builder $carry, string $value): Builder {
                            return $carry->where(fn (Builder $q) => match ($value) {
                                'locked' => $q
                                    ->whereHas('device', fn (Builder $d) => $d->whereIn('status', ['locked', 'disabled']))
                                    ->orWhereHas('device.amapiDevice', fn (Builder $a) => $a->where('amapi_state', 'DISABLED')),
                                'liberated' => $q
                                    ->whereHas('device', fn (Builder $d) => $d
                                        ->whereHas('financingPlan', fn (Builder $p) => $p
                                            ->where('status', 'paid_in_full')
                                            ->where('remaining_balance', 0)
                                        )
                                        ->where(fn (Builder $inner) => $inner
                                            ->whereDoesntHave('amapiDevice')
                                            ->orWhereHas('amapiDevice', fn (Builder $a) => $a->whereNotNull('amapi_released_at'))
                                        )
                                    ),
                                'active' => $q->whereHas('device', fn (Builder $d) => $d->where('status', 'active')),
                                'provisioning' => $q->whereHas('device.amapiDevice', fn (Builder $a) => $a->where('amapi_state', 'PROVISIONING')),
                                'not_enrolled' => $q->whereDoesntHave('device.amapiDevice'),
                                default => $q,
                            });
                        }, $query);
                    }),

                Filter::make('search')
                    ->label('Recherche (client, appareil, IMEI, code)')
                    ->schema([
                        TextInput::make('term')
                            ->label('Recherche')
                            ->placeholder('Awa Diop, Tecno, 1234-5678…'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $term = trim((string) ($data['term'] ?? ''));

                        if ($term === '') {
                            return $query;
                        }

                        $like = '%'.str_replace('%', '\%', $term).'%';

                        return $query->where(function (Builder $q) use ($like): void {
                            $q->whereHas('registrationToken.client', fn (Builder $c) => $c
                                ->where('full_name', 'like', $like)
                                ->orWhere('phone_number', 'like', $like)
                                ->orWhere('reference', 'like', $like)
                            )
                                ->orWhereHas('device', fn (Builder $d) => $d
                                    ->where('device_name', 'like', $like)
                                    ->orWhere('device_id', 'like', $like)
                                    ->orWhere('imei', 'like', $like)
                                )
                                ->orWhereHas('device.phone', fn (Builder $p) => $p
                                    ->where('brand', 'like', $like)
                                    ->orWhere('model', 'like', $like)
                                )
                                ->orWhere('next_offline_unlock_code', 'like', $like);
                        });
                    }),

                Filter::make('overdue')
                    ->label('Échéance dépassée')
                    ->query(function (Builder $query, array $data): Builder {
                        if (! ($data['isActive'] ?? false)) {
                            return $query;
                        }

                        return $query
                            ->where('status', 'active')
                            ->whereNotNull('next_payment_due_date')
                            ->where('next_payment_due_date', '<', now());
                    }),

                Filter::make('created_period')
                    ->label('Période d\'inscription')
                    ->schema([
                        Select::make('range')
                            ->label('Période')
                            ->options([
                                '7d' => '7 derniers jours',
                                '30d' => '30 derniers jours',
                                '90d' => '90 derniers jours',
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! ($data['isActive'] ?? false)) {
                            return $query;
                        }

                        return match ($data['range'] ?? null) {
                            '7d' => $query->where('created_at', '>=', now()->subDays(7)),
                            '30d' => $query->where('created_at', '>=', now()->subDays(30)),
                            '90d' => $query->where('created_at', '>=', now()->subDays(90)),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                $this->editFinancingPlanAction()
                    ->modalHeading(fn (Financing_plan $record): string => 'Contrat — '.($record->registrationToken?->client?->full_name ?? 'Client inconnu'))
                    ->modalDescription('Les montants et l\'échéancier sont modifiables ici. Le client et le téléphone ne le sont pas : ils définissent l\'appareil rattaché au contrat.'),

                Action::make('view_payments')
                    ->label('Voir les paiements')
                    ->icon('heroicon-o-currency-dollar')
                    ->modalHeading('Historique des paiements')
                    ->modalWidth('lg')
                    ->modalContent(fn (Financing_plan $record) => view('filament.resources.financing-plans.view-payments', [
                        'payments' => $record->payments,
                    ])),
            ])
            ->recordUrl(null);
    }
}
