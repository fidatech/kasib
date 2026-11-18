<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A photo or a PDF kept with an account or with a paper. The file is outside the public folder. */
class Attachment extends Model
{
    public const UPDATED_AT = null;

    public const SUBJECTS = ['party' => Party::class, 'voucher' => Voucher::class];

    protected $guarded = [];

    protected $casts = ['created_at' => 'datetime', 'size' => 'integer'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
