<?php

namespace App\Filament\Resources\FinancingPlans\Pages;

use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\FinancingPlans\FinancingPlanResource;

class CreateFinancingPlan extends CreateRecord
{
    protected static string $resource = FinancingPlanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $registration_token = app("App\Services\RegistrationTokenService")->createToken(['client_id' => $data['client_id']]);
        $data['registration_token_id'] = $registration_token->id;

        unset($data['client_id']);

        $financing_plan_service = app("App\Services\FinancingPlanService");

        $date_payment_due = $financing_plan_service->calculateNextPaymentDueDate(Carbon::now(), $data['days_interval'] ?? 30);
        $grace_period_ends_at = $financing_plan_service->calculateGracePeriod($date_payment_due->copy());
        $remaining_balance = $data['total_price'] - $data['down_payment'];
        $next_offline_unlock_code = $financing_plan_service->nextOfflineUnlockCode();



        $data['remaining_balance'] = $remaining_balance;
        $data['next_offline_unlock_code'] = $next_offline_unlock_code;
        $data['next_payment_due_date'] = $date_payment_due;
        $data['grace_period_ends_at'] = $grace_period_ends_at;


        return $data;
    }

    protected function afterCreate(): void
    {
        $financing_plan = $this->record;

        // save payment histories
        (new \App\Services\PaymentService())->store([
            'financing_plan_id' => $financing_plan->id,
            'amount' => $financing_plan->down_payment,
            'method' => "manual",
            'transaction_id' => uniqid('txn'),
            'status' => 'completed',
            'paid_at' => now(),
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

}
