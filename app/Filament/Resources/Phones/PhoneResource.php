<?php

namespace App\Filament\Resources\Phones;

use BackedEnum;
use App\Models\Phone;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Filament\Support\Icons\Heroicon;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Form;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use App\Filament\Resources\Phones\Pages\EditPhone;
use App\Filament\Resources\Phones\Pages\ListPhones;
use App\Filament\Resources\Phones\Pages\CreatePhone;
use App\Filament\Resources\Phones\Schemas\PhoneForm;
use App\Filament\Resources\Phones\Tables\PhonesTable;

class PhoneResource extends Resource
{
    protected static ?string $model = Phone::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?string $recordTitleAttribute = 'brand';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([

                Section::make('Information de l’appareil')
                    ->schema([
                        TextInput::make('brand')
                            ->label('Marque')
                            ->required(),

                        TextInput::make('model')
                            ->label('Modèle')
                            ->unique(ignoreRecord: true),

                        Textarea::make('description')
                            ->label('Description')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Stock & Statut')
                    ->schema([
                        TextInput::make('price')
                            ->numeric()
                            ->label('Prix')
                            ->required(),

                        TextInput::make('stock')
                            ->numeric()
                            ->default(1)
                            ->label('Stock'),

                        Select::make('status')
                            ->label('Statut')
                            ->options([
                                'available' => 'Disponible',
                                'reserved' => 'Réservé',
                                'sold' => 'Vendu',
                            ])
                            ->default('available'),
                    ])
                    ->columns(2),
        ]);
    }


    // public static function table(Table $table): Table
    // {
    //    // return PhonesTable::configure($table);
    // }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('brand')
                    ->label('Marque')
                    ->searchable(),

                TextColumn::make('model')
                    ->label('Modèle')
                    ->copyable(),

                TextColumn::make('price')
                    ->label('Prix')
                    ->money('XOF'),

                TextColumn::make('stock')
                    ->label('Stock'),

                BadgeColumn::make('status')
                    ->label('Statut')
                    ->colors([
                        'success' => 'available',
                        'warning' => 'reserved',
                        'danger'  => 'sold',
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'available' => 'Disponible',
                        'reserved' => 'Réservé',
                        'sold' => 'Vendu',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'available' => 'Disponible',
                        'reserved' => 'Réservé',
                        'sold' => 'Vendu',
                    ]),
            ])
            ->actions([
                EditAction::make(),
              //  DeleteAction::make(),
            ])
            ->bulkActions([
             //   DeleteBulkAction::make(),
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
            'index' => ListPhones::route('/'),
            'create' => CreatePhone::route('/create'),
            'edit' => EditPhone::route('/{record}/edit'),
        ];
    }
}
