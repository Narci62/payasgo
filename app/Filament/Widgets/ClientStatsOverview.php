<?php

namespace App\Filament\Widgets;

use App\Models\Client;
use Filament\Tables\Table;
use Filament\Widgets\Widget;
use Filament\Widgets\TableWidget;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ClientStatsOverview extends TableWidget
{
    // string $view = 'filament.widgets.client-stats-overview';


    public function table(Table $table): Table
    {
        return $table
            ->query(
                Client::query()->latest()->limit(5) // Afficher les 5 derniers
            )
            ->columns([
                TextColumn::make('full_name')
                    ->label('Nom'),
                TextColumn::make('reference')
                    ->label('Réference'),
                TextColumn::make('created_at')
                    ->label('Date d\'ajout')
                    ->dateTime('d/m/Y H:i'),
            ]);
    }
}
