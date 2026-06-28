<?php

namespace App\Filament\Widgets;

use App\Models\Client;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class LatestClients extends TableWidget
{
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
