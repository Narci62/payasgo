<?php

namespace App\Filament\Concerns;

use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;

/**
 * Édition d'un contrat de financement depuis un tableau Filament.
 *
 * Partagé par ContractsDevicesOverview et les trois widgets de contrats :
 * les quatre affichent Financing_plan et proposent d'éditer un contrat
 * existant.
 *
 * Le schéma est volontairement distinct du formulaire de
 * FinancingPlanResource : celui-ci est un formulaire de CRÉATION. client_id et
 * phone_id n'existent pas sur financing_plans (le plan porte device_id et
 * registration_token_id) et servent à fabriquer la Device et le
 * Registration_token. Les réutiliser proposerait au client de rattacher un
 * contrat existant à un autre client ou un autre téléphone.
 */
trait EditsFinancingPlan
{
    public static function canEditFinancingPlans(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->hasAnyRole(['admin', 'super-admin']);
    }

    /**
     * @return array<int, Component>
     */
    public function financingPlanEditSchema(): array
    {
        return [
            Section::make('Montants')
                ->schema([
                    TextInput::make('total_price')
                        ->label('Prix Cash')
                        ->numeric()
                        ->prefix('CFA')
                        ->required(),

                    TextInput::make('down_payment')
                        ->label('Acompte')
                        ->numeric()
                        ->prefix('CFA')
                        ->required(),

                    TextInput::make('remaining_balance')
                        ->label('Solde restant')
                        ->numeric()
                        ->prefix('CFA')
                        ->required(),

                    TextInput::make('installment_amount')
                        ->label('Mensualité')
                        ->numeric()
                        ->prefix('CFA')
                        ->required(),
                ])
                ->columns(2),

            Section::make('Échéancier')
                ->schema([
                    TextInput::make('days_interval')
                        ->label('Intervalle de jours entre les paiements')
                        ->integer()
                        ->required(),

                    Select::make('status')
                        ->label('Statut du contrat')
                        ->options([
                            'active' => 'Actif',
                            'paid_in_full' => 'Soldé',
                            'defaulted' => 'En attente',
                        ])
                        // L'accessor getStatusAttribute() réécrit la valeur à la
                        // lecture ('actif', 'soldé', 'En attente'). Sans cette
                        // lecture brute, le Select se remplirait avec 'actif' et
                        // enregistrerait 'actif' en base, hors enum.
                        ->afterStateHydrated(fn (Select $component) => $component
                            ->state((string) ($component->getRecord()?->getRawOriginal('status') ?? 'active'))
                        )
                        ->required(),

                    DateTimePicker::make('next_payment_due_date')
                        ->label('Prochaine échéance')
                        ->seconds(false),

                    TextInput::make('next_offline_unlock_code')
                        ->label('Code de déverrouillage')
                        ->maxLength(255),
                ])
                ->columns(2),
        ];
    }

    /**
     * Un EditAction déclaré sur un TableWidget ne résout pas le formulaire du
     * composant : le schéma doit être attaché à l'action.
     */
    public function editFinancingPlanAction(string $label = 'Ouvrir le contrat'): EditAction
    {
        return EditAction::make()
            ->label($label)
            ->icon('heroicon-m-pencil-square')
            ->schema($this->financingPlanEditSchema())
            ->modalWidth('4xl')
            ->visible(fn (): bool => static::canEditFinancingPlans());
    }
}
