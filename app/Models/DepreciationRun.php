<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** The depreciation of one month in one branch. */
class DepreciationRun extends Model
{
    protected $guarded = [];

    protected $casts = ['total' => 'decimal:2', 'date' => 'date:Y-m-d', 'cancelled_at' => 'datetime', 'is_auto' => 'boolean'];

    /** The one asset the paper is for; empty when it is for every asset of the branch. */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DepreciationLine::class);
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
