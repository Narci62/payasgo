<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AmapiSyncLog extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'attempts' => 'integer',
    ];

    public function financingPlan(): BelongsTo
    {
        return $this->belongsTo(Financing_plan::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function markAsSuccess(): void
    {
        $this->update([
            'status' => 'success',
            'error_message' => null,
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
            'attempts' => $this->attempts + 1,
        ]);
    }
}
