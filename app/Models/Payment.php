<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $guarded = [];

    public function financingPlan() : BelongsTo
    {
        return $this->belongsTo(Financing_plan::class);
    }
}
