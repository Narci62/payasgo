<?php

namespace App\Filament\Widgets;

use App\Models\Client;
use App\Models\Financing_plan;
use Carbon\Carbon;
use Filament\Tables\Table;
use Filament\Widgets\Widget;
use Filament\Widgets\TableWidget;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ClientStatsOverview extends StatsOverviewWidget
{
    //public string $view = 'filament.widgets.client-stats-overview';



    // retourn stats overview
    //protected static ?string $heading = 'Statistiques des Clients';
    protected function getStats(): array
    {
        // record count
        $totalClients = Client::count();
        $totalContracts = Financing_plan::count();
        $activeContracts = Financing_plan::where('status', 'active')->count();
        $inactiveContracts = Financing_plan::where('next_payment_due_date', '<', now())->count();;
        $paidInFullContracts = Financing_plan::where('status', 'paid_in_full')->count();

        return [
            Stat::make('Total Clients', $totalClients),
            Stat::make('Total Contrats', $totalContracts),
            Stat::make('Contrat Actifs', $activeContracts),
            Stat::make('Retard de paiement', $inactiveContracts),
            Stat::make('Contrat Soldé', $paidInFullContracts),
        ];
    }


}
