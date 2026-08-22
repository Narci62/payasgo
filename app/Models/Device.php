<?php

namespace App\Models;

use App\Services\DeviceMonitoringService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class Device extends Model
{
    /** @use HasFactory<\Database\Factories\DeviceFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $guarded = [];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function phone(): BelongsTo
    {
        return $this->belongsTo(Phone::class);
    }

    public function financingPlan(): HasOne
    {
        return $this->hasOne(Financing_plan::class);
    }

    public function registrationToken(): HasOne
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
        // return $this->fcm_token;
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

    /**
     * Vérifie si l'appareil est libéré du contrôle AMAPI
     * (plan payé + plus de device AMAPI associé)
     */
    public function isLiberated(): bool
    {
        return $this->isFullyPaid() && ! $this->amapiDevice;
    }

    /**
     * Vérifie si le plan de financement est entièrement payé
     */
    public function isFullyPaid(): bool
    {
        $plan = $this->financingPlan;

        if (! $plan) {
            return false;
        }

        // Utiliser getRawOriginal car l'accessor transforme 'paid_in_full' en 'soldé'
        return $plan->getRawOriginal('status') === 'paid_in_full' &&
               ($plan->remaining_balance ?? 0) == 0;
    }
}
