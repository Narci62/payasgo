<?php

namespace App\Filament\Widgets;

use Filament\Actions\BulkActionGroup;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LatestClients extends TableWidget
{
    public function table(Table $table): Table
    {
        return $table
            ->query(
                Client::query()->latest()->limit(5) // Afficher les 5 derniers
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom'),
                Tables\Columns\TextColumn::make('reference')
                    ->label('Réference'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date d\'ajout')
                    ->dateTime('d/m/Y H:i'),
            ]);
    }
}
