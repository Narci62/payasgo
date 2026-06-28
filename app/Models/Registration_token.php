<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Registration_token extends Model
{
    use SoftDeletes;

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
