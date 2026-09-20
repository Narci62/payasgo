<?php

namespace App\Filament\Resources\Penalties;

use App\Filament\Resources\Penalties\Pages\ListPenalties;
use App\Models\Penalty;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
