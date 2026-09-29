<?php

namespace App\Filament\Widgets;

use App\Services\DashboardService;
use Filament\Widgets\Widget;

class PortfolioKpis extends Widget
{
    protected string $view = 'filament.widgets.portfolio-kpis';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'kpis' => app(DashboardService::class)->kpis(),
        ];
    }
}
