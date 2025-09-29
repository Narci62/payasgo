<?php
namespace App\Services;

use App\Models\Device;

class DeviceService {

    public function createDevice(array $data)
    {
        return Device::create([
            'client_id' => $data['client_id'],
            'fcm_token' => $data['fcm_token'] ?? null,
            'android_version' => $data['device_info']['android_version'],
            'serial_number' => $data['device_info']['serial_number'],
            'imei' => null,
            'status' => 'active',
            'last_seen_at' => now(),
        ]);
    }
}
