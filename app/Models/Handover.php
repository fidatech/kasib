<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A cash box counted and handed to another box. */
class Handover extends Model
{
    protected $guarded = [];

    protected $casts = ['date' => 'date:Y-m-d', 'lines' => 'array', 'accepted_at' => 'datetime'];

    public function from(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'from_party_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'to_party_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }
}
