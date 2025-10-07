<?php
namespace App\Helpers;

use Carbon\Carbon;

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
     * Format Date to yyy-mm-dd
     */
    public static function formatDate($date): string
    {
        return Carbon::parse($date)->format('yyyy-mm-dd');
    }
}
