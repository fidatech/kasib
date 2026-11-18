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
        'money_in' => "{business}\nReceived from {name}: {amount} {currency}\nNo. {number} · {date}\nYour balance with us: {balance}",
        'money_out' => "{business}\nPaid to {name}: {amount} {currency}\nNo. {number} · {date}\nYour balance with us: {balance}",
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

    /** The receipt of money in or money out, to the party of the paper. */
    public static function paper(Voucher $voucher): bool
    {
        if (! self::enabled() || ! in_array($voucher->kind, [Voucher::MONEY_IN, Voucher::MONEY_OUT], true)) {
            return false;
        }
        $voucher->loadMissing(['party', 'currency']);

        return self::send($voucher->party?->phone, self::fill(self::template($voucher->kind), $voucher));
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
            ->whereNotNull('due_date')->whereDate('due_date', '<=', now()->addDay()->toDateString())->orderBy('id')->limit(500)->get();
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

    private static function fill(string $template, Voucher $voucher): string
    {
        $party = $voucher->party;

        return strtr($template, [
            '{business}' => (string) (setting('company_name') ?: tenant()?->name),
            '{name}' => (string) $party?->name,
            '{amount}' => money($voucher->amount),
            '{currency}' => (string) $voucher->currency?->code,
            '{number}' => $voucher->number,
            '{date}' => fmt_date($voucher->date),
            '{due}' => fmt_date($voucher->due_date),
            '{balance}' => $party ? self::balance($party) : '',
        ]);
    }

    /** "5,000.00 AFN, -200.00 USD": above zero the business owes the party. */
    private static function balance(Party $party): string
    {
        $parts = array_map(fn (array $row) => money($row['amount'], $row['currency']->code), Vouchers::balances($party->fresh()));

        return $parts ? implode(', ', $parts) : '0';
    }
}
