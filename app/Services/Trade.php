<?php

namespace App\Services;

use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\AccountGroup;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\Party;
use App\Support\Num;
use Illuminate\Support\Facades\DB;

/**
 * Sales and purchases (version 2, the Pro plan).
 *
 * A sale: the customer owes the total, the sales income rises, and the goods leave the stock at
 * their average cost, which becomes the cost of goods sold. A purchase: the supplier is owed the
 * total and the goods enter the stock at what they cost, which moves the average. A return turns
 * the invoice around. What is paid on the spot is an ordinary Money In or Money Out paper, so the
 * cash, the receipts and the statements work as everywhere else.
 */
class Trade
{
    /** key of the system account => [group of the chart, name, Dari name, nature] */
    private const ACCOUNTS = [
        'inventory' => ['inventory', 'Inventory', 'موجودی اجناس', 'asset'],
        'sales_income' => ['sales_income', 'Sales', 'فروشات', 'income'],
        'cogs' => ['cost_of_goods', 'Cost of goods sold', 'قیمت تمام‌شده اجناس فروخته‌شده', 'expense'],
    ];

    public static function account(string $key): Account
    {
        [$category, $name, $local, $nature] = self::ACCOUNTS[$key];
        // a business opened before version 2 gets the group the first time it is needed
        AccountCategory::firstOrCreate(['key' => $category], ['name' => $name, 'name_local' => $local, 'nature' => $nature, 'is_system' => true, 'sort' => 900]);

        return Accounts::open(current_branch(), $key, $category, $name);
    }

    /** The customer of a sale over the counter, to nobody in particular. */
    public static function walkIn(): Party
    {
        $group = AccountGroup::byKey('customers');

        return Party::firstOrCreate(['account_group_id' => $group->id, 'name' => 'Cash customer', 'is_system' => true])->fresh();
    }

    /**
     * @param  array<int, array{item_id: int|string, qty: mixed, price: mixed, description?: ?string}>  $lines
     */
    public static function record(string $kind, Party $party, Currency $currency, string $date, array $lines, mixed $discount = 0, mixed $paid = 0, ?Account $money = null, ?string $due = null, ?string $note = null): Invoice
    {
        if (! isset(Invoice::KINDS[$kind])) {
            throw new UserError(__('Unknown kind of invoice.'));
        }
        $party->loadMissing('group');
        if ($party->group->isMoney()) {
            throw new UserError(__('Choose a customer or a supplier.'));
        }
        if ($party->is_active === false) {
            throw new UserError(__(':name is switched off.', ['name' => $party->name]));
        }
        if (! $lines) {
            throw new UserError(__('Add at least one item.'));
        }
        $discount = Num::money($discount ?: 0);
        $paid = Num::money($paid ?: 0);
        if (Num::isNegative($discount) || Num::isNegative($paid)) {
            throw new UserError(__('app.errors.negative_amount'));
        }

        return DB::transaction(function () use ($kind, $party, $currency, $date, $lines, $discount, $paid, $money, $due, $note) {
            $branch = current_branch();
            $rows = [];
            $subtotal = '0';
            foreach ($lines as $line) {
                $item = Item::lockForUpdate()->find((int) $line['item_id']);
                if (! $item || ! $item->is_active) {
                    throw new UserError(__('Choose an item that is in use.'));
                }
                $qty = Num::round($line['qty'], 3);
                $price = Num::money($line['price']);
                if (! Num::isPositive($qty) || Num::isNegative($price)) {
                    throw new UserError(__('The quantity of :item must be more than zero.', ['item' => $item->name]));
                }
                $amount = Num::money(Num::mul($qty, $price));
                $subtotal = Num::add($subtotal, $amount);
                $rows[] = ['item' => $item, 'qty' => $qty, 'price' => $price, 'amount' => $amount, 'description' => $line['description'] ?? null];
            }
            $subtotal = Num::money($subtotal);
            $total = Num::money(Num::sub($subtotal, $discount));
            if (! Num::isPositive($total)) {
                throw new UserError(__('The total of the invoice must be more than zero.'));
            }
            if (Num::cmp($paid, $total) === 1) {
                throw new UserError(__('What is paid cannot be more than the total.'));
            }
            if ($party->is_system && Num::cmp($paid, $total) !== 0) {
                throw new UserError(__('A cash customer pays in full. For a sale on credit choose the customer.'));
            }
            $baseTotal = Rates::toBase($total, $currency, $branch);

            // every line's share of the total after the discount, in the base currency; the last one takes what rounding left
            $left = $baseTotal;
            foreach ($rows as $i => &$row) {
                $share = $i === count($rows) - 1 ? $left : Num::money(Num::div(Num::mul($baseTotal, $row['amount']), $subtotal));
                $row['base'] = $share;
                $left = Num::sub($left, $share);
            }
            unset($row);

            $invoice = Invoice::create([
                'number' => Sequence::next(Invoice::KINDS[$kind][2]), 'kind' => $kind, 'date' => $date, 'party_id' => $party->id, 'currency_id' => $currency->id,
                'subtotal' => $subtotal, 'discount' => $discount, 'total' => $total, 'base_total' => $baseTotal, 'paid' => $paid,
                'due_date' => $due, 'note' => $note, 'created_by' => auth()->id(),
            ]);

            // the stock: what leaves goes at the average cost; what a purchase brings in moves the average
            $leaves = $invoice->goodsLeave();
            $stockValue = '0';   // base value of the lines whose stock is counted
            $otherValue = '0';   // base value of the services
            $cost = '0';         // cost that left or came back at the average
            foreach ($rows as $row) {
                $item = $row['item'];
                $lineCost = '0';
                $held = $item->track_stock ? ItemStock::of($item, $branch) : null;
                if (! $item->track_stock) {
                    $otherValue = Num::add($otherValue, $row['base']);
                } else {
                    $stockValue = Num::add($stockValue, $row['base']);
                    if ($leaves) {
                        if (Num::cmp($held->stock, $row['qty']) === -1 && setting('negative_stock') !== '1') {
                            throw new UserError(__('Not enough :item in stock: :have, needed :need.', ['item' => $item->name, 'have' => qty($held->stock), 'need' => qty($row['qty'])]));
                        }
                        $lineCost = Num::money(Num::mul($row['qty'], $held->avg_cost));
                        $held->stock = Num::round(Num::sub($held->stock, $row['qty']), 3);
                    } elseif ($kind === Invoice::PURCHASE) {
                        $lineCost = $row['base'];
                        $before = Num::isPositive($held->stock) ? Num::mul($held->stock, $held->avg_cost) : '0';
                        $newStock = Num::round(Num::add(Num::isPositive($held->stock) ? $held->stock : '0', $row['qty']), 3);
                        $held->avg_cost = Num::round(Num::div(Num::add($before, $lineCost), $newStock), 4);
                        $held->stock = Num::round(Num::add($held->stock, $row['qty']), 3);
                    } else {   // a sale comes back: into the stock at the average of now
                        $lineCost = Num::money(Num::mul($row['qty'], Num::isPositive($held->avg_cost) ? $held->avg_cost : $item->cost_price));
                        if (! Num::isPositive($held->avg_cost)) {
                            $held->avg_cost = Num::round($item->cost_price, 4);
                        }
                        $held->stock = Num::round(Num::add($held->stock, $row['qty']), 3);
                    }
                    $held->save();
                    $cost = Num::add($cost, $lineCost);
                }
                $invoice->lines()->create(['item_id' => $item->id, 'description' => $row['description'], 'qty' => $row['qty'], 'price' => $row['price'], 'amount' => $row['amount'], 'base_amount' => $row['base'], 'base_cost' => $lineCost]);
            }
            $cost = Num::money($cost);
            $invoice->update(['cost_total' => $cost]);

            $account = Accounts::party($party, $currency, $branch);
            $inventory = self::account('inventory');
            $sales = self::account('sales_income');
            $cogs = self::account('cogs');
            $entry = match ($kind) {
                Invoice::SALE => [
                    ['account' => $account, 'debit' => $total, 'base' => $baseTotal], ['account' => $sales, 'credit' => $baseTotal],
                    ['account' => $cogs, 'debit' => $cost], ['account' => $inventory, 'credit' => $cost],
                ],
                Invoice::PURCHASE => [
                    ['account' => $inventory, 'debit' => $stockValue], ['account' => $cogs, 'debit' => $otherValue],
                    ['account' => $account, 'credit' => $total, 'base' => $baseTotal],
                ],
                Invoice::SALE_RETURN => [
                    ['account' => $sales, 'debit' => $baseTotal], ['account' => $account, 'credit' => $total, 'base' => $baseTotal],
                    ['account' => $inventory, 'debit' => $cost], ['account' => $cogs, 'credit' => $cost],
                ],
                // back to the supplier: the stock gives up its average cost; what the supplier credits above or below it is cost of goods
                Invoice::PURCHASE_RETURN => array_merge(
                    [['account' => $account, 'debit' => $total, 'base' => $baseTotal], ['account' => $inventory, 'credit' => $cost], ['account' => $cogs, 'credit' => $otherValue]],
                    [Num::cmp($stockValue, $cost) >= 0 ? ['account' => $cogs, 'credit' => Num::sub($stockValue, $cost)] : ['account' => $cogs, 'debit' => Num::sub($cost, $stockValue)]],
                ),
            };
            $entry = array_values(array_filter($entry, fn (array $line) => Num::isPositive(Num::money($line['debit'] ?? $line['credit']))));
            Ledger::post($branch, $kind, $date, trim($invoice->number.' · '.$party->name.($note ? ' · '.$note : '')), $entry, $invoice);
            audit('created', $invoice, Invoice::KINDS[$kind][0].' '.$invoice->number.' '.$total.' '.$currency->code);
            Alerts::entry(fn () => __(Invoice::KINDS[$kind][0]).' '.$invoice->number.' · '.money($total, $currency->code), $date, $baseTotal, $invoice);

            // on credit: the customer may not come to owe more than his limit
            if ($kind === Invoice::SALE && Num::cmp($paid, $total) === -1 && Num::isPositive($party->credit_limit ?? 0)) {
                $owes = Num::money(Num::sub(DB::table('account_balances')->whereIn('account_id', $party->accounts()->pluck('id'))->sum('base_balance'), Rates::toBase($paid, $currency, $branch)));
                if (Num::cmp($owes, $party->credit_limit) === 1) {
                    throw new UserError(__(':name would owe :amount, more than the credit limit of :limit.', ['name' => $party->name, 'amount' => money($owes), 'limit' => money($party->credit_limit)]));
                }
            }

            // what is paid on the spot: money in for a sale and for goods sent back to a supplier, money out for the other two
            if (Num::isPositive($paid)) {
                if (! $money || (int) $money->currency_id !== (int) $currency->id) {
                    throw new UserError(__('Choose a cash, bank or saraf account in :currency for what is paid.', ['currency' => $currency->code]));
                }
                $text = __(Invoice::KINDS[$kind][0]).' '.$invoice->number;
                $voucher = in_array($kind, [Invoice::SALE, Invoice::PURCHASE_RETURN], true)
                    ? Vouchers::moneyIn($party, $money, $date, $paid, null, $text)
                    : Vouchers::moneyOut($party, $money, $date, $paid, $text);
                $invoice->update(['payment_voucher_id' => $voucher->id]);
            }

            return $invoice->fresh(['lines.item', 'party', 'currency']);
        });
    }

    /** An invoice is never deleted: it is cancelled, the goods go back where they were and its payment is cancelled with it. */
    public static function cancel(Invoice $invoice, ?string $reason = null): void
    {
        if ($invoice->isCancelled()) {
            return;
        }
        DB::transaction(function () use ($invoice, $reason) {
            $invoice->load('lines');
            foreach ($invoice->lines as $line) {
                $item = Item::lockForUpdate()->find($line->item_id);
                if (! $item || ! $item->track_stock) {
                    continue;
                }
                $held = ItemStock::of($item, \App\Models\Branch::findOrFail($invoice->branch_id));
                if ($invoice->goodsLeave()) {
                    $held->stock = Num::round(Num::add($held->stock, $line->qty), 3);   // what left comes back
                } else {
                    if (Num::cmp($held->stock, $line->qty) === -1 && setting('negative_stock') !== '1') {
                        throw new UserError(__(':item of this invoice is already sold; it cannot be cancelled.', ['item' => $item->name]));
                    }
                    $newStock = Num::round(Num::sub($held->stock, $line->qty), 3);
                    if ($invoice->kind === Invoice::PURCHASE && Num::isPositive($newStock)) {
                        // the average without this purchase
                        $value = Num::sub(Num::mul($held->stock, $held->avg_cost), $line->base_cost);
                        $held->avg_cost = Num::isPositive($value) ? Num::round(Num::div($value, $newStock), 4) : $held->avg_cost;
                    }
                    $held->stock = $newStock;
                }
                $held->save();
            }
            $invoice->update(['cancelled_at' => now(), 'cancel_reason' => $reason ? mb_substr($reason, 0, 255) : null]);
            Ledger::voidSource($invoice, $reason);
            if ($invoice->paymentVoucher) {
                Vouchers::cancel($invoice->paymentVoucher, trim($invoice->number.' '.($reason ?? '')));
            }
            audit('cancelled', $invoice, trim($invoice->number.' '.($reason ?? '')));
            Alerts::raise('cancelled', trim($invoice->number.' · '.money($invoice->total, $invoice->currency?->code).' · '.($reason ?? ''), ' ·'), $invoice);
        });
    }

    /**
     * Stock counted or corrected by hand. More than the books: it enters at the unit cost given,
     * against the opening balances. Less: it leaves at the average, as cost of goods.
     */
    public static function adjust(Item $item, mixed $counted, mixed $unitCost = null, ?string $note = null): void
    {
        if (! $item->track_stock) {
            throw new UserError(__('The stock of a service is not counted.'));
        }
        $counted = Num::round($counted, 3);
        if (Num::isNegative($counted)) {
            throw new UserError(__('app.errors.negative_amount'));
        }
        DB::transaction(function () use ($item, $counted, $unitCost, $note) {
            $branch = current_branch();
            $held = ItemStock::of($item, $branch);
            $diff = Num::round(Num::sub($counted, $held->stock), 3);
            if (Num::isZero($diff)) {
                return;
            }
            $text = trim(__('Stock count').' · '.$item->name.' · '.qty($held->stock).' → '.qty($counted).($note ? ' · '.$note : ''));
            if (Num::isPositive($diff)) {
                $cost = Num::round(Num::isPositive($unitCost ?: 0) ? $unitCost : (Num::isPositive($held->avg_cost) ? $held->avg_cost : $item->cost_price), 4);
                $value = Num::money(Num::mul($diff, $cost));
                $before = Num::isPositive($held->stock) ? Num::mul($held->stock, $held->avg_cost) : '0';
                $held->avg_cost = Num::round(Num::div(Num::add($before, $value), $counted), 4);
                if (Num::isPositive($value)) {
                    Ledger::post($branch, 'stock', today_iso(), $text, [['account' => self::account('inventory'), 'debit' => $value], ['account' => Accounts::system($branch, 'opening'), 'credit' => $value]], $item);
                }
            } else {
                $value = Num::money(Num::mul(Num::abs($diff), $held->avg_cost));
                if (Num::isPositive($value)) {
                    Ledger::post($branch, 'stock', today_iso(), $text, [['account' => self::account('cogs'), 'debit' => $value], ['account' => self::account('inventory'), 'credit' => $value]], $item);
                }
            }
            $held->stock = $counted;
            $held->save();
            audit('updated', null, $text);
        });
    }
}
