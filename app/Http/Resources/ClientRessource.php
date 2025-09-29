<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientRessource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->loadMissing(['registrationTokens']);
        return [
            "message" => "Client crée avec success",
            "client" => [
                "full_name" => $this->full_name,
                "address" => $this->address,
                "phone_number" => $this->phone_number,
                "identity_document_type" => $this->identity_document_type,
                "identity_document_number" => $this->identity_document_number,
                "identity_document_path" => asset("storage/" . $this->identity_document_path),
                "created_at" => $this->created_at,
            ],
            "token" => [
                "type" => "Bearer",
                "value" => $this->registrationTokens->where("used_at", null)->first()->token,
            ],
        ];
    }
}
