<?php

namespace App\Models;

use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use App\Services\DeviceMonitoringService;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Device extends Model
{
    /** @use HasFactory<\Database\Factories\DeviceFactory> */
    use HasFactory, HasApiTokens, Notifiable, SoftDeletes;

    protected $guarded = [];

    public function client() : BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function financingPlan() : HasOne
    {
        return $this->hasOne(Financing_plan::class);
    }

    public function registrationToken() : HasOne
    {
        return $this->hasOne(Registration_token::class);
    }

    /***
     * Before creating, we generate a unique public ID for the device.
     */
    protected static function booted(): void
    {
        static::creating(function (self $device) {
            $device->public_id = (string) Str::uuid();
        });
    }

    /**
     * Specifies the user's FCM token
     *
     * @return string|array
     */
    public function routeNotificationForFcm()
    {
        //return $this->fcm_token;
        return $this->getDeviceTokens();
    }

    public function amapiDevice(): HasOne
    {
        return $this->hasOne(AmapiDevice::class);
    }

    public function lockHistory()
    {
        return $this->hasMany(DeviceLockHistory::class);
    }

    /**
     * Vérifie si l'appareil doit être verrouillé (logique métier)
     */
    public function shouldBeLocked(): bool
    {
        $monitoringService = app(DeviceMonitoringService::class);
        return $monitoringService->shouldDeviceBeLocked($this);
    }

    /**
     * Vérifie si l'appareil est actuellement verrouillé
     */
    public function isLocked(): bool
    {
        return $this->status === 'locked' ||
               $this->amapiDevice?->amapi_state === 'DISABLED';
    }

}
