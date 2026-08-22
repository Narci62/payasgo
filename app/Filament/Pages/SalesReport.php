<?php

namespace App\Filament\Pages;

use App\Models\Payment;
use BackedEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SalesReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.sales-report';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $title = 'Fiche de vente';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Payment::query())
            ->columns([
                TextColumn::make('id')->label('ID'),

                TextColumn::make('financingPlan.registrationToken.client.full_name')
                    ->label('Client'),

                TextColumn::make('financingPlan.device.device_name')
                    ->label('Appareil'),

                TextColumn::make('amount')
                    ->label('Payé')
                    ->money('XOF'),

                TextColumn::make('financingPlan.remaining_balance')
                    ->label('Restant')
                    ->money('XOF'),
            ])
            ->filters([

                // ---------------------------
                // 🎯 Nouveau filtre entre deux dates
                // ---------------------------
                Filter::make('date_range')
                    ->label('Plage de dates')
                    ->form([
                        DatePicker::make('from')
                            ->label('Du')
                            ->native(false),

                        DatePicker::make('to')
                            ->label('Au')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data) {

                        // Si aucune date choisie → ne rien filtrer
                        if (empty($data['from']) && empty($data['to'])) {
                            return $query;
                        }

                        // Filtre date de début
                        if (! empty($data['from'])) {
                            $query->whereDate('created_at', '>=', Carbon::parse($data['from']));
                        }

                        // Filtre date de fin
                        if (! empty($data['to'])) {
                            $query->whereDate('created_at', '<=', Carbon::parse($data['to']));
                        }

                        return $query;
                    }),
            ])
            ->headerActions([
                Action::make('downloadPdf')
                    ->label('Télécharger PDF')
                    ->color('primary')
                    ->action(fn () => $this->downloadPdf()),
            ]);
    }

    // ----------------------------------------------------------
    //  Récupérer les paiements filtrés selon la plage de date
    // ----------------------------------------------------------
    protected function getQueryByFilter()
    {
        $filters = $this->getTable()->getFilters();
        $filter = $filters['date_range']->getState() ?? [];

        $query = Payment::query();

        if (! empty($filter['from'])) {
            $query->whereDate('created_at', '>=', Carbon::parse($filter['from']));
        }

        if (! empty($filter['to'])) {
            $query->whereDate('created_at', '<=', Carbon::parse($filter['to']));
        }

        return $query;
    }

    // ----------------------------------------------------------
    //  Export PDF avec la plage de date sélectionnée
    // ----------------------------------------------------------
    public function downloadPdf()
    {
        $filters = $this->getTable()->getFilters();
        $filter = $filters['date_range']->getState() ?? [];

        $sales = $this->getQueryByFilter()->get();

        $pdf = Pdf::loadView('pdf.sales-report', [
            'sales' => $sales,
            'from' => $filter['from'] ?? null,
            'to' => $filter['to'] ?? null,
        ]);

        $filename = 'P-Guard_'.now()->format('Ymd_His').'.pdf';

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            $filename
        );
    }
}
