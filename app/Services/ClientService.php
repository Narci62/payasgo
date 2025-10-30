<?php
namespace App\Services;

use App\Helpers\Helper;
use App\Models\Client;

class ClientService {

    public function createClient(array $data): Client {

        $identity_document_path =asset('storage');  // $data["identity_document_proof"]->store("identity_documents", "public");
        $reference = $this->generateIdentifiantClient();

        return Client::create([
            "full_name" => $data["full_name"],
            "phone_number" => $data["phone_number"],
            "address" => $data["address"],
            "reference" => $reference,
            "identity_document_type" => $data["identity_document_type"],
            "identity_document_number" => $data["identity_document_number"],
            "identity_document_file_path" => $identity_document_path,
        ]);
    }

    private function generateIdentifiantClient(): string{
        do {
            $code = Helper::generateUniquePublicIdentifier();
        } while (Client::where("reference", $code)->exists());

        return $code;
    }

    public function getClientByDeviceToken($identifiant_client){
        return Client::where("reference", $identifiant_client)->first();
    }

}
