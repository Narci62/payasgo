<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $guarded = [];

    public function devices() : HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function registrationTokens() : HasMany
    {
        return $this->hasMany(Registration_token::class);
    }

}
