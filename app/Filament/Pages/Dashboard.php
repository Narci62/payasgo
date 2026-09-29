<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AlertsOverview;
use App\Filament\Widgets\ContractsDevicesOverview;
use App\Filament\Widgets\PortfolioKpis;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\AccountWidget;

class Dashboard extends BaseDashboard
{
    // protected string $view = 'filament.pages.dashboard';

    // protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $title = 'Tableau de Bord';

    // Pour masquer le dashboard par défaut et utiliser le nôtre
    protected static bool $shouldRegisterNavigation = false;

    public function getColumns(): int|array
    {
        return 1;
    }

    public function getWidgets(): array
    {
        return [
            AccountWidget::class,
            PortfolioKpis::class,
            ContractsDevicesOverview::class,
            AlertsOverview::class,
        ];
    }
}
