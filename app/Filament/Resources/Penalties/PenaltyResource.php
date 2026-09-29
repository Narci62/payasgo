<?php

namespace App\Filament\Resources\Penalties;

use App\Filament\Resources\Penalties\Pages\ListPenalties;
use App\Models\Client;
use App\Models\Penalty;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PenaltyResource extends Resource
{
    protected static ?string $model = Penalty::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|\UnitEnum|null $navigationGroup = 'Statistiques';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Pénalités';

    protected static ?string $pluralLabel = 'Pénalités';

    public static function canViewAny(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'super-admin']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('financingPlan.registrationToken.client.full_name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('financingPlan.registrationToken.client.reference')
                    ->label('Référence')
                    ->searchable(),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'fixed_5000' => 'warning',
                        'fixed_10000' => 'danger',
                        'variable_5pct' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'fixed_5000' => 'Fixe 5 000 FCFA',
                        'fixed_10000' => 'Fixe 10 000 FCFA',
                        'variable_5pct' => 'Variable 5%',
                        default => 'N/A',
                    }),

                TextColumn::make('amount')
                    ->label('Montant')
                    ->money('XOF')
                    ->sortable(),

                TextColumn::make('installment.due_date')
                    ->label('Échéance')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('reason')
                    ->label('Raison')
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                // Rappel : chaque callback ->query() se garde sur « isActive ».
                // Filament l'évalue même sans état ; un filtre jamais choisi ne
                // doit jamais restreindre la liste.

                SelectFilter::make('type')
                    ->label('Type de pénalité')
                    ->options([
                        'fixed_5000' => 'Fixe 5 000 FCFA',
                        'fixed_10000' => 'Fixe 10 000 FCFA',
                        'variable_5pct' => 'Variable 5%',
                    ])
                    ->multiple(),

                SelectFilter::make('contract_status')
                    ->label('Statut du contrat')
                    ->options([
                        'active' => 'Actif',
                        'paid_in_full' => 'Soldé',
                        'defaulted' => 'En attente',
                    ])
                    ->multiple()
                    ->query(fn (Builder $query, array $data): Builder => empty($values = (array) ($data['values'] ?? $data['value'] ?? []))
                        ? $query
                        : $query->whereHas(
                            'financingPlan',
                            fn (Builder $plan) => $plan->whereIn('status', $values)
                        )),

                SelectFilter::make('client')
                    ->label('Client')
                    ->options(fn (): array => Client::query()->orderBy('full_name')->pluck('full_name', 'id')->all())
                    ->searchable()
                    ->multiple()
                    ->query(fn (Builder $query, array $data): Builder => empty($values = (array) ($data['values'] ?? $data['value'] ?? []))
                        ? $query
                        : $query->whereHas(
                            'financingPlan.registrationToken',
                            fn (Builder $token) => $token->whereIn('client_id', $values)
                        )),

                Filter::make('overdue_installment')
                    ->label('Échéance dépassée')
                    ->schema([
                        Select::make('range')
                            ->label('Retard de')
                            ->options([
                                'any' => 'N\'importe quel retard',
                                '7' => 'Plus de 7 jours',
                                '30' => 'Plus de 30 jours',
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! ($data['isActive'] ?? false)) {
                            return $query;
                        }

                        $days = match ($data['range'] ?? null) {
                            '7' => 7,
                            '30' => 30,
                            default => 0,
                        };

                        return $query->whereHas(
                            'installment',
                            fn (Builder $i) => $days > 0
                                ? $i->whereDate('due_date', '<=', now()->subDays($days))
                                : $i->whereDate('due_date', '<', now())
                        );
                    }),

                Filter::make('amount_range')
                    ->label('Montant (XOF)')
                    ->schema([
                        TextInput::make('min')
                            ->label('Montant minimum')
                            ->numeric(),
                        TextInput::make('max')
                            ->label('Montant maximum')
                            ->numeric(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! ($data['isActive'] ?? false)) {
                            return $query;
                        }

                        if (filled($data['min'] ?? null)) {
                            $query->where('amount', '>=', (float) $data['min']);
                        }

                        if (filled($data['max'] ?? null)) {
                            $query->where('amount', '<=', (float) $data['max']);
                        }

                        return $query;
                    }),

                Filter::make('created_period')
                    ->label('Période de création')
                    ->schema([
                        Select::make('range')
                            ->label('Période')
                            ->options([
                                '7d' => '7 derniers jours',
                                '30d' => '30 derniers jours',
                                '90d' => '90 derniers jours',
                                'year' => 'Année en cours',
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! ($data['isActive'] ?? false)) {
                            return $query;
                        }

                        return match ($data['range'] ?? null) {
                            '7d' => $query->where('created_at', '>=', now()->subDays(7)),
                            '90d' => $query->where('created_at', '>=', now()->subDays(90)),
                            'year' => $query->whereYear('created_at', now()->year),
                            '30d' => $query->where('created_at', '>=', now()->subDays(30)),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    DeleteAction::make()
                        ->label('Supprimer')
                        ->icon('heroicon-o-trash'),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPenalties::route('/'),
        ];
    }
}
