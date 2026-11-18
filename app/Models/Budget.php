<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** What an expense category may cost in one month, in the base currency. */
class Budget extends Model
{
    protected $guarded = [];

    protected $casts = ['amount' => 'decimal:2', 'warned_level' => 'integer'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(AccountCategory::class, 'account_category_id');
    }
}
