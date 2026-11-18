<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Something bought to be sold again. The goods themselves are not kept in the system: only
 * what was paid, what came in, and the profit or loss when the deal is closed.
 */
class Deal extends Model
{
    use \App\Models\Concerns\BranchScoped;

    protected $guarded = [];

    protected $casts = ['opened_on' => 'date:Y-m-d', 'closed_on' => 'date:Y-m-d', 'result' => 'decimal:2'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function auditLabel(): string
    {
        return $this->number.' '.$this->name;
    }
}
