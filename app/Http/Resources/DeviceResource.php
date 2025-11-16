<?php

namespace App\Http\Resources;

use Carbon\Carbon;
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
        $this->loadMissing(['financingPlan','client']);

        $expiresAt = $this->financingPlan?->next_payment_due_date;
        $gracePeriodEndsAt = $this->financingPlan?->grace_period_ends_at;

        return [
            'message' => 'Appareil enrégistré avec succès',
            'device_id' => $this->public_id,
            'subscription' => [
                'expires_at' => $expiresAt,
                'grace_period_ends_at' => $gracePeriodEndsAt,
                'status' => $this->financingPlan?->status,
                'next_offline_unlock_code' => $this->financingPlan?->next_offline_unlock_code,
                'amount_paid' => $this->financingPlan?->total_price - $this->financingPlan?->remaining_balance,
                'amount_remaining' => $this->financingPlan?->remaining_balance,
                'payment_instructions' => '*880*2*3876*'. $this->financingPlan?->installment_amount .'*302938*code#',
                "identifiant_client" => $this->client?->reference,
            ],
            'config' => [
                'check_interval_minutes' => 60,
            ],
            'user' => [
                'reference' => $this->client->reference,
                'client_name' => $this->client->full_name,
                'payment_date' => Carbon::parse($this->client?->created_at)->format('d-m-Y'),
                'total_amount' => $this->financingPlan?->total_price,
                'phone_name' => $this->client->phone_number,
                'admin_note' => $this->notes
            ]
        ];
    }
}
