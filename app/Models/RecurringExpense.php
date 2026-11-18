<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An expense that comes every month. The system records it on its day, by itself. */
class RecurringExpense extends Model
{
    use Audited;

    protected $guarded = [];

    protected $casts = ['amount' => 'decimal:2', 'is_active' => 'boolean', 'last_posted_on' => 'date:Y-m-d', 'day' => 'integer'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(AccountCategory::class, 'account_category_id');
    }

    public function paidAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'paid_account_id');
    }

    public function auditLabel(): string
    {
        return (string) ($this->description ?: $this->category?->name);
    }
}
