<?php

namespace App\Filament\Resources\Devices\Tables;

use App\Helpers\Helper;
use App\Models\Device;
use App\Services\AMAPIClientService;
use App\Services\DeviceMonitoringService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class DevicesTable
{
    public static function configure(Table $table): Table
    {
        /**
         * afficher la liste des devices avec le client, le modele et la marque du téléphone, le status puis en options d'actions : son token
         * d'enrollement amapi,  les historiques de paiement et les action verrouiller et deverrouiller le téléphone
         */

        // par ordre decroissant de la date de derniere connexion
        $table->defaultSort('last_seen_at', 'desc');

        return $table
            ->columns([
                TextColumn::make('client.full_name')
                    ->label('Client')
                    ->copyable()
                    ->searchable(),
                TextColumn::make('phone.brand')
                    ->label('Marque')
                    ->searchable(),
                TextColumn::make('phone.model')
                    ->label('Modèle')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->label('Statut'),
                TextColumn::make('last_seen_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Dernière connexion'),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->actions([
                Action::make('amapi_status')
                    ->label('État AMAPI')
                    ->icon('heroicon-o-shield-check')
                    ->color('info')
                    ->modalHeading('QR Code d\'enrôlement AMAPI')
                    ->modalContent(fn(Device $record) => view('filament.devices.qr-code', [
                        'qrcode' => Helper::generateJsonQrCode($record),
                    ]))
                    ->modalWidth('lg'),

                Action::make('lock_history')
                    ->label('Historique de verrouillage')
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->modalHeading('Historique de verrouillage')
                    ->modalContent(fn(Device $record) => view('filament.devices.lock-history', [
                        'lockHistory' => $record->lockHistory()->latest()->limit(20)->get()
                    ]))
                    ->modalWidth('3xl'),
                ActionGroup::make([

                    // Verrouiller manuellement
                    Action::make('lock_device')
                        ->label('Verrouiller')
                        ->icon('heroicon-o-lock-closed')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Verrouiller cet appareil ?')
                        ->modalDescription('L\'appareil sera immédiatement verrouillé via AMAPI.')
                        ->action(function (Device $record) {
                            $amapiClient = app(AMAPIClientService::class);

                            try {
                                $success = $amapiClient->lockDevice(
                                    $record,
                                    'MANUAL_ADMIN',
                                    auth()->id()
                                );

                                if ($success) {
                                    Notification::make()
                                        ->title('Appareil verrouillé')
                                        ->success()
                                        ->send();
                                } else {
                                    Notification::make()
                                        ->title('Échec du verrouillage')
                                        ->danger()
                                        ->send();
                                }
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Erreur')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->visible(fn(Device $record) => !$record->isLocked()),

                    // Déverrouiller manuellement
                    Action::make('unlock_device')
                        ->label('Déverrouiller')
                        ->icon('heroicon-o-lock-open')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Déverrouiller cet appareil ?')
                        ->modalDescription('L\'appareil sera immédiatement déverrouillé via AMAPI.')
                        ->action(function (Device $record) {
                            $amapiClient = app(AMAPIClientService::class);

                            try {
                                $success = $amapiClient->unlockDevice(
                                    $record,
                                    'ADMIN_OVERRIDE',
                                    auth()->id()
                                );

                                if ($success) {
                                    Notification::make()
                                        ->title('Appareil déverrouillé')
                                        ->success()
                                        ->send();
                                } else {
                                    Notification::make()
                                        ->title('Échec du déverrouillage')
                                        ->danger()
                                        ->send();
                                }
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Erreur')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->visible(fn(Device $record) => $record->isLocked()),

                    // Vérifier maintenant (forcer le check)
                    Action::make('check_now')
                        ->label('Vérifier maintenant')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->action(function (Device $record) {
                            $monitoringService = app(DeviceMonitoringService::class);
                            $result = $monitoringService->checkSingleDevice($record);

                            if ($result['action'] === 'NONE') {
                                Notification::make()
                                    ->title('Aucune action nécessaire')
                                    ->body('L\'appareil est conforme')
                                    ->success()
                                    ->send();
                            } elseif ($result['success'] ?? false) {
                                Notification::make()
                                    ->title('Action effectuée : ' . $result['action'])
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Vérification échouée')
                                    ->body($result['error'] ?? 'Erreur inconnue')
                                    ->danger()
                                    ->send();
                            }
                        }),

                    // Générer un nouveau QR Code
                    // Action::make('regenerate_qr')
                    //     ->label('Régénérer QR Code')
                    //     ->icon('heroicon-o-qr-code')
                    //     ->color('info')
                    //     ->action(function (Device $record) {
                    //         $amapiClient = app(AMAPIClientService::class);

                    //         try {
                    //             $result = $amapiClient->generateProvisioningQRCode($record);

                    //             Notification::make()
                    //                 ->title('QR Code généré')
                    //                 ->body('Expire le : ' . $result['expires_at']->format('d/m/Y H:i'))
                    //                 ->success()
                    //                 ->send();
                    //         } catch (\Exception $e) {
                    //             Notification::make()
                    //                 ->title('Erreur')
                    //                 ->body($e->getMessage())
                    //                 ->danger()
                    //                 ->send();
                    //         }
                    //     })
                    //     ->visible(
                    //         fn(Device $record) =>
                    //         !$record->amapiDevice ||
                    //             $record->amapiDevice->amapi_state === 'PROVISIONING'
                    //     ),

                    // Voir l'historique de verrouillage
                    Action::make('lock_history')
                        ->label('Historique de verrouillage')
                        ->icon('heroicon-o-clock')
                        ->color('gray')
                        ->modalHeading('Historique de verrouillage')
                        ->modalContent(fn(Device $record) => view('filament.devices.lock-history', [
                            'lockHistory' => $record->lockHistory()->latest()->limit(20)->get()
                        ]))
                        ->modalWidth('3xl'),


                        // delete action
                        Action::make('delete_device')
                        ->label('Supprimer')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Supprimer cet appareil ?')
                        ->modalDescription('Cette action est irréversible. L\'appareil sera supprimé de la base de données.')
                        ->action(function (Device $record) {
                            try {
                                $record->delete();
                                Notification::make()
                                    ->title('Appareil supprimé')
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Erreur')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                ])
            ]);
            // ->toolbarActions([
            //     // Ajouter une action pour créer un device manuellement
            //     Action::make('create_device_manual')
            //         ->label('Créer un appareil manuellement')
            //         ->icon('heroicon-o-plus')
            //         ->color('success')
            //         ->action(function () {
            //             // Rediriger vers la page de création d'un appareil avec des paramètres pré-remplis pour indiquer que c'est une création manuelle
            //             // Par exemple, vous pouvez ajouter un paramètre ?manual=true à l'URL et gérer cela dans la page de création pour pré-remplir certains champs ou afficher des instructions spécifiques
            //             redirect()->route('filament.resources.devices.create', ['manual' => true]);
            //         }),
            // ]);

        // return $table
        //     ->columns([
        //         TextColumn::make('client.id')
        //         -
        //             ->searchable(),
        //        /* TextColumn::make('public_id')
        //             ->searchable(),*/
        //         TextColumn::make('android_version')
        //             ->searchable(),
        //         TextColumn::make('device_name')
        //             ->searchable(),
        //        /* TextColumn::make('device_id')
        //             ->searchable(),*/
        //         TextColumn::make('device_model')
        //             ->searchable(),
        //         TextColumn::make('device_brand')
        //             ->searchable(),
        //         TextColumn::make('imei')
        //             ->searchable(),
        //         TextColumn::make('status')
        //             ->badge(),
        //         TextColumn::make('last_seen_at')
        //             ->dateTime()
        //             ->sortable(),
        //         TextColumn::make('created_at')
        //             ->dateTime()
        //             ->sortable()
        //             ->toggleable(isToggledHiddenByDefault: true),
        //         TextColumn::make('updated_at')
        //             ->dateTime()
        //             ->sortable()
        //             ->toggleable(isToggledHiddenByDefault: true),
        //         TextColumn::make('deleted_at')
        //             ->dateTime()
        //             ->sortable()
        //             ->toggleable(isToggledHiddenByDefault: true),
        //     ])
        //     ->filters([
        //         TrashedFilter::make(),
        //     ])
        //     ->recordActions([
        //         EditAction::make(),
        //     ])
        //     ->toolbarActions([
        //         BulkActionGroup::make([
        //             DeleteBulkAction::make(),
        //             ForceDeleteBulkAction::make(),
        //             RestoreBulkAction::make(),
        //         ]),
        //     ])
        //     ->actions([
        //         ActionGroup::make([
        //             // Afficher l'état AMAPI
        //             Action::make('amapi_status')
        //                 ->label('État AMAPI')
        //                 ->icon('heroicon-o-shield-check')
        //                 ->color('info')
        //                 ->modalHeading('État de l\'appareil sur AMAPI')
        //                 ->modalContent(fn(Device $record) => view('filament.devices.amapi-status', [
        //                     'device' => $record,
        //                     'amapiDevice' => $record->amapiDevice
        //                 ]))
        //                 ->modalWidth('lg'),

        //             // Verrouiller manuellement
        //             Action::make('lock_device')
        //                 ->label('Verrouiller')
        //                 ->icon('heroicon-o-lock-closed')
        //                 ->color('danger')
        //                 ->requiresConfirmation()
        //                 ->modalHeading('Verrouiller cet appareil ?')
        //                 ->modalDescription('L\'appareil sera immédiatement verrouillé via AMAPI.')
        //                 ->action(function (Device $record) {
        //                     $amapiClient = app(AMAPIClientService::class);

        //                     try {
        //                         $success = $amapiClient->lockDevice(
        //                             $record,
        //                             'MANUAL_ADMIN',
        //                             auth()->id()
        //                         );

        //                         if ($success) {
        //                             Notification::make()
        //                                 ->title('Appareil verrouillé')
        //                                 ->success()
        //                                 ->send();
        //                         } else {
        //                             Notification::make()
        //                                 ->title('Échec du verrouillage')
        //                                 ->danger()
        //                                 ->send();
        //                         }
        //                     } catch (\Exception $e) {
        //                         Notification::make()
        //                             ->title('Erreur')
        //                             ->body($e->getMessage())
        //                             ->danger()
        //                             ->send();
        //                     }
        //                 })
        //                 ->visible(fn(Device $record) => !$record->isLocked()),

        //             // Déverrouiller manuellement
        //             Action::make('unlock_device')
        //                 ->label('Déverrouiller')
        //                 ->icon('heroicon-o-lock-open')
        //                 ->color('success')
        //                 ->requiresConfirmation()
        //                 ->modalHeading('Déverrouiller cet appareil ?')
        //                 ->modalDescription('L\'appareil sera immédiatement déverrouillé via AMAPI.')
        //                 ->action(function (Device $record) {
        //                     $amapiClient = app(AMAPIClientService::class);

        //                     try {
        //                         $success = $amapiClient->unlockDevice(
        //                             $record,
        //                             'ADMIN_OVERRIDE',
        //                             auth()->id()
        //                         );

        //                         if ($success) {
        //                             Notification::make()
        //                                 ->title('Appareil déverrouillé')
        //                                 ->success()
        //                                 ->send();
        //                         } else {
        //                             Notification::make()
        //                                 ->title('Échec du déverrouillage')
        //                                 ->danger()
        //                                 ->send();
        //                         }
        //                     } catch (\Exception $e) {
        //                         Notification::make()
        //                             ->title('Erreur')
        //                             ->body($e->getMessage())
        //                             ->danger()
        //                             ->send();
        //                     }
        //                 })
        //                 ->visible(fn(Device $record) => $record->isLocked()),

        //             // Vérifier maintenant (forcer le check)
        //             Action::make('check_now')
        //                 ->label('Vérifier maintenant')
        //                 ->icon('heroicon-o-arrow-path')
        //                 ->color('warning')
        //                 ->action(function (Device $record) {
        //                     $monitoringService = app(DeviceMonitoringService::class);
        //                     $result = $monitoringService->checkSingleDevice($record);

        //                     if ($result['action'] === 'NONE') {
        //                         Notification::make()
        //                             ->title('Aucune action nécessaire')
        //                             ->body('L\'appareil est conforme')
        //                             ->success()
        //                             ->send();
        //                     } elseif ($result['success'] ?? false) {
        //                         Notification::make()
        //                             ->title('Action effectuée : ' . $result['action'])
        //                             ->success()
        //                             ->send();
        //                     } else {
        //                         Notification::make()
        //                             ->title('Vérification échouée')
        //                             ->body($result['error'] ?? 'Erreur inconnue')
        //                             ->danger()
        //                             ->send();
        //                     }
        //                 }),

        //             // Générer un nouveau QR Code
        //             Action::make('regenerate_qr')
        //                 ->label('Régénérer QR Code')
        //                 ->icon('heroicon-o-qr-code')
        //                 ->color('info')
        //                 ->action(function (Device $record) {
        //                     $amapiClient = app(AMAPIClientService::class);

        //                     try {
        //                         $result = $amapiClient->generateProvisioningQRCode($record);

        //                         Notification::make()
        //                             ->title('QR Code généré')
        //                             ->body('Expire le : ' . $result['expires_at']->format('d/m/Y H:i'))
        //                             ->success()
        //                             ->send();
        //                     } catch (\Exception $e) {
        //                         Notification::make()
        //                             ->title('Erreur')
        //                             ->body($e->getMessage())
        //                             ->danger()
        //                             ->send();
        //                     }
        //                 })
        //                 ->visible(
        //                     fn(Device $record) =>
        //                     !$record->amapiDevice ||
        //                         $record->amapiDevice->amapi_state === 'PROVISIONING'
        //                 ),

        //             // Voir l'historique de verrouillage
        //             Action::make('lock_history')
        //                 ->label('Historique de verrouillage')
        //                 ->icon('heroicon-o-clock')
        //                 ->color('gray')
        //                 ->modalHeading('Historique de verrouillage')
        //                 ->modalContent(fn(Device $record) => view('filament.devices.lock-history', [
        //                     'history' => $record->lockHistory()->latest()->limit(20)->get()
        //                 ]))
        //                 ->modalWidth('3xl'),
        //         ])
        //     ]);
    }
}
