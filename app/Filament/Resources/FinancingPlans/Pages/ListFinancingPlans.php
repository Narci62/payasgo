<?php

namespace App\Filament\Resources\FinancingPlans\Pages;

use App\Filament\Resources\FinancingPlans\FinancingPlanResource;
use App\Filament\Widgets\ActiveContractsWidget;
use App\Filament\Widgets\AllContractsWidget;
use App\Filament\Widgets\FinishedContractsWidget;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFinancingPlans extends ListRecords
{
    protected static string $resource = FinancingPlanResource::class;

    protected static ?string $title = 'Liste des plans de financement';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getFooterWidgets(): array
    {
        return [ActiveContractsWidget::class, AllContractsWidget::class,  FinishedContractsWidget::class];
    }
}
