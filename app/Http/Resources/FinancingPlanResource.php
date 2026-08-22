<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinancingPlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->loadMissing(['registrationToken']);

        return [
            'message' => 'Nouvelle vente échelonnée crée avec success',
            'client' => [
                'total_price' => $this->total_price,
                'down_payment' => $this->down_payment,
                'installment_amount' => $this->installment_amount,
                'remaining_balance' => $this->remaining_balance,
                'registration_token' => $this->registrationTokens->where('used_at', null)->first()->token,
                'created_at' => $this->created_at,
            ],
        ];
    }
}
