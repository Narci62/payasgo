<?php

use App\Models\Financing_plan;
use App\Models\Installment;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $plansSansInstallments = Financing_plan::whereDoesntHave('installments')->get();

        foreach ($plansSansInstallments as $plan) {
            Installment::create([
                'financing_plan_id' => $plan->id,
                'due_date' => $plan->next_payment_due_date,
                'amount' => $plan->installment_amount,
                'remaining_amount' => $plan->remaining_balance,
                'status' => $plan->remaining_balance > 0 ? 'pending' : 'paid',
            ]);
        }
    }

    public function down(): void
    {
        // Les installments créés seront supprimés par cascade via financing_plan_id FK
    }
};
