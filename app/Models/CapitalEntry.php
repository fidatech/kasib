<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Capital paid in or taken out (a drawing) by a shareholder. */
class CapitalEntry extends Model
{
    protected $guarded = [];

    public const KINDS = ['contribution' => 'Capital paid in', 'withdrawal' => 'Drawing (capital taken out)'];

    protected $casts = ['amount' => 'decimal:2', 'date' => 'date:Y-m-d', 'cancelled_at' => 'datetime'];

    public function shareholder(): BelongsTo
    {
        return $this->belongsTo(Shareholder::class);
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
