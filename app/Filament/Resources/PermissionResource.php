<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PermissionResource\Pages;
use App\Helpers\PermissionHelper;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Permission;

class PermissionResource extends Resource
{
    protected static ?string $model = Permission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Permissions';

    protected static ?string $breadcrumbTitle = 'Permissions';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 11;

    public static function canViewAny(): bool
    {
        return auth()->user()->hasPermissionTo('manage-roles');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->hasPermissionTo('manage-roles');
    }

    public static function canEdit($record): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'super-admin']);
    }

    public static function canDelete($record): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'super-admin']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('name')
                    ->label('Nom de la permission')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->helperText('Format recommandé : action-ressource (ex: view-clients)'),

                TextInput::make('guard_name')
                    ->label('Guard')
                    ->default('web')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Slug')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('label')
                    ->label('Description')
                    ->state(fn ($record) => PermissionHelper::getLabel($record->name)),

                TextColumn::make('guard_name')
                    ->label('Guard')
                    ->badge(),

                TextColumn::make('roles_count')
                    ->label('Rôles')
                    ->counts('roles')
                    ->badge(),

                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPermissions::route('/'),
            'create' => Pages\CreatePermission::route('/create'),
            'edit' => Pages\EditPermission::route('/{record}/edit'),
        ];
    }
}
