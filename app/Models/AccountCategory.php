<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A group of the chart of accounts, under one of the five natures. */
class AccountCategory extends Model
{
    use Audited;

    public const NATURES = ['asset' => 'Assets', 'liability' => 'Liabilities', 'equity' => 'Capital', 'income' => 'Income', 'expense' => 'Expenses'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public static function byKey(string $key): self
    {
        return static::where('key', $key)->firstOrFail();
    }

    /** Name in the language of the screen. */
    public function label(): string
    {
        return (text_dir() === 'rtl' ? $this->name_local : null) ?: __($this->name);
    }

    /** Chart groups whose accounts are money of the business: cash, bank, saraf and any category of that type. */
    public static function moneyIds(): array
    {
        return AccountGroup::where('type', AccountGroup::MONEY)->pluck('account_category_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }
}
