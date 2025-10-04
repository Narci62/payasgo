<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->loadMissing(['financingPlan']);

        return [
            'message' => 'Appareil enrégistré avec succès',
            'device_id' => $this->public_id,
            'subscription' => [
                'expires_at' => $this->financingPlan?->next_payment_due_date,
                'grace_period_ends_at' => $this->financingPlan?->grace_period_ends_at,
                'status' => $this->financingPlan?->status,
                'next_offline_unlock_code' => $this->financingPlan?->next_offline_unlock_code,
                'amount_paid' => $this->financingPlan?->total_price - $this->financingPlan?->remaining_balance,
                'amount_remaining' => $this->financingPlan?->remaining_balance,
                'payment_instructions' => '*880*2*3876*'. $this->financingPlan?->installment_amount .'*302938*code#',
                'identifiant_client' => "Référence client : 123456",
            ],
            'config' => [
                'check_interval_minutes' => 60,
            ]
        ];
    }
}
