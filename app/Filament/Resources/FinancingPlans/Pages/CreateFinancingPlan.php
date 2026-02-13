<?php

namespace App\Filament\Resources\FinancingPlans\Pages;

use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\FinancingPlans\FinancingPlanResource;
use App\Models\Phone;
use App\Services\DeviceService;
use Illuminate\Support\Facades\Cache;

class CreateFinancingPlan extends CreateRecord
{
    protected static string $resource = FinancingPlanResource::class;

    protected function beforeCreate(): void
    {

        $formData = $this->data;

        // create device

        $device_name = Phone::find($formData['phone_id'])->brand;

        $device = new DeviceService();
        $createdDevice = $device->createDevice([
            'phone_id' => $formData['phone_id'],
            'device_id' => "manual-" . uniqid(),
            'device_name' => $device_name,
            'client_id' => $formData['client_id'],
        ]);

        //call api to create device in google amapi
        // $device->createDeviceInAmapi($createdDevice);

        // save device_id in cache to be used in afterCreate to update financing plan with device id
        Cache::put('created_device_id', $createdDevice->id, now()->addMinutes(2));
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $registration_token = app("App\Services\RegistrationTokenService")->createToken(['client_id' => $data['client_id']]);
        $data['registration_token_id'] = $registration_token->id;

        unset($data['client_id']);
        unset($data['phone_id']);

        $financing_plan_service = app("App\Services\FinancingPlanService");

        $date_payment_due = $financing_plan_service->calculateNextPaymentDueDate(Carbon::now(), $data['days_interval'] ?? 30);
        $grace_period_ends_at = $financing_plan_service->calculateGracePeriod($date_payment_due->copy());
        $remaining_balance = $data['total_price'] - $data['down_payment'];
        $next_offline_unlock_code = $financing_plan_service->nextOfflineUnlockCode();



        $data['remaining_balance'] = $remaining_balance;
        $data['next_offline_unlock_code'] = $next_offline_unlock_code;
        $data['next_payment_due_date'] = $date_payment_due;
        $data['grace_period_ends_at'] = $grace_period_ends_at;
        $data['status'] = 'active';


        return $data;
    }

    protected function afterCreate(): void
    {
        $financing_plan = $this->record;


        // update financing plan with device id
        $financing_plan->update([
            'device_id' => Cache::pull('created_device_id'),
        ]);


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
