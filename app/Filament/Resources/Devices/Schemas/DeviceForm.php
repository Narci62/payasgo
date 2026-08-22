<?php

namespace App\Filament\Resources\Devices\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DeviceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('client_id')
                    ->relationship('client', 'id')
                    ->required(),
                TextInput::make('public_id')
                    ->required(),
                TextInput::make('android_version'),
                TextInput::make('device_name')
                    ->required(),
                TextInput::make('device_id')
                    ->required(),
                TextInput::make('device_model'),
                TextInput::make('device_brand'),
                TextInput::make('imei'),
                Select::make('status')
                    ->options([
                        'pending_registration' => 'Pending registration',
                        'active' => 'Active',
                        'payment_due' => 'Payment due',
                        'locked' => 'Locked',
                        'disabled' => 'Disabled',
                    ])
                    ->default('pending_registration')
                    ->required(),
                DateTimePicker::make('last_seen_at'),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
