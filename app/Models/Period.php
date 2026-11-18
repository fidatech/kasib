<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Period extends Model
{
    protected $guarded = [];

    protected $casts = ['started_on' => 'date:Y-m-d', 'closed_on' => 'date:Y-m-d', 'branches' => 'array'];

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
