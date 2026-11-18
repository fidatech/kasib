<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

/** The fingerprint of one backup file the system made for a business. */
class BackupSeal extends Model
{
    use UsesCentralConnection;

    public const UPDATED_AT = null;

    protected $guarded = [];
}
