<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Something said with a customer or a supplier, and the day the business wants to be reminded of it. */
class PartyNote extends Model
{
    protected $guarded = [];

    protected $casts = ['remind_on' => 'date:Y-m-d', 'done_at' => 'datetime'];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Notes that are not done and whose day has come. */
    public function scopeDue($query)
    {
        return $query->whereNull('done_at')->whereNotNull('remind_on')->whereDayOf('remind_on', '<=', today_iso());
    }
}
