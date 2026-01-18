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
        'last_command_sent_at' => 'datetime',
        'last_amapi_sync_at' => 'datetime',
        'amapi_metadata' => 'array'
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
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
        if (!$this->last_amapi_sync_at) {
            return false;
        }

        return $this->last_amapi_sync_at->diffInHours(now()) < 24;
    }
}
