<?php

namespace App\Filament\Resources\Penalties\Pages;

use App\Filament\Resources\Penalties\PenaltyResource;
use App\Models\Penalty;
use Filament\Resources\Pages\ListRecords;

class ListPenalties extends ListRecords
{
    protected static string $resource = PenaltyResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTotalPenalties(): float
    {
        return (float) Penalty::sum('amount');
    }

    public function getCountPenalties(): int
    {
        return Penalty::count();
    }

    public function getAveragePenalty(): float
    {
        $count = $this->getCountPenalties();

        return $count > 0 ? $this->getTotalPenalties() / $count : 0;
    }

    public function getTodayPenalties(): float
    {
        return (float) Penalty::whereDate('created_at', today())->sum('amount');
    }
}
