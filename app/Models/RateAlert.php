<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** "Tell me when this currency goes above (or below) this rate." It speaks once and switches itself off. */
class RateAlert extends Model
{
    public const DIRECTIONS = ['above' => 'Reaches or goes above', 'below' => 'Reaches or goes below'];

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean', 'fired_at' => 'datetime'];

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
