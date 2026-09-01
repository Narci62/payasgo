<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Utilisateurs';

    protected static ?string $breadcrumbTitle = 'Utilisateurs';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        return auth()->user()->hasPermissionTo('view-users');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->hasPermissionTo('create-users');
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
                    ->label('Nom')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),

                TextInput::make('password')
                    ->label('Mot de passe')
                    ->password()
                    ->required()
                    ->maxLength(255)
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? bcrypt($state) : null)
                    ->dehydrated(fn ($state) => filled($state)),

                TextInput::make('imat')
                    ->label('IMAT')
                    ->maxLength(255),

                TextInput::make('phone')
                    ->label('Téléphone')
                    ->tel()
                    ->maxLength(255),

                Select::make('roles')
                    ->label('Rôle')
                    ->relationship('roles', 'name')
                    ->preload()
                    ->searchable()
                    ->required()
                    ->getSearchResultsUsing(fn (string $search) => Role::where('name', 'like', "%{$search}%")->pluck('name', 'id'))
                    ->getOptionLabelUsing(fn ($value): string => Role::find($value)?->name ?? $value)
                    ->multiple(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('imat')
                    ->label('IMAT')
                    ->searchable(),

                TextColumn::make('phone')
                    ->label('Téléphone')
                    ->searchable(),

                TextColumn::make('roles.name')
                    ->label('Rôle')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'super-admin' => 'danger',
                        'admin' => 'warning',
                        'commercial' => 'success',
                        'support' => 'info',
                        default => 'gray',
                    }),

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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
