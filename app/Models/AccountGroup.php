<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An account category as the people of a business see it: Cash, Bank, Saraf, Customers,
 * Suppliers, or one they added. Every category has its own page under Accounts in the menu.
 *   money  the business's own funds: listed where money is paid from and received into
 *   party  someone the business owes or is owed by
 */
class AccountGroup extends Model
{
    use Audited;

    public const MONEY = 'money';

    public const PARTY = 'party';

    public const TYPES = [self::MONEY => 'Money account (cash, bank, saraf)', self::PARTY => 'Party account (customer, supplier)'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_system' => 'boolean', 'is_active' => 'boolean'];
    }

    public function chart(): BelongsTo
    {
        return $this->belongsTo(AccountCategory::class, 'account_category_id');
    }

    public function parties(): HasMany
    {
        return $this->hasMany(Party::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isMoney(): bool
    {
        return $this->type === self::MONEY;
    }

    public static function byKey(string $key): self
    {
        return static::where('key', $key)->firstOrFail();
    }

    /** Name in the language of the screen. */
    public function label(): string
    {
        return match (app()->getLocale()) {
            'fa' => $this->name_fa ?: ($this->name_ps ?: __($this->name)),
            'ps' => $this->name_ps ?: ($this->name_fa ?: __($this->name)),
            default => __($this->name),
        };
    }
}
