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
            "registration_token_id" => $data['registration_token_id'],
            'total_price' => $data['total_price'],
            'down_payment' => $data['down_payment'],
            'remaining_balance' => $remaining_balance,
            'installment_amount' => $data['installment_amount'],
            'next_payment_due_date' => $date_payment_due,
            'days_interval' => $data['days_interval'] ?? 30,
            'grace_period_ends_at' => $grace_period_ends_at,
            'next_offline_unlock_code' => $next_offline_unlock_code
        ]);
    }

    public function showFinancingPlan($id): ?Financing_plan
    {
        return Financing_plan::find($id);
    }

    public function updateFinancingPlan(int $id, array $data): Financing_plan
    {
        $plan = Financing_plan::find($id);
        if (!$plan) {
            throw new \Exception("Financing plan not found");
        }
        $plan->update($data);
        return $plan;
    }

    public function updateFinancingPlanByToken(int $token_id, array $data): ?Financing_plan
    {
        $plan = Financing_plan::where('registration_token_id', $token_id)
            ->first();

        if (!$plan) {
            throw new \Exception("Financing plan not found");
        }

        $plan->update([
            'status' => 'active',
            'device_id' => $data['device_id'],
        ]);

        return $plan;
    }

    public function savePayment(Financing_plan $financingPlan, $amountPaid, string $method = 'fedapay', $transactionId = null): Financing_plan
    {

        $newbalance = $financingPlan->remaining_balance - $amountPaid;
        if ($newbalance < 0)  $newbalance = 0;
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
            $financingPlan->status = "paid_in_full";
            // other notification push to unlock phone forever
        }

        if($financingPlan->installment_amount > $newbalance) {
            $financingPlan->installment_amount = $newbalance;
        }

        $financingPlan->save();

        // save payment histories
       (new PaymentService())->store([
            'financing_plan_id' => $financingPlan->id,
            'amount' => $amountPaid,
            'method' => $method,
            'transaction_id' => $transactionId, // ID de la transaction Fedapay
            'status' => 'completed',
            'paid_at' => now(),
        ]);

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
