<?php

namespace App\Filament\Resources\Phones\Pages;

use App\Filament\Resources\Phones\PhoneResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPhone extends EditRecord
{
    protected static string $resource = PhoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    // redirect to the list of phones after saving the record
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
