<?php

namespace App\Services;

use App\Models\AccountGroup;
use App\Models\Alert;
use App\Models\BalanceConfirmation;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Party;
use App\Models\Setting;
use App\Models\Voucher;
use App\Support\Num;

/**
 * What goes to people by itself: the evening summary to the owner, and once a month the balance
 * of every customer and supplier to that party, who is asked to confirm it.
 */
class Summary
{
    public const CONFIRM = "{business}\n{name}, your balance with us on {date}:\n{balance}\nPlease answer this message to confirm it, or tell us what is different.";

    /** One message: the cash per currency, the movement of the day, the exchange profit and what is due tomorrow. */
    public static function evening(): string
    {
        $before = app()->getLocale();
        app()->setLocale(setting('owner_locale') ?: $before);
        [$all, Branch::$everywhere] = [Branch::$everywhere, true];   // the owner is told about the whole business
        try {
            $today = today_iso();
            // every branch that keeps books, one after the other; with one branch its name is left out
            $branches = Branch::withBooks()->where('is_active', true)->orderBy('id')->get();
            $lines = [(setting('company_name') ?: tenant()?->name).' · '.fmt_date($today)];
            foreach ($branches as $branch) {
                $lines[] = ($branches->count() > 1 ? $branch->name.' · ' : '').__('Cash and bank').':';
                foreach (Days::summary($branch, $today)['rows'] as $row) {
                    if (Num::isZero($row['closing']) && Num::isZero($row['in']) && Num::isZero($row['out'])) {
                        continue;
                    }
                    $lines[] = $row['currency']->code.': '.money($row['closing']).' ('.__('in').' '.money($row['in']).', '.__('out').' '.money($row['out']).')';
                }
            }
            $profit = Voucher::where('kind', Voucher::EXCHANGE)->whereNull('cancelled_at')->whereDay('date', $today)->sum('profit');
            $lines[] = __('Exchange profit today').': '.money($profit, Currency::base()->code);

            $due = Voucher::with('currency')->where('kind', Voucher::MONEY_IN)->whereNull('cancelled_at')->whereDay('due_date', now()->addDay()->toDateString())->get();
            $sums = $due->groupBy(fn (Voucher $v) => $v->currency->code)->map(fn ($group, $code) => money(Num::sum($group->pluck('amount')), $code))->implode(', ');
            $lines[] = __('To be paid back tomorrow').': '.$due->count().($sums ? ' ('.$sums.')' : '');
            $lines[] = __('Unusual activity today').': '.Alert::whereDay('created_at', $today)->count();

            return implode("\n", $lines);
        } finally {
            Branch::$everywhere = $all;
            app()->setLocale($before);
        }
    }

    public static function sendEvening(): bool
    {
        return Whatsapp::send(setting('owner_phone'), self::evening());
    }

    /** Called every hour: at the hour the owner chose the summary goes out, once a day. */
    public static function tick(): bool
    {
        if (setting('summary_enabled') !== '1' || (int) setting('summary_hour', '20') !== (int) now()->hour || setting('summary_sent_on') === today_iso()) {
            return false;
        }
        self::remember('summary_sent_on', today_iso());

        return self::sendEvening();
    }

    /**
     * Send every customer and supplier who has a phone and a balance that balance. A party is asked
     * once in a calendar month; one party alone can be asked again at any time.
     */
    public static function confirmBalances(?Party $only = null): int
    {
        if (! Whatsapp::enabled()) {
            return 0;
        }
        $parties = $only ? collect([$only]) : Party::with(['group', 'accounts.total', 'accounts.currency'])->where('is_active', true)->whereNotNull('phone')
            ->whereHas('group', fn ($q) => $q->where('type', AccountGroup::PARTY))->orderBy('id')->limit(5000)->get();
        $asked = $only ? [] : BalanceConfirmation::where('sent_at', '>=', now()->startOfMonth())->pluck('party_id')->all();
        $sent = 0;
        foreach ($parties as $party) {
            $balance = Whatsapp::balance($party);
            if ($balance === '0' || in_array($party->id, $asked) || ! filled($party->phone)) {
                continue;
            }
            $message = strtr((string) (setting('whatsapp_tpl_confirm') ?: self::CONFIRM), [
                '{business}' => (string) (setting('company_name') ?: tenant()?->name), '{name}' => $party->name, '{date}' => fmt_date(today_iso()), '{balance}' => $balance,
            ]);
            if (Whatsapp::send($party->phone, $message)) {
                BalanceConfirmation::create(['party_id' => $party->id, 'balance' => mb_substr($balance, 0, 255), 'status' => 'sent', 'sent_at' => now()]);
                $sent++;
            }
        }

        return $sent;
    }

    /** Called every night: on the day of the month the owner chose, everybody is asked. */
    public static function monthly(): int
    {
        $day = (int) setting('confirm_day', '0');
        if ($day < 1 || (int) now()->day !== $day || setting('confirm_sent_month') === now()->format('Y-m')) {
            return 0;
        }
        self::remember('confirm_sent_month', now()->format('Y-m'));

        return self::confirmBalances();
    }

    public static function remember(string $key, ?string $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        app()->forgetInstance('erp.settings');
    }
}
