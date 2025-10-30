<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckTokenResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->loadMissing(['registrationTokens']);
        $registrationToken = $this->registrationTokens->where("used_at", null)->first();
        $token = $registrationToken ? $registrationToken->token : null;
        return [
            "client" => $this->full_name,
            "registration_token" => $token,
        ];
    }
}
