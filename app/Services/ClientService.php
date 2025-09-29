<?php
namespace App\Services;

use App\Models\Client;

class ClientService {

    public function createClient(array $data): Client {

        $identity_document_path =asset('storage');  // $data["identity_document_proof"]->store("identity_documents", "public");

        return Client::create([
            "full_name" => $data["full_name"],
            "phone_number" => $data["phone_number"],
            "address" => $data["address"],
            "identity_document_type" => $data["identity_document_type"],
            "identity_document_number" => $data["identity_document_number"],
            "identity_document_file_path" => $identity_document_path,
        ]);
    }

}
