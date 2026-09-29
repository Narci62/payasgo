<?php

namespace App\Filament\Resources\Devices\Tables;

use App\Helpers\Helper;
use App\Models\Device;
use App\Models\Phone;
use App\Services\AMAPIClientService;
use App\Services\DeviceMonitoringService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                    ->label('Statut')
                    ->color(fn (string $state): string => match ($state) {
                        'locked' => 'danger',
                        'disabled' => 'danger',
                        'payment_due' => 'warning',
                        'active' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('last_seen_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Dernière connexion'),
            ])
            ->filters([
                // Note : chaque callback ->query() démarre par la même garde.
                // Filament évalue ->query() même quand l'état du filtre est vide,
                // donc un filtre jamais choisi restreindrait quand même la requête.
                // whereIn sur un tableau vide produisant « 0 = 1 », l'absence de
                // garde afficherait silencieusement zéro ligne.

                SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        'active' => 'Actif',
                        'payment_due' => 'Paiement dû',
                        'locked' => 'Verrouillé',
                        'disabled' => 'Désactivé',
                    ])
                    ->multiple(),

                Filter::make('is_locked')
                    ->label('Verrouillé (statut ou AMAPI)')
                    ->query(function (Builder $query, array $data): Builder {
                        if (! ($data['isActive'] ?? false)) {
                            return $query;
                        }

                        return $query->where(fn (Builder $q) => $q
                            ->where('status', 'locked')
                            ->orWhere('status', 'disabled')
                            ->orWhereHas('amapiDevice', fn (Builder $amapi) => $amapi->where('amapi_state', 'DISABLED'))
                        );
                    }),

                SelectFilter::make('amapi_state')
                    ->label('État AMAPI')
                    ->options([
                        'ACTIVE' => 'Enrôlé et actif',
                        'PROVISIONING' => 'Enrôlement en cours',
                        'DISABLED' => 'Verrouillé',
                        'DELETED' => 'Supprimé de l\'enterprise',
                        'LIBERATED' => 'Libéré (propriété cédée)',
                    ])
                    ->multiple()
                    ->query(function (Builder $query, array $data): Builder {
                        $values = (array) ($data['values'] ?? $data['value'] ?? []);

                        if (empty($values)) {
                            return $query;
                        }

                        return $query->whereHas(
                            'amapiDevice',
                            fn (Builder $amapi) => $amapi->whereIn('amapi_state', $values)
                        );
                    }),

                SelectFilter::make('amapi_enrollment')
                    ->label('Enrôlement')
                    ->options([
                        'enrolled' => 'Enrôlé',
                        'not_enrolled' => 'Non enrôlé',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $values = (array) ($data['values'] ?? $data['value'] ?? []);

                        if (empty($values)) {
                            return $query;
                        }

                        return match ($data['value']) {
                            'enrolled' => $query->whereHas('amapiDevice'),
                            'not_enrolled' => $query->whereDoesntHave('amapiDevice'),
                            default => $query,
                        };
                    }),

                SelectFilter::make('enrollment_mode')
                    ->label('Mode d\'enrôlement')
                    ->options([
                        'FULLY_MANAGED' => 'Fully Managed',
                        'COPE' => 'COPE',
                    ])
                    ->multiple()
                    ->query(function (Builder $query, array $data): Builder {
                        $values = (array) ($data['values'] ?? $data['value'] ?? []);

                        if (empty($values)) {
                            return $query;
                        }

                        return $query->whereHas(
                            'amapiDevice',
                            fn (Builder $amapi) => $amapi->whereIn('enrollment_mode', $values)
                        );
                    }),

                SelectFilter::make('phone_brand')
                    ->label('Marque')
                    ->options(fn (): array => Phone::query()->distinct()->orderBy('brand')->pluck('brand', 'brand')->all())
                    ->searchable()
                    ->multiple()
                    ->query(function (Builder $query, array $data): Builder {
                        $values = (array) ($data['values'] ?? $data['value'] ?? []);

                        if (empty($values)) {
                            return $query;
                        }

                        return $query->whereHas('phone', fn (Builder $phone) => $phone->whereIn('brand', $values));
                    }),

                SelectFilter::make('liberated')
                    ->label('Libération')
                    ->options([
                        'liberated' => 'Libéré',
                        'not_liberated' => 'Encore sous contrôle',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $values = (array) ($data['values'] ?? $data['value'] ?? []);

                        if (empty($values)) {
                            return $query;
                        }

                        return match ($data['value']) {
                            'liberated' => $query
                                ->whereHas('financingPlan', fn (Builder $plan) => $plan
                                    ->where('status', 'paid_in_full')
                                    ->where('remaining_balance', 0)
                                )
                                ->where(fn (Builder $q) => $q
                                    ->whereDoesntHave('amapiDevice')
                                    ->orWhereHas('amapiDevice', fn (Builder $amapi) => $amapi->whereNotNull('amapi_released_at'))
                                ),
                            'not_liberated' => $query
                                ->whereHas('amapiDevice')
                                ->whereDoesntHave('amapiDevice', fn (Builder $amapi) => $amapi->whereNotNull('amapi_released_at')),
                            default => $query,
                        };
                    }),

                Filter::make('locked_since')
                    ->label('Verrouillé depuis plus de (jours)')
                    ->schema([
                        TextInput::make('days')
                            ->label('Nombre de jours')
                            ->numeric()
                            ->minValue(1),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $days = (int) ($data['days'] ?? 0);

                        if (! ($data['isActive'] ?? false) || $days < 1) {
                            return $query;
                        }

                        return $query->whereHas(
                            'lockHistory',
                            fn (Builder $history) => $history
                                ->whereIn('action', ['LOCK', 'LOCK_ATTEMPT'])
                                ->where('created_at', '<=', now()->subDays($days))
                        );
                    }),

                Filter::make('last_seen')
                    ->label('Dernière connexion')
                    ->schema([
                        Select::make('range')
                            ->label('Période')
                            ->options([
                                '24h' => 'Dernières 24 h',
                                '7d' => '7 derniers jours',
                                '30d' => '30 derniers jours',
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! ($data['isActive'] ?? false)) {
                            return $query;
                        }

                        return match ($data['range'] ?? null) {
                            '24h' => $query->where('last_seen_at', '>=', now()->subDay()),
                            '30d' => $query->where('last_seen_at', '>=', now()->subDays(30)),
                            '7d' => $query->where('last_seen_at', '>=', now()->subDays(7)),
                            default => $query,
                        };
                    }),

                TrashedFilter::make(),
            ])
            ->actions([
                // Affichage "Téléphone libéré" quand le device n'est plus sous contrôle AMAPI
                Action::make('liberated_label')
                    ->label('Téléphone libéré')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Device $record) => $record->isLiberated()),

                Action::make('amapi_status')
                    ->label('État AMAPI')
                    ->icon('heroicon-o-shield-check')
                    ->color('info')
                    ->modalHeading('QR Code d\'enrôlement AMAPI')
                    ->modalContent(fn (Device $record) => view('filament.devices.qr-code', [
                        'qrcode' => Helper::generateJsonQrCode($record),
                    ]))
                    ->modalWidth('lg')
                    ->visible(fn (Device $record) => ! $record->isLiberated()),

                Action::make('lock_history')
                    ->label('Historique de verrouillage')
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->modalHeading('Historique de verrouillage')
                    ->modalContent(fn (Device $record) => view('filament.devices.lock-history', [
                        'lockHistory' => $record->lockHistory()
                            ->with(['device.client', 'triggeredByUser'])
                            ->latest()
                            ->limit(20)
                            ->get(),
                    ]))
                    ->modalWidth('4xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fermer')
                    ->visible(fn (Device $record) => ! $record->isLiberated()),
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
                        ->visible(fn (Device $record) => ! $record->isLocked() && ! $record->isLiberated()),

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
                        ->visible(fn (Device $record) => $record->isLocked() && ! $record->isLiberated()),

                    // Désinstaller AMAPI
                    Action::make('uninstall_amapi')
                        ->label(fn (Device $record) => $record->amapiDevice?->isCopeEnrolled()
                            ? 'Libérer la propriété AMAPI'
                            : 'Désinstaller AMAPI')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading(fn (Device $record) => $record->amapiDevice?->isCopeEnrolled()
                            ? 'Libérer la propriété AMAPI ?'
                            : 'Désinstaller AMAPI ?')
                        ->modalDescription(fn (Device $record) => $record->amapiDevice?->isCopeEnrolled()
                            ? 'La propriété de l\'appareil sera cédée à l\'utilisateur : il sort du contrôle AMAPI et conserve ses applications. Cette action est irréversible.'
                            : 'Le device sera supprimé de l\'enterprise AMAPI. Cette action est irréversible.')
                        ->action(function (Device $record) {
                            $amapiClient = app(AMAPIClientService::class);
                            $isCope = (bool) $record->amapiDevice?->isCopeEnrolled();

                            try {
                                $success = $amapiClient->releaseDevice(
                                    $record,
                                    'ADMIN_UNINSTALL',
                                    auth()->id()
                                );

                                if ($success) {
                                    Notification::make()
                                        ->title($isCope
                                            ? 'Propriété AMAPI libérée'
                                            : 'Device désinstallé de AMAPI')
                                        ->success()
                                        ->send();
                                } else {
                                    Notification::make()
                                        ->title('Échec de la libération')
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
                        ->visible(fn (Device $record) => $record->isFullyPaid() && ! $record->isLiberated()),

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
                                    ->title('Action effectuée : '.$result['action'])
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Vérification échouée')
                                    ->body($result['error'] ?? 'Erreur inconnue')
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->visible(fn (Device $record) => ! $record->isLiberated()),

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
                        })
                        ->visible(fn (Device $record) => ! $record->isLiberated()),
                ]),
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
