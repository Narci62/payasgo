<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Financing_plan extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function registrationToken(): BelongsTo
    {
        return $this->belongsTo(Registration_token::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function amapiSyncLogs(): HasMany
    {
        return $this->hasMany(AmapiSyncLog::class);
    }

    // get "soldé" for paid_in_full status attribute
    public function getStatusAttribute($value)
    {
        switch ($value) {
            case 'paid_in_full':
                $value = 'soldé';
                break;
            case 'active':
                $value = 'actif';
                break;

            default:
                $value = 'En attente';
                break;
        }

        return $value;
    }
}
