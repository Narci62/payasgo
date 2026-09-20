<?php

namespace App\Services;

use App\Models\Installment;
use App\Models\Penalty;

class PenaltyService
{
    /**
     * Calcule les pénalités pour une échéance en retard.
     *
     * Palier 1 (1-7 jours) : 0 FCFA
     * Palier 2 (8-15 jours) : 5000 FCFA fixes
     * Palier 3 (16+ jours) : blocs_30j × 10000 + cycles_14j × (0.05 × montant_échéance)
     */
    public function calculatePenalty(Installment $installment): float
    {
        $daysLate = $installment->getDaysLate();

        if ($daysLate <= 0) {
            return 0;
        }

        if ($daysLate >= 1 && $daysLate <= 7) {
            return 0;
        }

        if ($daysLate >= 8 && $daysLate <= 15) {
            return 5000;
        }

        // Palier 3 : 16 jours et au-delà
        return $this->calculatePalier3($daysLate, (float) $installment->amount);
    }

    /**
     * Calcule les pénalités du palier 3 avec deux composantes :
     * - Frais fixes : 10000 FCFA tous les 30 jours (premier déclenché à J+16)
     * - Frais variables : 0.05 × montant_échéance toutes les 2 semaines (14 jours)
     */
    private function calculatePalier3(int $daysLate, float $installmentAmount): float
    {
        // Blocs fixes de 30 jours : premier à J+16, puis tous les 30 jours
        $blocs30Jours = 1 + (int) (($daysLate - 16) / 30);
        $fraisFixes = $blocs30Jours * 10000;

        // Sous-cycles de 14 jours à partir du jour 16
        // Tout cycle entamé compte pour entier (ceil)
        $joursDansPalier3 = $daysLate - 15;
        $cycles14Jours = (int) ceil($joursDansPalier3 / 14);
        $fraisVariables = $cycles14Jours * (0.05 * $installmentAmount);

        return $fraisFixes + $fraisVariables;
    }

    /**
     * Retourne le détail du calcul des pénalités pour affichage.
     */
    public function getPenaltyBreakdown(Installment $installment): array
    {
        $daysLate = $installment->getDaysLate();

        if ($daysLate <= 0) {
            return [
                'days_late' => 0,
                'tier' => 0,
                'fixed_amount' => 0,
                'variable_amount' => 0,
                'total' => 0,
            ];
        }

        if ($daysLate >= 1 && $daysLate <= 7) {
            return [
                'days_late' => $daysLate,
                'tier' => 1,
                'fixed_amount' => 0,
                'variable_amount' => 0,
                'total' => 0,
            ];
        }

        if ($daysLate >= 8 && $daysLate <= 15) {
            return [
                'days_late' => $daysLate,
                'tier' => 2,
                'fixed_amount' => 5000,
                'variable_amount' => 0,
                'total' => 5000,
            ];
        }

        $blocs30Jours = 1 + (int) (($daysLate - 16) / 30);
        $fraisFixes = $blocs30Jours * 10000;

        $joursDansPalier3 = $daysLate - 15;
        $cycles14Jours = (int) ceil($joursDansPalier3 / 14);
        $fraisVariables = $cycles14Jours * (0.05 * $installment->amount);

        return [
            'days_late' => $daysLate,
            'tier' => 3,
            'blocs_30j' => $blocs30Jours,
            'cycles_14j' => $cycles14Jours,
            'fixed_amount' => $fraisFixes,
            'variable_amount' => $fraisVariables,
            'total' => $fraisFixes + $fraisVariables,
        ];
    }

    /**
     * Retourne le total des pénalités non payées pour une échéance.
     */
    public function getTotalPenaltiesDue(Installment $installment): float
    {
        return (float) $installment->penalties()->sum('amount');
    }

    /**
     * Calcule le montant total dû pour une échéance (principal + pénalités).
     */
    public function getTotalDue(Installment $installment): float
    {
        return $installment->remaining_amount + $this->getTotalPenaltiesDue($installment);
    }

    /**
     * Vérifie si l'échéance courante d'un plan est en retard.
     */
    public function isCurrentInstallmentOverdue(\App\Models\Financing_plan $plan): bool
    {
        $currentInstallment = $plan->installments()
            ->where('status', '!=', 'paid')
            ->orderBy('due_date', 'asc')
            ->first();

        if (! $currentInstallment) {
            return false;
        }

        return $currentInstallment->isOverdue();
    }

    /**
     * Vérifie si le device doit être verrouillé.
     * Lock si l'échéance courante est en retard (quel que soit le palier).
     */
    public function shouldDeviceBeLocked(\App\Models\Financing_plan $plan): bool
    {
        return $this->isCurrentInstallmentOverdue($plan);
    }

    /**
     * Applique l'ordre d'imputation : pénalités d'abord, puis principal.
     *
     * @return array{penalties_paid: float, principal_paid: float, remaining_amount: float, penalties_remaining: float, fully_paid: bool}
     */
    public function allocatePayment(Installment $installment, float $amount): array
    {
        $totalPenaltiesDue = $this->getTotalPenaltiesDue($installment);
        $penaltiesPaid = 0;
        $principalPaid = 0;
        $remainingAmount = $amount;

        // 1. Payer les pénalités d'abord
        if ($totalPenaltiesDue > 0 && $remainingAmount > 0) {
            if ($remainingAmount >= $totalPenaltiesDue) {
                $penaltiesPaid = $totalPenaltiesDue;
                $remainingAmount -= $totalPenaltiesDue;
            } else {
                $penaltiesPaid = $remainingAmount;
                $remainingAmount = 0;
            }
        }

        // 2. Le reste va sur le principal
        if ($remainingAmount > 0 && $installment->remaining_amount > 0) {
            if ($remainingAmount >= $installment->remaining_amount) {
                $principalPaid = $installment->remaining_amount;
                $remainingAmount -= $installment->remaining_amount;
            } else {
                $principalPaid = $remainingAmount;
                $remainingAmount = 0;
            }
        }

        $penaltiesRemaining = max(0, $totalPenaltiesDue - $penaltiesPaid);
        $principalRemaining = max(0, $installment->remaining_amount - $principalPaid);
        $fullyPaid = $principalRemaining == 0 && $penaltiesRemaining == 0;

        return [
            'penalties_paid' => $penaltiesPaid,
            'principal_paid' => $principalPaid,
            'remaining_amount' => $principalRemaining,
            'penalties_remaining' => $penaltiesRemaining,
            'fully_paid' => $fullyPaid,
        ];
    }

    /**
     * Enregistre les pénalités calculées dans la base de données.
     */
    public function storePenalties(Installment $installment, float $totalPenalty, ?int $paymentId = null): void
    {
        if ($totalPenalty <= 0) {
            return;
        }

        $daysLate = $installment->getDaysLate();

        // Palier 2 : frais fixe de 5000
        if ($daysLate >= 8 && $daysLate <= 15) {
            Penalty::create([
                'financing_plan_id' => $installment->financing_plan_id,
                'installment_id' => $installment->id,
                'payment_id' => $paymentId,
                'amount' => 5000,
                'type' => 'fixed_5000',
                'reason' => "Pénalité palier 2 - {$daysLate} jours de retard",
            ]);

            return;
        }

        // Palier 3 : frais fixes + frais variables
        if ($daysLate >= 16) {
            $breakdown = $this->getPenaltyBreakdown($installment);

            if ($breakdown['fixed_amount'] > 0) {
                Penalty::create([
                    'financing_plan_id' => $installment->financing_plan_id,
                    'installment_id' => $installment->id,
                    'payment_id' => $paymentId,
                    'amount' => $breakdown['fixed_amount'],
                    'type' => 'fixed_10000',
                    'reason' => "Pénalité palier 3 fixes - {$breakdown['blocs_30j']} bloc(s) de 30j - {$daysLate} jours de retard",
                ]);
            }

            if ($breakdown['variable_amount'] > 0) {
                Penalty::create([
                    'financing_plan_id' => $installment->financing_plan_id,
                    'installment_id' => $installment->id,
                    'payment_id' => $paymentId,
                    'amount' => $breakdown['variable_amount'],
                    'type' => 'variable_5pct',
                    'reason' => "Pénalité palier 3 variable - {$breakdown['cycles_14j']} cycle(s) de 14j - {$daysLate} jours de retard",
                ]);
            }
        }
    }
}
