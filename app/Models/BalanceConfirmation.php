<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The balance a party was sent, and the answer. */
class BalanceConfirmation extends Model
{
    public const STATUSES = ['sent' => 'Waiting for an answer', 'confirmed' => 'Confirmed', 'disputed' => 'Not agreed'];

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['sent_at' => 'datetime', 'answered_at' => 'datetime'];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
