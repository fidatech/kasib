<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A share of a payment owed to a seller, or the reward of a client who brought another client. */
class Commission extends Model
{
    use UsesCentralConnection;

    protected $guarded = [];

    protected $casts = ['paid_on' => 'date:Y-m-d', 'cancelled_at' => 'datetime'];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'referrer_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
