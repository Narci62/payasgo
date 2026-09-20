<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Installment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'due_date' => 'date',
        'amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
    ];

    public function financingPlan(): BelongsTo
    {
        return $this->belongsTo(Financing_plan::class);
    }

    public function penalties(): HasMany
    {
        return $this->hasMany(Penalty::class);
    }

    public function isOverdue(): bool
    {
        return $this->status !== 'paid' && Carbon::now()->greaterThan($this->due_date);
    }

    public function getDaysLate(): int
    {
        if (! $this->isOverdue()) {
            return 0;
        }

        return abs(Carbon::now()->diffInDays($this->due_date));
    }

    public function getTotalPenaltiesDue(): float
    {
        return (float) $this->penalties()->sum('amount');
    }

    public function getTotalDue(): float
    {
        return $this->remaining_amount + $this->getTotalPenaltiesDue();
    }
}
