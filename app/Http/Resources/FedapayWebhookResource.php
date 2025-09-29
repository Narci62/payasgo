<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FedapayWebhookResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'device_id' => $this->device_id,
            'registration_token_id' => $this->registration_token_id,
            'total_price' => $this->total_price,
            'down_payment' => $this->down_payment,
            'remaining_balance' => $this->remaining_balance,
            'installment_amount' => $this->installment_amount,
            'next_payment_due_date' => $this->next_payment_due_date,
            'grace_period_ends_at' => $this->grace_period_ends_at,
            'next_offline_unlock_code' => $this->next_offline_unlock_code,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
