<?php
namespace App\Services;

use App\Models\Device;

class DeviceService {

    public function createDevice(array $data)
    {
        return Device::create([
            'client_id' => $data['client_id'],
            'fcm_token' => $data['fcm_token'] ?? null,
            'device_id' => $data['device_info']['device_id'],
            'device_name' => $data['device_info']['device_name'],
            'device_brand' => $data['device_info']['device_brand'] ?? null,
            'device_model' => $data['device_info']['device_model'] ?? null,
            'status' => 'active',
            'last_seen_at' => now(),
        ]);
    }
}
