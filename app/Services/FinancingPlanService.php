<?php

namespace App\Services;

use App\Helpers\Helper;
use App\Models\AmapiSyncLog;
use App\Models\Device;
use App\Models\Financing_plan;
use App\Models\Installment;
use App\Models\Penalty;
use App\Models\User;
use App\Notifications\AmapiSyncFailedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class FinancingPlanService
{
    public function createFinancingPlan(array $data): Financing_plan
    {
        $date_payment_due = $this->calculateNextPaymentDueDate(Carbon::now(), $data['days_interval'] ?? 30);
        $grace_period_ends_at = $this->calculateGracePeriod($date_payment_due->copy());
        $remaining_balance = $data['total_price'] - $data['down_payment'];
        $next_offline_unlock_code = $this->nextOfflineUnlockCode();

        $plan = Financing_plan::create([
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

        // Créer la première échéance
        Installment::create([
            'financing_plan_id' => $plan->id,
            'due_date' => $date_payment_due,
            'amount' => $data['installment_amount'],
            'remaining_amount' => $data['installment_amount'],
            'status' => 'pending',
        ]);

        return $plan;
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
        $penaltyService = new PenaltyService;

        $payout = $financing_plan->installment_amount;

        if ($payout > $amount) {
            return ['message' => 'Montant insuffisant', 'status' => false];
        }

        $now = Carbon::now();
        $next_payment_due = Carbon::parse($financing_plan->next_payment_due_date);

        // Si on est en retard, calculer les pénalités via le nouveau système
        if ($now->greaterThan($next_payment_due)) {
            // Trouver l'échéance courante
            $currentInstallment = $financing_plan->installments()
                ->where('status', '!=', 'paid')
                ->orderBy('due_date', 'asc')
                ->first();

            if ($currentInstallment) {
                $penalty = $penaltyService->calculatePenalty($currentInstallment);
                $totalDue = $payout + $penalty;

                if ($amount < $totalDue) {
                    return [
                        'message' => "Le montant doit être au moins de {$totalDue} FCFA pour couvrir l'échéance et les pénalités de retard.",
                        'status' => false,
                    ];
                }

                // Le montant couvre, calculer combien d'échéances il peut payer
                $montant_restant = $amount - $penalty;
                $nbr_intervall = (int) ($montant_restant / $payout);
                $total_normal = $payout * $nbr_intervall;

                return [
                    'nbr_interval' => $nbr_intervall,
                    'status' => true,
                    'total_normal' => $total_normal,
                    'penalite' => $penalty,
                ];
            }
        }

        // Paiement à temps ou échéance non trouvée
        $nbr_intervall = (int) ($amount / $payout);
        $total_normal = $payout * $nbr_intervall;

        return [
            'nbr_interval' => $nbr_intervall,
            'status' => true,
            'total_normal' => $total_normal,
            'penalite' => 0,
        ];
    }

    public function savePayment(Financing_plan $financingPlan, $amountPaid, string $method, $transactionId, float $penaltyAmount = 0): Financing_plan
    {
        $penaltyService = new PenaltyService;

        // Trouver l'échéance courante
        $currentInstallment = $financingPlan->installments()
            ->where('status', '!=', 'paid')
            ->orderBy('due_date', 'asc')
            ->first();

        if ($currentInstallment) {
            // Appliquer l'ordre d'imputation : pénalités d'abord, puis principal
            $allocation = $penaltyService->allocatePayment($currentInstallment, $amountPaid);

            // Mettre à jour le solde du plan
            $financingPlan->remaining_balance = max(0, $financingPlan->remaining_balance - $allocation['principal_paid']);

            // Marquer l'échéance comme payée si le principal est soldé
            if ($allocation['remaining_amount'] == 0) {
                $currentInstallment->update([
                    'remaining_amount' => 0,
                    'status' => 'paid',
                ]);

                // Avancer la date du prochain paiement
                $financingPlan->next_payment_due_date = $this->calculateNextPaymentDueDate(
                    Carbon::parse($currentInstallment->due_date),
                    $financingPlan->days_interval
                );

                // Créer la prochaine échéance si le plan n'est pas soldé
                if ($financingPlan->remaining_balance > 0) {
                    $nextDueDate = $this->calculateNextPaymentDueDate(
                        Carbon::parse($currentInstallment->due_date),
                        $financingPlan->days_interval
                    );
                    $nextAmount = min($financingPlan->installment_amount, $financingPlan->remaining_balance);

                    Installment::create([
                        'financing_plan_id' => $financingPlan->id,
                        'due_date' => $nextDueDate,
                        'amount' => $nextAmount,
                        'remaining_amount' => $nextAmount,
                        'status' => 'pending',
                    ]);
                }
            } else {
                // Paiement partiel sur le principal
                $currentInstallment->update([
                    'remaining_amount' => $allocation['remaining_amount'],
                ]);
            }

            // Enregistrer les pénalités payées
            if ($allocation['penalties_paid'] > 0) {
                $paymentResult = (new PaymentService)->store([
                    'financing_plan_id' => $financingPlan->id,
                    'amount' => $allocation['penalties_paid'],
                    'method' => $method,
                    'transaction_id' => $transactionId,
                    'status' => 'completed',
                    'paid_at' => now(),
                ]);

                // Les pénalités sont déjà enregistrées par le système de paliers
            }
        } else {
            // Pas d'échéance trouvée, comportement par défaut
            $newbalance = $financingPlan->remaining_balance - $amountPaid;
            if ($newbalance < 0) {
                $newbalance = 0;
            }
            $financingPlan->remaining_balance = $newbalance;
        }

        // next offline unlock code
        $financingPlan->next_offline_unlock_code = $this->nextOfflineUnlockCode();

        // check if financing plan is paid in full
        if ($financingPlan->remaining_balance == 0) {
            $financingPlan->status = 'paid_in_full';
            do {
                $financingPlan->uninstall_code = Helper::generateRandomString();
            } while (Financing_plan::where('uninstall_code', $financingPlan->uninstall_code)->exists());
        } else {
            $financingPlan->status = 'active';
        }

        $financingPlan->save();

        // save payment histories
        $paymentResult = (new PaymentService)->store([
            'financing_plan_id' => $financingPlan->id,
            'amount' => $amountPaid,
            'method' => $method,
            'transaction_id' => $transactionId,
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        // Enregistrer la pénalité passée en paramètre (compatibilité avec l'ancien flux)
        if ($penaltyAmount > 0) {
            $payment = $paymentResult['payment'];
            $existingPenalty = Penalty::where('payment_id', $payment->id)->exists();

            if (! $existingPenalty) {
                Penalty::create([
                    'financing_plan_id' => $financingPlan->id,
                    'payment_id' => $payment->id,
                    'amount' => $penaltyAmount,
                    'type' => null,
                    'reason' => 'Pénalité de retard',
                ]);
            }
        }

        $device = $financingPlan->device;

        if ($device) {
            $action = $financingPlan->getRawOriginal('status') === 'paid_in_full' ? 'DELETE' : 'UNLOCK';

            $syncLog = AmapiSyncLog::create([
                'financing_plan_id' => $financingPlan->id,
                'device_id' => $device->id,
                'action' => $action,
                'status' => 'pending',
            ]);

            try {
                if ($action === 'DELETE') {
                    $success = (new AMAPIClientService)->deleteDevice($device, 'PAYMENT_RECEIVED');
                } else {
                    $success = (new AMAPIClientService)->unlockDevice($device, 'PAYMENT_RECEIVED');
                }

                if ($success) {
                    $syncLog->markAsSuccess();
                    $financingPlan->update(['amapi_sync_status' => 'synced']);
                } else {
                    throw new \Exception('AMAPI API returned false');
                }
            } catch (\Exception $e) {
                $syncLog->markAsFailed($e->getMessage());
                $financingPlan->update([
                    'amapi_sync_status' => 'failed',
                    'amapi_sync_error' => $e->getMessage(),
                ]);

                Log::error('AMAPI sync failed after payment', [
                    'financing_plan_id' => $financingPlan->id,
                    'device_id' => $device->id,
                    'action' => $action,
                    'error' => $e->getMessage(),
                ]);

                $this->notifyAdminsOfSyncFailure($financingPlan, $device, $action, $e->getMessage());
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

    private function notifyAdminsOfSyncFailure(Financing_plan $plan, Device $device, string $action, string $error): void
    {
        $admins = User::role(['admin', 'super-admin'])->get();

        if ($admins->isEmpty()) {
            return;
        }

        Notification::send($admins, new AmapiSyncFailedNotification(
            $plan,
            $device,
            $action,
            $error
        ));
    }
}
