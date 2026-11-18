<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A paper of the business itself, kept in the vault: a licence, a contract, an identity card. */
class Document extends Model
{
    public const CATEGORIES = ['licence' => 'Licence', 'contract' => 'Contract', 'identity' => 'Identity paper', 'bank' => 'Bank', 'tax' => 'Tax', 'other' => 'Other'];

    protected $guarded = [];

    protected $casts = ['expires_on' => 'date:Y-m-d', 'warned_at' => 'datetime', 'size' => 'integer'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
