<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceLockHistory extends Model
{
    protected $guarded = [];

    protected $casts = [
        'executed_at' => 'datetime'
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function financingPlan(): BelongsTo
    {
        return $this->belongsTo(Financing_plan::class);
    }

    public function triggeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    /**
     * Scopes
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'SUCCESS');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'FAILED');
    }

    public function scopeLockActions($query)
    {
        return $query->whereIn('action', ['LOCK', 'LOCK_ATTEMPT']);
    }

    public function scopeUnlockActions($query)
    {
        return $query->whereIn('action', ['UNLOCK', 'UNLOCK_ATTEMPT']);
    }
}
