<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Something the branch owns and uses for years: furniture, a computer, a licence fee, software. */
class FixedAsset extends Model
{
    protected $guarded = [];

    public const KINDS = ['tangible' => 'Tangible', 'intangible' => 'Intangible'];

    protected $casts = ['cost' => 'decimal:2', 'salvage_value' => 'decimal:2', 'depreciated' => 'decimal:2', 'purchase_date' => 'date:Y-m-d', 'cancelled_at' => 'datetime', 'auto_depreciate' => 'boolean', 'useful_life_months' => 'integer'];

    public function paidFrom(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'paid_from_account_id');
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
