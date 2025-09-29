<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Device extends Model
{
    /** @use HasFactory<\Database\Factories\DeviceFactory> */
    use HasFactory, HasApiTokens, Notifiable;

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
}
