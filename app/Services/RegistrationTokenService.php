<?php
namespace App\Services;

use App\Helpers\Helper;
use App\Models\Registration_token;

class RegistrationTokenService {

    public function createToken(array $data)
    {
        $token = $this->generateToken();
        return Registration_token::create([
            'client_id' => $data['client_id'],
            'device_id' => $data['device_id'] ?? null,
            'token' => $token,
            'expires_at' => now()->addHours(24)
        ]);
    }

    public function validateToken(string $token) : ?Registration_token
    {
        return Registration_token::where('token', $token)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    private function generateToken()
    {
        do {
            $token = Helper::generateRandomToken();
        } while (Registration_token::where('token', $token)->exists());

        return $token;
    }

}
