<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Garant extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }
}
