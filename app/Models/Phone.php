<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Phone extends Model
{
    protected $guarded = [];

    public function device()
    {
        return $this->hasMany(Device::class);
    }
}
