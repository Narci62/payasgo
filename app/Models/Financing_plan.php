<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Financing_plan extends Model
{
    protected $guarded = [];

    public function payments() : HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function registrationToken() : BelongsTo
    {
        return $this->belongsTo(Registration_token::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
