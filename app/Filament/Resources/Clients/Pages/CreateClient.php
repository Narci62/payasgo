<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ClientResource;
use App\Models\Client;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Http;

class CreateClient extends CreateRecord
{
    protected static string $resource = ClientResource::class;

    // protected function handleRecordCreation(array $data): Client
    // {
    //     try {
    //         $response = Http::post(config('app.api_url') . '/client/register', $data);

    //         dd($response);

    //         if ($response->failed()) {
    //             Notification::make()
    //                 ->title('Erreur')
    //                 ->body('Impossible de créer le client : ' . $response->body())
    //                 ->danger()
    //                 ->send();
    //             throw new \RuntimeException('Échec de la création via API');
    //         }

    //         $clientData = $response->json('data');

    //         Notification::make()
    //             ->title('Succès')
    //             ->body('Client créé avec succès : ' . ($clientData['reference'] ?? ''))
    //             ->success()
    //             ->send();

    //         return new \App\Models\Client($clientData);
    //         // Si tu veux aussi l'enregistrer localement (facultatif)
    //        // return new \App\Models\Client($clientData);
    //     } catch (\Exception $e) {
    //         Notification::make()
    //             ->title('Erreur réseau')
    //             ->body($e->getMessage())
    //             ->danger()
    //             ->send();
    //         throw $e;
    //     }
    // }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
