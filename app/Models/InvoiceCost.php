<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A cost of a purchase (customs, transport, rent ...) and the money account it was paid from. */
class InvoiceCost extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public const NAMES = ['Customs duty', 'Transport', 'Loading and unloading', 'Warehouse rent', 'Clearing agent', 'Commission', 'Packing', 'Other costs'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
