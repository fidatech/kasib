<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Money received that the branch owes, or a payment of what it owes. */
class LiabilityEntry extends Model
{
    protected $guarded = [];

    public const KINDS = ['received' => 'Money received (we owe it)', 'payment' => 'Payment of what we owe'];

    protected $casts = ['amount' => 'decimal:2', 'date' => 'date:Y-m-d', 'cancelled_at' => 'datetime'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function moneyAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'money_account_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }
}
