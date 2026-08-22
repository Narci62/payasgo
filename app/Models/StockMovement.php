<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockMovement extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public function phone()
    {
        return $this->belongsTo(Phone::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
