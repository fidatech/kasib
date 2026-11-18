<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The cash of one box counted by hand, and how far it was from the books. */
class CashCount extends Model
{
    use \App\Models\Concerns\BranchScoped;

    protected $guarded = [];

    protected $casts = ['date' => 'date:Y-m-d', 'cancelled_at' => 'datetime', 'book' => 'decimal:2', 'counted' => 'decimal:2', 'difference' => 'decimal:2'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
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
