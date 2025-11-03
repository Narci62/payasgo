<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Page;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Filament\Widgets\StatsOverviewWidget;

use App\Filament\Widgets\ClientStatsOverview; 
use App\Filament\Widgets\LatestClients;

class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.pages.dashboard';

  #  protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $title = "Tableau de Bord";

    // Pour masquer le dashboard par défaut et utiliser le nôtre
    protected static bool $shouldRegisterNavigation = true;

    public function getColumns(): int | array
    {
        return 2; // Par exemple, pour un layout en 2 colonnes
    }

    public function getWidgets(): array
    {
        return [
            AccountWidget::class,
            FilamentInfoWidget::class,
            ClientStatsOverview::class,
            LatestClients::class,
        ];
    }
}
