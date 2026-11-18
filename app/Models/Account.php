<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** One ledger account, in one currency. A cash box, a bank account or a customer has one for every currency it uses. */
class Account extends Model
{
    use Audited;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'meta' => 'array'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AccountCategory::class, 'account_category_id');
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    /** The running balance, kept by the ledger. */
    public function total(): HasOne
    {
        return $this->hasOne(AccountBalance::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isSystem(): bool
    {
        return $this->system_key !== null;
    }

    public function label(): string
    {
        return (text_dir() === 'rtl' ? $this->name_local : null) ?: $this->name;
    }
}
