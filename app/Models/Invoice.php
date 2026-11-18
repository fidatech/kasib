<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A sale, a purchase, or the return of one. */
class Invoice extends Model
{
    use \App\Models\Concerns\BranchScoped;

    public const SALE = 'sale';

    public const PURCHASE = 'purchase';

    public const SALE_RETURN = 'sale_return';

    public const PURCHASE_RETURN = 'purchase_return';

    /** kind => [name of one, name of the list, prefix of its number, part of its address] */
    public const KINDS = [
        self::SALE => ['Sale invoice', 'Sales', 'SI', 'sales'],
        self::PURCHASE => ['Purchase invoice', 'Purchases', 'PI', 'purchases'],
        self::SALE_RETURN => ['Sale return', 'Sale Returns', 'SR', 'sale-returns'],
        self::PURCHASE_RETURN => ['Purchase return', 'Purchase Returns', 'PR', 'purchase-returns'],
    ];

    protected $guarded = [];

    protected $casts = ['date' => 'date:Y-m-d', 'due_date' => 'date:Y-m-d', 'cancelled_at' => 'datetime'];

    public static function kindOf(string $slug): ?string
    {
        foreach (self::KINDS as $kind => $about) {
            if ($about[3] === $slug) {
                return $kind;
            }
        }

        return null;
    }

    public function slug(): string
    {
        return self::KINDS[$this->kind][3];
    }

    /** Goods leave the stock with a sale and with a return to the supplier. */
    public function goodsLeave(): bool
    {
        return in_array($this->kind, [self::SALE, self::PURCHASE_RETURN], true);
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function paymentVoucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'payment_voucher_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
