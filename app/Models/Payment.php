<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public function financingPlan(): BelongsTo
    {
        return $this->belongsTo(Financing_plan::class);
    }
}
