<?php

namespace App\Filament\Resources\Clients;

use BackedEnum;
use App\Models\Client;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;

use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Tables\Filters\Filter;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Http;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;

use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Filters\SelectFilter;


use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Filament\Resources\Clients\Pages\CreateClient;
use App\Filament\Resources\Clients\Schemas\ClientForm;
use App\Filament\Resources\Clients\Tables\ClientsTable;




class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'full_name';

    # protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    #protected static ?string $navigationGroup = 'Gestion du magasin';
    # protected static ?int $navigationSort = 1; // Ordre dans la navigation

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Détails du Client')
                    ->description('Informations générales sur le client.')
                    ->schema([

                        TextInput::make('full_name')
                            ->label('Nom complet')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('phone_number')
                            ->label('Téléphone')
                            ->required(),
                    ])->columns(2), // 2 colonnes pour cette section

                Section::make('Adresse du Client')
                    ->description('Informations générales sur le client.')
                    ->schema([
                        TextInput::make('reference')
                            ->label('Référence')
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),
                        TextInput::make('address')
                            ->label('Adresse géographique')
                            ->placeholder("123 Main St, Anytown, Porto")
                            ->required()
                            ->maxLength(255)
                    ])->columns(2), // 2 colonnes pour cette section

                Section::make('Plus de renseignement')
                    ->description('Renseignement administratifs')
                    ->schema([
                        TextInput::make('npi')
                            ->label('Numéro NPI')
                            ->required(),

                        TextInput::make('ifu')
                            ->label("Numéro IFU")
                            ->nullable(),

                        Select::make('identity_document_type')
                            ->label('Type de pièce')
                            ->options([
                                'national_id' => 'Carte Nationale d’Identité',
                                'passport' => 'Passeport',
                                'driver_licence' => 'Permis de conduire'
                            ])
                            ->searchable()
                            ->required(),

                        TextInput::make('identity_document_number')
                            ->label('Référence du document')
                            ->required(),

                        FileUpload::make('identity_document_file_path')
                            ->label('Fichier du document')
                            ->directory('documents/identites')
                            ->preserveFilenames()
                            ->downloadable()
                            ->previewable()
                            ->maxSize(2048)
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                            ->required(),
                    ])->columns(3),

            ]);
    }




    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')
                    ->label('Référence')
                    ->copyable() // permet de copier la référence d’un clic
                    ->sortable()
                    ->searchable(),

                TextColumn::make('full_name')
                    ->label('Nom complet')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('address')
                    ->label('Adresse')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('phone_number')
                    ->label('Téléphone')
                    ->searchable(),

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
                Filter::make('created_recently')
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
            'index' => ListClients::route('/'),
            'create' => CreateClient::route('/create'),
            'edit' => EditClient::route('/{record}/edit'),
        ];
    }
}
