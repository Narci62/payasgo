<?php

namespace App\Filament\Resources\Clients;

use App\Filament\Resources\Clients\Pages\CreateClient;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Models\Client;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'full_name';

    public static function canViewAny(): bool
    {
        return auth()->user()->hasPermissionTo('view-clients');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->hasPermissionTo('create-clients');
    }

    public static function canEdit($record): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'super-admin']);
    }

    public static function canDelete($record): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'super-admin']);
    }

    // protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    // protected static ?string $navigationGroup = 'Gestion du magasin';
    // protected static ?int $navigationSort = 1; // Ordre dans la navigation

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
                            ->placeholder('123 Main St, Anytown, Porto')
                            ->required()
                            ->maxLength(255),
                    ])->columns(2), // 2 colonnes pour cette section

                Section::make('Plus de renseignement')
                    ->description('Renseignement administratifs')
                    ->schema([
                        TextInput::make('npi')
                            ->label('Numéro NPI')
                            ->required(),

                        TextInput::make('ifu')
                            ->label('Numéro IFU')
                            ->nullable(),

                        Select::make('identity_document_type')
                            ->label('Type de pièce')
                            ->options([
                                'national_id' => 'Carte Nationale d’Identité',
                                'passport' => 'Passeport',
                                'driver_licence' => 'Permis de conduire',
                                'cip' => 'CIP',
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

                Section::make('Garant (Témoin)')
                    ->description('Informations du garant du client.')
                    ->schema([
                        Group::make([
                            TextInput::make('nom')
                                ->label('Nom du garant')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('prenom')
                                ->label('Prénom du garant')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('adresse')
                                ->label('Adresse du garant')
                                ->maxLength(255),
                            TextInput::make('telephone')
                                ->label('Téléphone du garant'),
                            TextInput::make('numero_identite')
                                ->label('Numéro NPI / CIP'),
                            FileUpload::make('photo_piece')
                                ->label('Photo de la pièce d\'identité')
                                ->directory('documents/garants')
                                ->preserveFilenames()
                                ->downloadable()
                                ->previewable()
                                ->maxSize(2048)
                                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png']),
                        ])->relationship('garant')
                            ->columns(3),
                    ]),

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
                // Rappel : chaque callback ->query() se garde sur « isActive ».
                // Filament l'évalue même sans état, et un whereIn sur un tableau
                // vide génère « 0 = 1 », ce qui masquerait toute la liste.

                // Filtre par date d’inscription
                Filter::make('created_recently')
                    ->label('Inscrits récents (7 derniers jours)')
                    ->query(
                        fn (Builder $query): Builder => $query->where('created_at', '>=', now()->subDays(7))
                    )
                    ->toggle(),

                SelectFilter::make('contract_status')
                    ->label('Statut du contrat')
                    ->options([
                        'active' => 'Actif',
                        'paid_in_full' => 'Soldé',
                        'defaulted' => 'En attente',
                    ])
                    ->multiple()
                    ->query(fn (Builder $query, array $data): Builder => empty($values = (array) ($data['values'] ?? $data['value'] ?? []))
                        ? $query
                        : self::restrictToClientsWithPlan($query, fn ($plan) => $plan->whereIn('status', $values))),

                SelectFilter::make('has_contract')
                    ->label('Contrat')
                    ->options([
                        'with' => 'Avec au moins un contrat',
                        'without' => 'Sans contrat',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'with' => self::restrictToClientsWithPlan($query),
                        'without' => $query->whereNotIn('id', self::clientIdsWithPlan()),
                        default => $query,
                    }),
                SelectFilter::make('device_status')
                    ->label('Statut de l\'appareil')
                    ->options([
                        'locked' => 'Verrouillé',
                        'payment_due' => 'Paiement dû',
                        'active' => 'Actif',
                    ])
                    ->multiple()
                    ->query(fn (Builder $query, array $data): Builder => empty($values = (array) ($data['values'] ?? $data['value'] ?? []))
                        ? $query
                        : $query->whereHas('devices', fn (Builder $device) => $device->whereIn('status', $values))),

                SelectFilter::make('overdue')
                    ->label('Échéance dépassée')
                    ->options([
                        'overdue' => 'En retard de paiement',
                        'not_overdue' => 'À jour',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $values = (array) ($data['values'] ?? $data['value'] ?? []);

                        if (empty($values)) {
                            return $query;
                        }

                        $overdueQuery = fn ($plan) => $plan
                            ->where('status', 'active')
                            ->whereNotNull('next_payment_due_date')
                            ->where('next_payment_due_date', '<', now());

                        return match ($data['value']) {
                            'overdue' => self::restrictToClientsWithPlan($query, $overdueQuery),
                            'not_overdue' => $query->whereNotIn('id', self::clientIdsWithPlan($overdueQuery)),
                            default => $query,
                        };
                    }),

                SelectFilter::make('has_garant')
                    ->label('Garant')
                    ->options([
                        'yes' => 'Avec garant',
                        'no' => 'Sans garant',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'yes' => $query->whereHas('garant'),
                        'no' => $query->whereDoesntHave('garant'),
                        default => $query,
                    }),
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

    /**
     * Sous-requête des identifiants clients possédant au moins un contrat.
     *
     * Il n'existe pas de relation imbriquée registrationTokens.financingPlan :
     * le plan porte registration_token_id, la relation va donc du plan vers le
     * token. Plutôt que d'ajouter une relation au modèle pour un simple filtre,
     * on exprime la jointure en SQL.
     *
     * @param  (callable(\Illuminate\Database\Query\Builder): void)|null  $planConstraint
     */
    private static function clientIdsWithPlan(?callable $planConstraint = null): QueryBuilder
    {
        return DB::table('clients')
            ->select('clients.id')
            ->whereIn('clients.id', function ($tokens) use ($planConstraint): void {
                $tokens->select('registration_tokens.client_id')
                    ->from('registration_tokens')
                    ->whereIn('registration_tokens.id', function ($plans) use ($planConstraint): void {
                        $plans->select('financing_plans.id')->from('financing_plans');

                        if ($planConstraint !== null) {
                            $planConstraint($plans);
                        }
                    });
            });
    }

    /**
     * Restreint la requête aux clients possédant un contrat satisfaisant $planConstraint.
     *
     * @param  (callable(\Illuminate\Database\Query\Builder): void)|null  $planConstraint
     */
    private static function restrictToClientsWithPlan(Builder $query, ?callable $planConstraint = null): Builder
    {
        return $query->whereIn('id', self::clientIdsWithPlan($planConstraint));
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
