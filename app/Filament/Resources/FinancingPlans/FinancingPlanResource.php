<?php

namespace App\Filament\Resources\FinancingPlans;

use BackedEnum;
use App\Models\Client;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use App\Models\FinancingPlan;
use App\Models\Financing_plan;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Tables\Filters\Filter;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Http;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use App\Services\FinancingPlanService;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\Clients\ClientResource;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\FinancingPlans\Pages\EditFinancingPlan;
use App\Filament\Resources\FinancingPlans\Pages\ListFinancingPlans;
use App\Filament\Resources\FinancingPlans\Pages\CreateFinancingPlan;
use App\Filament\Resources\FinancingPlans\Schemas\FinancingPlanForm;
use App\Filament\Resources\FinancingPlans\Tables\FinancingPlansTable;

class FinancingPlanResource extends Resource
{
    protected static ?string $model = Financing_plan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'status';

    // custom name for navigation
    protected static ?string $navigationLabel = 'Plans de financement';


    // change breadcrumb title
    protected static ?string $breadcrumbTitle = 'Plans de financement';


    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Nouveau Contrat')
                    ->description('Informations générales sur le contrat.')
                    ->schema([
                        Select::make('client_id')
                            ->label('Client')
                            ->options(
                                fn() => \App\Models\Client::all()->pluck('full_name', 'id')
                            )
                            ->searchable()
                            ->required(),

                        // show phone brand and model in select options and use phone_id as value
                        // select phone id in get url

                        Select::make('phone_id')
                            ->label('Téléphone')
                            ->options(
                                fn() => \App\Models\Phone::all()->mapWithKeys(function ($phone) {
                                    return [$phone->id => "{$phone->brand} - {$phone->model}"];
                                })
                            )
                            ->default(fn() => request()->get('phone_id'))
                            ->searchable()
                            ->required(),

                        TextInput::make('total_price')
                            ->label('Prix Cash')
                            ->numeric()
                            ->prefix('CFA')
                            ->required(),

                        TextInput::make('down_payment')
                            ->label('Acompte')
                            ->numeric()
                            ->prefix('CFA')
                            ->required(),

                        TextInput::make('installment_amount')
                            ->label('Mensualité')
                            ->numeric()
                            ->prefix('CFA')
                            ->required(),

                        TextInput::make('days_interval')
                            ->label('Intervalle de jours entre les paiements')
                            ->integer()
                            ->required(),

                        // afiche en label le prix total calculé à partir du prix cash,

                    ])->columns(2), // 2 colonnes pour cette section

            ]);
    }


    public static function table(Table $table): Table
    {
        return $table
            // order by created_at desc by default
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('registrationToken.client.full_name')
                    ->label('Client')
                    ->sortable()
                    ->searchable()
                    //link to client resource
                    ->url(fn(Financing_plan $record): string =>
                    ClientResource::getUrl('edit', ['record' => $record->registrationToken->client->id]))
                    ->default('N/A'),

                TextColumn::make('device.device_name')
                    ->label('Appareil')
                    ->copyable()
                    ->sortable()
                    ->searchable()
                    ->default('N/A'),

                TextColumn::make('status')
                    ->label('Statut')
                    ->searchable(),

                TextColumn::make('total_price')
                    ->label('Prix total')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('down_payment')
                    ->label('Acompte')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('remaining_balance')
                    ->label('Solde restant')
                    ->searchable(),

                TextColumn::make("installment_amount")
                    ->label('Montant des versements')
                    ->searchable(),

                TextColumn::make('next_payment_due_date')
                    ->label('Prochain paiement dû le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('next_offline_unlock_code')
                    ->label('Code de deverrouillage')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Inscrit le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])

            ->filters([
                // Filtre par statut (actif/inactif)
                // SelectFilter::make('status')
                //     ->label('Statut')
                //     ->options([
                //         1 => 'Actif',
                //         0 => 'Inactif',
                //     ])
                //     ->query(fn (Builder $query, array $data): Builder =>
                //         isset($data['value'])
                //             ? $query->where('status', $data['value'])
                //             : $query
                //     ),

                // Filtre par date d’inscription
                Filter::make('created_at')
                    ->label('Inscrits récents (7 derniers jours)')
                    ->query(
                        fn(Builder $query): Builder =>
                        $query->where('created_at', '>=', now()->subDays(7))
                    )
                    ->toggle(),
            ])

            ->actions([
                ActionGroup::make([
                    EditAction::make()
                        ->label('Modifier')
                        ->icon('heroicon-o-pencil'),

                    // add historique payment action
                    Action::make('view_payments')
                        ->label('Voir les paiements')
                        ->icon('heroicon-o-currency-dollar')
                        ->action(function (Financing_plan $record): void {
                            // Logic to view payment history
                            $payments = $record->payments;
                            //dd($payments);
                        })
                        ->modalWidth('lg')
                        ->modalHeading('Historique des paiements')
                        ->modalContent(fn(Financing_plan $record) => view('filament.resources.financing-plans.view-payments', [
                            'payments' => $record->payments,
                        ])),


                    // add payment action
                    Action::make('add_payment')
                        ->label('Ajouter un paiement')
                        ->icon('heroicon-o-currency-dollar')
                        ->action(function (Financing_plan $record, array $data): void {
                            $financingPlanService = new FinancingPlanService();
                            $payments = $financingPlanService->savePayment($record, $data['amount'], 'manual', uniqid("txn-"));
                            Notification::make()
                                ->title('Paiement ajouté avec succès.')
                                ->success()
                                ->send();
                        })
                        ->form([
                            TextInput::make('amount')
                                ->label('Montant du paiement')
                                ->numeric()
                                ->prefix('CFA')
                                // min is installment amount
                                ->minValue(fn(Financing_plan $record) => $record->installment_amount)
                                ->default(fn(Financing_plan $record) => $record->installment_amount)
                                ->required(),
                        ]),

                    // DeleteAction::make()
                    //     ->label('Supprimer')
                    //     ->icon('heroicon-o-trash'),

                ]),
            ])

            ->bulkActions([
                BulkActionGroup::make([
                    // DeleteBulkAction::make()
                    //     ->label('Supprimer sélection'),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFinancingPlans::route('/'),
            'create' => CreateFinancingPlan::route('/create'),
            'edit' => EditFinancingPlan::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
