<?php

namespace App\Filament\Widgets;

use App\Services\DashboardService;
use Filament\Widgets\Widget;
use Livewire\WithPagination;

class ContractsDevicesOverview extends Widget
{
    use WithPagination;

    protected string $view = 'filament.widgets.contracts-devices-overview';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    protected int $perPage = 15;

    protected string $pageName = 'pg-contracts';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $service = app(DashboardService::class);

        $contracts = $service->contractsWithDevices($this->perPage, $this->pageName);

        return [
            'rows' => $service->decoratePlans($contracts->getCollection()),
            'contracts' => $contracts,
        ];
    }
}
