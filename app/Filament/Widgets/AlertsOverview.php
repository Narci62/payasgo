<?php

namespace App\Filament\Widgets;

use App\Services\DashboardService;
use Filament\Widgets\Widget;

class AlertsOverview extends Widget
{
    protected string $view = 'filament.widgets.alerts-overview';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 3;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'alerts' => app(DashboardService::class)->alerts(),
        ];
    }
}
