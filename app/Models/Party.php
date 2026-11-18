<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One account of an account category, as people know it: a cash box, a bank account, a saraf,
 * a customer, a supplier. It holds a separate balance in every currency it uses: behind it
 * there is one ledger account per currency.
 */
class Party extends Model
{
    use Audited;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_system' => 'boolean', 'is_active' => 'boolean'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(AccountGroup::class, 'account_group_id');
    }

    /** Its ledger accounts, one per currency. */
    /** Its ledger accounts in the branch one works in (in all of them inside Branch::everywhere). */
    public function accounts(): HasMany
    {
        $relation = $this->hasMany(Account::class);
        if (! Branch::$everywhere && ($branch = current_branch())) {
            $relation->where('accounts.branch_id', $branch->id);
        }

        return $relation;
    }

    /** Its ledger accounts in every branch. */
    public function allAccounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function label(): string
    {
        return $this->name;
    }

    public function auditLabel(): string
    {
        return $this->name;
    }
}
