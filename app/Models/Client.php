<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Services\ClientService;

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

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($client) {
            // Génère une référence unique du type 51014
            $reference = (new ClientService)->generateIdentifiantClient();
            $client->reference = $reference;
        });
    }


}
