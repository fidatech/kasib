<?php

namespace App\Services;

use App\Models\Party;
use App\Models\Voucher;
use App\Support\Num;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Messages to customers and suppliers through the WhatsApp sender of the business: a receipt
 * after money in or money out, and a reminder before a payback date. The address, the key and
 * the names of the two fields of the sender are written under Configuration. A message that
 * cannot be sent is written in the log and never stops the work at the counter.
 */
class Whatsapp
{
    public const TEMPLATES = [
        'money_in' => "{business}\nReceived from {name}: {amount} {currency}\nNo. {number} · {date}\nYour balance with us:\n{balance}",
        'money_out' => "{business}\nPaid to {name}: {amount} {currency}\nNo. {number} · {date}\nYour balance with us:\n{balance}",
        // every other paper on the account of a customer, a supplier or a saraf: a transfer, an exchange, an invoice
        'paper' => "{business}\n{name}: {what}\n{amount} {currency}\nNo. {number} · {date}\nYour balance with us:\n{balance}",
        'due' => "{business}\n{name}, the payback date of {amount} {currency} (No. {number}) is {due}.",
    ];

    public static function enabled(): bool
    {
        return setting('whatsapp_enabled') === '1' && filled(setting('whatsapp_url'));
    }

    /** 0700123456 becomes 93700123456; anything that is not a digit is dropped. */
    public static function phone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (strlen($digits) === 10 && $digits[0] === '0') {
            $digits = setting('whatsapp_country', '93').substr($digits, 1);
        }

        return strlen($digits) >= 9 ? $digits : null;
    }

    public static function send(?string $phone, string $message): bool
    {
        $to = self::phone($phone);
        if (! self::enabled() || ! $to || trim($message) === '') {
            return false;
        }
        try {
            $request = Http::timeout(6)->acceptJson();
            if (filled(setting('whatsapp_token'))) {
                $request = $request->withToken((string) setting('whatsapp_token'));
            }
            $response = $request->post((string) setting('whatsapp_url'), [
                setting('whatsapp_phone_field', 'number') => $to,
                setting('whatsapp_message_field', 'message') => $message,
            ]);
            if (! $response->successful()) {
                Log::warning('WhatsApp message not sent: HTTP '.$response->status());
            }

            return $response->successful();
        } catch (Throwable $e) {
            Log::warning('WhatsApp message not sent: '.$e->getMessage());

            return false;
        }
    }

    /** While true no paper sends its own message (an invoice sends one for itself and the payment on it). */
    public static bool $quiet = false;

    /** Called when a paper is made: its message leaves once the paper is safely in the books. */
    public static function afterPaper(Voucher $voucher): void
    {
        if (self::$quiet || ! self::enabled()) {
            return;
        }
        \Illuminate\Support\Facades\DB::afterCommit(fn () => self::paper($voucher));
    }

    /**
     * The message of a paper to everybody on it who has a phone: the customer or supplier, and a
     * saraf or bank whose account the money went into or came out of. It ends with the balance of
     * every currency the account keeps. Returns how many were sent.
     */
    public static function paper(Voucher $voucher): int
    {
        if (! self::enabled()) {
            return 0;
        }
        $voucher->loadMissing(['party.group', 'currency', 'account.party.group', 'otherAccount.party.group']);
        $sent = 0;
        foreach (self::partiesOf($voucher) as $party) {
            $kind = $voucher->party_id === $party->id && in_array($voucher->kind, [Voucher::MONEY_IN, Voucher::MONEY_OUT], true) ? $voucher->kind : 'paper';
            $sent += (int) self::send($party->phone, self::fill(self::template($kind), $voucher, $party));
        }

        return $sent;
    }

    /** The message of an invoice, with what was paid on it, to its customer or supplier. */
    public static function invoice(\App\Models\Invoice $invoice): bool
    {
        if (! self::enabled() || ! $invoice->party || ! filled($invoice->party->phone)) {
            return false;
        }
        $invoice->loadMissing(['party.group', 'currency', 'paymentVoucher']);
        $paid = $invoice->paymentVoucher ? "\n".__('Paid').': '.money($invoice->paymentVoucher->amount).' '.$invoice->currency?->code : '';

        return self::send($invoice->party->phone, strtr(self::template('paper'), [
            '{business}' => self::business(), '{name}' => $invoice->party->name, '{what}' => __(\App\Models\Invoice::KINDS[$invoice->kind][0] ?? 'Invoice'),
            '{amount}' => money($invoice->total), '{currency}' => (string) $invoice->currency?->code.$paid, '{number}' => $invoice->number,
            '{date}' => fmt_date($invoice->date), '{due}' => '', '{balance}' => self::balance($invoice->party),
        ]));
    }

    /**
     * The statement of one currency of an account, as a message: the balance it opened with, every
     * line (the last :max of them when there are more), and the balance it closed with.
     *
     * @param  array<int, array{line: \App\Models\JournalLine, balance: string}>  $rows
     */
    public static function statement(Party $party, \App\Models\Account $account, string $from, string $to, string $opening, array $rows, string $closing, int $max = 40): string
    {
        $party->loadMissing('group');
        $code = $account->currency->code;
        $sign = $party->group->isMoney() ? '-1' : '1';           // read from their side: + is what we owe them
        $side = fn (string $amount) => Num::isZero($amount) ? '' : ' '.(Num::isPositive(Num::mul($amount, $sign)) ? __('we owe you') : __('you owe us'));
        $out = [self::business(), __('Statement of :name', ['name' => $party->name]).' · '.$code, fmt_date($from).' – '.fmt_date($to), '',
            __('Opening balance').': '.money(Num::abs($opening)).$side($opening)];
        if (count($rows) > $max) {
            $out[] = __('(the last :count of :all lines)', ['count' => $max, 'all' => count($rows)]);
        }
        foreach (array_slice($rows, -$max) as $row) {
            $line = $row['line'];
            $moved = Num::sub($line->debit, $line->credit);
            $out[] = fmt_date($line->date).' · '.($line->entry?->number ?? '').' · '.(Num::isPositive($moved) ? '+' : '−').money(Num::abs($moved))
                .' → '.money(Num::abs($row['balance'])).($line->entry?->description ? "\n   ".mb_substr((string) $line->entry->description, 0, 70) : '');
        }
        $out[] = '';
        $out[] = __('Closing balance').': '.money(Num::abs($closing)).' '.$code.$side($closing);
        $out[] = '';
        $out[] = __('Balance of every currency').':';
        $out[] = self::balance($party);

        return implode("\n", $out);
    }

    /** The address that opens WhatsApp with a message written, for a business without its own sender. */
    public static function link(?string $phone, string $message): ?string
    {
        $to = self::phone($phone);

        return $to ? 'https://wa.me/'.$to.'?text='.rawurlencode($message) : null;
    }

    /** @return array<int, Party> the parties of a paper that have a phone, the cash box left out */
    private static function partiesOf(Voucher $voucher): array
    {
        $found = [];
        foreach ([$voucher->party, $voucher->account?->party, $voucher->otherAccount?->party] as $party) {
            if ($party && ! $party->is_system && filled($party->phone)) {
                $found[$party->id] = $party;
            }
        }

        return array_values($found);
    }

    private static function business(): string
    {
        return (string) (setting('company_name') ?: tenant()?->name);
    }

    /**
     * Tell the parties whose payback date is tomorrow or earlier, once for each paper, as long as
     * the business still owes them in that currency. Returns how many were told.
     */
    public static function remindDue(): int
    {
        if (! self::enabled()) {
            return 0;
        }
        $sent = 0;
        $papers = Voucher::with(['party.accounts.total', 'currency'])->where('kind', Voucher::MONEY_IN)->whereNull('cancelled_at')->whereNull('reminded_at')
            ->whereNotNull('due_date')->whereDayOf('due_date', '<=', now()->addDay()->toDateString())->orderBy('id')->limit(500)->get();
        foreach ($papers as $voucher) {
            $account = $voucher->party?->accounts->firstWhere('currency_id', $voucher->currency_id);
            if (! $voucher->party?->phone || ! Num::isPositive(Num::neg($account?->total?->balance ?? 0))) {
                continue;
            }
            if (self::send($voucher->party->phone, self::fill(self::template('due'), $voucher))) {
                $voucher->update(['reminded_at' => now()]);
                $sent++;
            }
        }

        return $sent;
    }

    public static function template(string $kind): string
    {
        return (string) (setting('whatsapp_tpl_'.$kind) ?: self::TEMPLATES[$kind]);
    }

    private static function fill(string $template, Voucher $voucher, ?Party $party = null): string
    {
        $party ??= $voucher->party;

        return strtr($template, [
            '{business}' => self::business(),
            '{what}' => __(Voucher::KINDS[$voucher->kind][0] ?? $voucher->kind).($voucher->description ? ' · '.$voucher->description : ''),
            '{name}' => (string) $party?->name,
            '{amount}' => money($voucher->amount),
            '{currency}' => (string) $voucher->currency?->code,
            '{number}' => $voucher->number,
            '{date}' => fmt_date($voucher->date),
            '{due}' => fmt_date($voucher->due_date),
            '{balance}' => $party ? self::balance($party) : '',
        ]);
    }

    /**
     * The final balance of every currency of an account, one line each, with which side owes:
     * "• AFN 5,000.00 we owe you" / "• USD 200.00 you owe us". All branches are added up.
     */
    public static function balance(Party $party): string
    {
        $party->loadMissing('group');
        $money = $party->group?->isMoney();
        $sums = [];
        foreach ($party->allAccounts()->with(['total', 'currency'])->get()->sortBy(fn ($a) => [$a->currency?->sort, $a->currency_id]) as $account) {
            // read from their side: above zero the business owes them
            $sums[$account->currency->code] = Num::add($sums[$account->currency->code] ?? 0, Num::neg($account->total?->balance ?? 0));
        }
        $lines = [];
        foreach ($sums as $code => $amount) {
            $amount = Num::money($amount);
            if (! Num::isZero($amount)) {
                $lines[] = '• '.$code.' '.money(Num::abs($amount)).' '.(Num::isPositive($amount) ? __('we owe you') : __('you owe us'));
            }
        }

        return $lines ? implode("\n", $lines) : '• '.__('Settled, nothing is owed');
    }

    /** Whether a text made by balance() says that nothing is owed. */
    public static function settled(string $balance): bool
    {
        return $balance === '• '.__('Settled, nothing is owed');
    }
}
