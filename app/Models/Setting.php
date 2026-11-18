<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One setting of the business, kept as key and value. */
class Setting extends Model
{
    /** What a new business starts with. Every figure can be changed on the Settings page. */
    public const DEFAULTS = [
        'calendar' => 'shamsi',   // dates are shown in the Shamsi calendar until the business chooses otherwise
    ];

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $guarded = [];
}
