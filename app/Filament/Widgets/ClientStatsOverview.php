<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class ClientStatsOverview extends Widget
{
    protected string $view = 'filament.widgets.client-stats-overview';

    protected function getStats(): array
    {
        $totalClients = Client::count();

        return [
            Stat::make('Total Clients', $totalClients)
                ->description('Nombre total de client dans la base')
                ->descriptionIcon('heroicon-m-archive-box')
                ->color('success')
        ];
    }
}
