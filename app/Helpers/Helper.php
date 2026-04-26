<?php

namespace App\Helpers;

use App\Models\AmapiDevice;
use Carbon\Carbon;
use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Log;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class Helper
{
    /**
     * Generate a random token.
     */

    public static function generateRandomToken($length = 32)
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Generate a random string.
     */
    public static function generateRandomString($length = 10)
    {
        return substr(str_shuffle(str_repeat("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ", ceil($length / 62))), 0, $length);
    }

    /**
     * Generate a random offline unlocked token (8 bytes) with number only (ex : 2308-3988).
     */
    public static function offlineUnlockedToken($length = 8)
    {
        return substr(str_shuffle(str_repeat("0123456789", ceil($length / 10))), 0, $length);
    }

    /***
     * Generate a unique public identifier with five numbers for customers
     */
    public static function generateUniquePublicIdentifier(): string
    {
        return sprintf('%05d', random_int(0, 99999));
    }

    /***
     * Format Date to yyy-mm-dd ()
     */
    public static function formatDate($date): string
    {
        return Carbon::parse($date)->format('yyyy-mm-dd');
    }

    public static function getAccessToken(): ?string
    {
        return cache()->remember('amapi_access_token', 3500, function () {
            try {
                $serviceAccountPath = config('services.amapi.service_account_json');

                if (!file_exists($serviceAccountPath)) {
                    Log::error("❌ Fichier service account introuvable : {$serviceAccountPath}");
                    return null;
                }

                $client = new GoogleClient();
                $client->setAuthConfig($serviceAccountPath);
                $client->addScope('https://www.googleapis.com/auth/androidmanagement');

                $token = $client->fetchAccessTokenWithAssertion();

                return $token['access_token'] ?? null;
            } catch (\Exception $e) {
                Log::error('Erreur lors de l\'obtention du token : ' . $e->getMessage());
                return null;
            }
        });
    }


     public static function generateJsonQrCode($data): string
    {
        $amapi_device = AmapiDevice::where('device_id', $data->id)->first();
        $jsonString = json_encode($amapi_device->qr_code_data);

        return QrCode::margin(2)->size(300)->generate($jsonString);

    }
}
