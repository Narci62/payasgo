<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Registration_token extends Model
{
    protected $guarded = [];

    public function device() : BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function client() : BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
