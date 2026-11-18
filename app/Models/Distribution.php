<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Distribution extends Model
{
    protected $guarded = [];

    protected $casts = ['date' => 'date:Y-m-d', 'amount' => 'decimal:2', 'shares' => 'array', 'cancelled_at' => 'datetime'];

    public function paidAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'paid_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function auditLabel(): string
    {
        return $this->number;
    }
}
