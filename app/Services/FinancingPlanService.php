<?php

namespace App\Services;

use App\Helpers\Helper;
use App\Models\Device;
use App\Models\Financing_plan;
use Carbon\Carbon;

class FinancingPlanService
{
    public function createFinancingPlan(array $data): Financing_plan
    {
        $date_payment_due = $this->calculateNextPaymentDueDate(Carbon::now(), $data['days_interval'] ?? 30);
        $grace_period_ends_at = $this->calculateGracePeriod($date_payment_due->copy());
        $remaining_balance = $data['total_price'] - $data['down_payment'];
        $next_offline_unlock_code = $this->nextOfflineUnlockCode();

        return Financing_plan::create([
            'device_id' => $data['device_id'] ?? null,
            'registration_token_id' => $data['registration_token_id'],
            'total_price' => $data['total_price'],
            'down_payment' => $data['down_payment'],
            'remaining_balance' => $remaining_balance,
            'installment_amount' => $data['installment_amount'],
            'next_payment_due_date' => $date_payment_due,
            'days_interval' => $data['days_interval'] ?? 30,
            'grace_period_ends_at' => $grace_period_ends_at,
            'next_offline_unlock_code' => $next_offline_unlock_code,
        ]);
    }

    public function showFinancingPlan($id): ?Financing_plan
    {
        return Financing_plan::find($id);
    }

    public function updateFinancingPlan(int $id, array $data): Financing_plan
    {
        $plan = Financing_plan::find($id);
        if (! $plan) {
            throw new \Exception('Financing plan not found');
        }
        $plan->update($data);

        return $plan;
    }

    public function updateFinancingPlanByToken(int $token_id, array $data): ?Financing_plan
    {
        $plan = Financing_plan::where('registration_token_id', $token_id)
            ->first();

        if (! $plan) {
            throw new \Exception('Financing plan not found');
        }

        $plan->update([
            'status' => 'active',
            'device_id' => $data['device_id'],
        ]);

        return $plan;
    }

    // public function checkEligibilityAndReturnNewAmount(Financing_plan $financing_plan, $amount)
    // {
    //     /**
    //      * On va verifier si le montant envoyé par l'utilisateur est supérieur ou égal au montant du versement stocké dans financing plan
    //      * On recupere la date d'aujourd'hui, la date du prochain paiement et l'intervalle de paiement stocker dans financing plan
    //      * On divise la difference du nombre de jours entre les deux dates par l'intervalle de paiement et on prend la partie entier
    //      * Si le resultat de la division est supérieur à 1, on multipliera par une penalité de 50%(du montant de versement par date de paiement)
    //      * Ensuite on multiplie le resultat par le montant de versement par date de paiement
    //      * Si ce montant est inferieur au montant $amount, on rejette le paiement sinon on accepte
    //      *
    //      */
    //     $penalite = 0;
    //     $nbr_intervall = 0;
    //     $total_normal = $amount;
    //     $payout = $total_all = $financing_plan->installment_amount;
    //     if ($payout > $amount) {
    //         return ["message" => "montant insuffisant", "status" => false];
    //     }

    //     $now = Carbon::now();
    //     $next_pa = Carbon::parse($financing_plan->next_payment_due_date);
    //     $intervall_days = $financing_plan->days_interval;

    //     if ($now->greaterThan($next_pa)) {
    //         $diff_days = $now->diffInDays($next_pa);
    //         $nbr_intervall = (int) ($diff_days / $intervall_days);
    //         $total_normal = $payout * $nbr_intervall;

    //         if ($nbr_intervall >= 1) {
    //             $penalite = ($payout * 0.5) * $nbr_intervall;
    //             $total_all = $penalite + $total_normal;
    //             if ($amount < $total_all) {
    //                 return ["message" => "Le montant doit être au moins de $total_all FCFA pour couvrir les pénalités de retard.", "status" => false];
    //             }
    //         }
    //     }
    //     else{
    //         $nbr_intervall = (int) ($total_normal / $payout);

    //       //  dd($nbr_intervall);
    //     }

    //     return [
    //         "nbr_interval" => $nbr_intervall,
    //         "status" => "ok",
    //         "total_normal" => $total_normal,
    //         "penalite" => $penalite
    //     ];

    // }

    public function checkEligibilityAndReturnNewAmount(Financing_plan $financing_plan, $amount)
    {
        $penalite = 0;
        $nbr_intervall = 0;
        $payout = $financing_plan->installment_amount;

        // Vérification du montant minimum
        if ($payout > $amount) {
            return ['message' => 'Montant insuffisant', 'status' => false];
        }

        $now = Carbon::now();
        $next_payment_due = Carbon::parse($financing_plan->next_payment_due_date);
        $intervall_days = $financing_plan->days_interval;

        // Si on est en retard
        if ($now->greaterThan($next_payment_due)) {
            // Nombre de jours de retard
            $diff_days = abs($now->diffInDays($next_payment_due));

            // Nombre d'échéances manquées (périodes complètes)
            $nbr_echeances_manquees = (int) ($diff_days / $intervall_days);
            if ($nbr_echeances_manquees < 1) {
                $nbr_echeances_manquees = 1;
            }

            // Si au moins 1 échéance manquée, il y a pénalité
            if ($nbr_echeances_manquees >= 1) {
                $total_normal = $payout * $nbr_echeances_manquees;
                $penalite = ($payout * 0.5) * $nbr_echeances_manquees;
                $total_all = $total_normal + $penalite;

                if ($amount < $total_all) {
                    return [
                        'message' => "Le montant doit être au moins de $total_all FCFA pour couvrir les pénalités de retard.",
                        'status' => false,
                    ];
                }

                // Le montant couvre les pénalités, calculer combien d'échéances il peut payer
                // On soustrait d'abord les pénalités
                $montant_restant = $amount - $penalite;
                $nbr_intervall = (int) ($montant_restant / $payout);
                $total_normal = $payout * $nbr_intervall;
            } else {
                // En retard mais moins d'une période complète
                // Pas de pénalité encore, on calcule normalement
                $nbr_intervall = (int) ($amount / $payout);
                $total_normal = $payout * $nbr_intervall;
            }
        } else {
            // Paiement à temps ou en avance
            $nbr_intervall = (int) ($amount / $payout);
            $total_normal = $payout * $nbr_intervall;
        }

        return [
            'nbr_interval' => $nbr_intervall,
            'status' => true,
            'total_normal' => $total_normal,
            'penalite' => $penalite,
        ];
    }

    public function savePayment(Financing_plan $financingPlan, $amountPaid, string $method, $transactionId): Financing_plan
    {
        $is_full_payment = false; // paiement complète
        $newbalance = $financingPlan->remaining_balance - $amountPaid;
        if ($newbalance < 0) {
            $newbalance = 0;
        }
        $financingPlan->remaining_balance = $newbalance;

        // next payment date
        $nbr_deviseur = (int) ($amountPaid / $financingPlan->installment_amount);
        if ($nbr_deviseur >= 1) {
            // calculate next payment due date
            $financingPlan->next_payment_due_date = $this->calculateNextPaymentDueDate(Carbon::parse($financingPlan->next_payment_due_date), $financingPlan->days_interval * $nbr_deviseur);
        }

        // $financingPlan->next_payment_due_date = $this->calculateNextPaymentDueDate(Carbon::parse($financingPlan->next_payment_due_date), $financingPlan->days_interval);

        // next offline unlock code
        $financingPlan->next_offline_unlock_code = $this->nextOfflineUnlockCode();

        // check if financing plan is paid in full
        if ($newbalance == 0) {
            $financingPlan->status = 'paid_in_full';
            // save uninstall code
            do {
                $financingPlan->uninstall_code = Helper::generateRandomString();
            } while (Financing_plan::where('uninstall_code', $financingPlan->uninstall_code)->exists());
        }

        if ($newbalance != 0 && $financingPlan->installment_amount > $newbalance) {
            $financingPlan->installment_amount = $newbalance;
        }

        $financingPlan->save();

        // save payment histories
        (new PaymentService)->store([
            'financing_plan_id' => $financingPlan->id,
            'amount' => $amountPaid,
            'method' => $method,
            'transaction_id' => $transactionId, // ID de la transaction Fedapay
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        $device = $financingPlan->device;

        if ($device) {

            if ($financingPlan->getRawOriginal('status') === 'paid_in_full') {
                // delete amapi device
                (new AMAPIClientService)->deleteDevice($device, 'PAYMENT_RECEIVED');
            } else {
                // unlock amapi device
                (new AMAPIClientService)->unlockDevice($device, 'PAYMENT_RECEIVED');
            }
        }

        return $financingPlan;
    }

    public function calculateGracePeriod(Carbon $date): Carbon
    {
        return $date->addDays(5);
    }

    public function calculateNextPaymentDueDate(Carbon $date_payment, int $nbre_schedule_day = 30): Carbon
    {
        return $date_payment->addDays($nbre_schedule_day);
    }

    public function nextOfflineUnlockCode(): string
    {
        do {
            $next_offline_unlock_code = Helper::offlineUnlockedToken();
        } while (Financing_plan::where('next_offline_unlock_code', $next_offline_unlock_code)->exists());

        return $next_offline_unlock_code;
    }
}
