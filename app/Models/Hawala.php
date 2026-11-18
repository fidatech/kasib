<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Money sent through a saraf: followed by the number the saraf gave until the receiver has it. */
class Hawala extends Model
{
    use \App\Models\Concerns\BranchScoped;

    public const STATUSES = ['sent' => 'On the way', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];

    protected $guarded = [];

    protected $casts = ['date' => 'date:Y-m-d', 'delivered_on' => 'date:Y-m-d', 'amount' => 'decimal:2'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }
}
