<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AmapiDevice extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'amapi_released_at' => 'datetime',
        'last_command_sent_at' => 'datetime',
        'last_amapi_sync_at' => 'datetime',
        'amapi_metadata' => 'array',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Vérifie si l'appareil a été enrôlé en mode COPE (Android Management API
     * épinglé). Les appareils antérieurs sont en Fully Managed.
     */
    public function isCopeEnrolled(): bool
    {
        return $this->enrollment_mode === 'COPE';
    }

    /**
     * Indique qu'une libération a été effectuée avec succès côté AMAPI :
     * suppression pour un appareil Fully Managed, abandon de propriété pour
     * un appareil COPE.
     */
    public function isReleased(): bool
    {
        return $this->amapi_released_at !== null || $this->amapi_state === 'LIBERATED';
    }

    /**
     * Vérifie si l'appareil est actuellement verrouillé
     */
    public function isLocked(): bool
    {
        return $this->amapi_state === 'DISABLED';
    }

    /**
     * Vérifie si l'appareil est actif
     */
    public function isActive(): bool
    {
        return $this->amapi_state === 'ACTIVE';
    }

    /**
     * Vérifie si le dernier sync est récent (moins de 24h)
     */
    public function hasRecentSync(): bool
    {
        if (! $this->last_amapi_sync_at) {
            return false;
        }

        return $this->last_amapi_sync_at->diffInHours(now()) < 24;
    }

    /**
     * Ajouté un amapi_device_id par defaut aleatoire unique au moment de la création du modèle
     */
    protected static function booted()
    {
        static::creating(function ($amapiDevice) {
            if (empty($amapiDevice->amapi_device_id)) {
                do {
                    $amapiDevice->amapi_device_id = 'amapi_'.bin2hex(random_bytes(8));
                } while (self::where('amapi_device_id', $amapiDevice->amapi_device_id)->exists());
            }
        });
    }
}
