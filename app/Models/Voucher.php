<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Money in, money out, a transfer, an exchange or an income: one paper, one journal entry. */
class Voucher extends Model
{
    public const MONEY_IN = 'money_in';

    public const MONEY_OUT = 'money_out';

    public const TRANSFER = 'transfer';

    public const EXCHANGE = 'exchange';

    public const INCOME = 'income';

    /** kind => [name, prefix of its number] */
    public const KINDS = [
        self::MONEY_IN => ['Money In', 'MI'],
        self::MONEY_OUT => ['Money Out', 'MO'],
        self::TRANSFER => ['Transfer', 'TR'],
        self::EXCHANGE => ['Exchange', 'EX'],
        self::INCOME => ['Income', 'IN'],
    ];

    protected $guarded = [];

    protected $casts = [
        'date' => 'date:Y-m-d', 'due_date' => 'date:Y-m-d', 'cancelled_at' => 'datetime',
        'amount' => 'decimal:2', 'other_amount' => 'decimal:2', 'fee' => 'decimal:2', 'base_amount' => 'decimal:2', 'profit' => 'decimal:2',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /** The money account: received into, or paid from. */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function otherAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'other_account_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function otherCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'other_currency_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function auditLabel(): string
    {
        return $this->number;
    }
}
