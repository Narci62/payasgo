<?php

namespace App\Filament\Pages;

use view;
use BackedEnum;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Pages\Page;
use Filament\Tables\Table;
use Filament\Actions\Action;
use App\Models\Financing_plan;
use App\Models\Payment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
//use Tables\Concerns\InteractsWithTable;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Concerns\InteractsWithTable;

class SalesReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.sales-report';
    //modifie icon
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $title = 'Fiche de vente';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn(): Builder => Payment::query())
            ->columns([
                TextColumn::make('id')->label('ID'),
                TextColumn::make('financingPlan.registrationToken.client.full_name')->label('Client'),
                TextColumn::make('financingPlan.device.device_name')->label('Appareil'),
                TextColumn::make('amount')->label('Payé')->money('XOF'),
                TextColumn::make('financingPlan.remaining_balance')->label('Restant')->money('XOF'),
            ])
            ->filters([
                SelectFilter::make('period')
                    ->label('Période')
                    ->options([
                        'today' => 'Aujourd\'hui',
                        'week' => 'Cette semaine',
                        'month' => 'Ce mois-ci',
                    ])
                    ->default('today')
                    ->query(function ($query, $data) {
                        if (!$data['value']) {
                            return;
                        }

                        return match ($data['value']) {
                            'today' => $query->whereDate('created_at', today()),
                            'week'  => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
                            'month' => $query->whereMonth('created_at', now()->month)
                                ->whereYear('created_at', now()->year),
                        };
                    }),
            ])
            ->headerActions([
                Action::make('downloadPdf')
                    ->label('Télécharger PDF')
                    ->color('primary')
                    ->action(fn() => $this->downloadPdf())
                //->requiresConfirmation(),
            ]);
    }

    protected function getQueryByFilter()
    {
        $state = $this->getTable()->getFilters();
        $period = $state['period']->getState()['value'] ?? 'today';

        return match ($period) {
            'week' => Payment::whereBetween('created_at', [
                Carbon::now()->startOfWeek(),
                Carbon::now()->endOfWeek(),
            ]),

            'month' => Payment::whereMonth('created_at', Carbon::now()->month),

            default => Payment::whereDate('created_at', Carbon::today()),
        };
    }

    public function downloadPdf()
    {
        $state = $this->getTable()->getFilters();
        $period = $state['period']->getState()['value'] ?? 'today';
        $sales = $this->getQueryByFilter()->get();

        $pdf = Pdf::loadView('pdf.sales-report', [
            'sales' => $sales,
            'period' => $period,
        ]);

        $filename = 'P-Guard_' . now()->format('Ymd_His') . '.pdf';

        return response()->streamDownload(fn() => print($pdf->output()), $filename);
    }
}
