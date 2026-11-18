<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Notice;
use App\Models\User;
use App\Support\Num;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Unusual activity. The owner is told, under the bell and (when asked for) on WhatsApp, about a
 * very large paper, a paper dated back, one made outside working hours, a cancelled one, and an
 * expense budget that is nearly used up. The limits are written under "Alerts & Summary".
 */
class Alerts
{
    public const KINDS = [
        'large' => 'Large amount', 'backdated' => 'Dated back', 'after_hours' => 'Outside working hours',
        'cancelled' => 'Cancelled', 'budget' => 'Expense budget', 'rate' => 'Exchange rate', 'cash' => 'Cash differs from the books',
    ];

    public static function enabled(): bool
    {
        return setting('alerts_enabled', '1') === '1';
    }

    /** A paper was just written: is anything about it unusual? $text is only built when something is. */
    public static function entry(Closure $text, string $date, mixed $base, ?Model $subject = null): void
    {
        if (! self::enabled()) {
            return;
        }
        $large = setting('alert_large');
        if (Num::isPositive($large ?: 0) && Num::cmp(Num::abs($base), $large) >= 0) {
            self::raise('large', $text(), $subject);
        }
        $days = (int) setting('alert_backdate', '2');
        if ($days > 0 && substr($date, 0, 10) < now()->subDays($days)->toDateString()) {
            self::raise('backdated', $text().' · '.fmt_date($date), $subject);
        }
        $from = setting('alert_from');
        $to = setting('alert_to');
        if (auth()->check() && filled($from) && filled($to) && (now()->hour < (int) $from || now()->hour >= (int) $to)) {
            self::raise('after_hours', $text().' · '.now()->format('H:i'), $subject);
        }
    }

    /** A rate was written: tell those who were waiting for it, once. */
    public static function rate(\App\Models\ExchangeRate $written, \App\Models\Currency $currency): void
    {
        foreach (\App\Models\RateAlert::where('currency_id', $currency->id)->where('is_active', true)->get() as $alert) {
            $side = Num::cmp($written->rate, $alert->rate);
            if (($alert->direction === 'above' && $side >= 0) || ($alert->direction === 'below' && $side <= 0)) {
                $alert->update(['is_active' => false, 'fired_at' => now()]);
                self::raise('rate', $currency->code.' '.Num::format($written->rate, 6, true).' '.($alert->direction === 'above' ? '≥' : '≤').' '.Num::format($alert->rate, 6, true).($alert->note ? ' · '.$alert->note : ''), $alert, true);
            }
        }
    }

    public static function raise(string $kind, string $text, ?Model $subject = null, bool $always = false): void
    {
        if (! $always && ! self::enabled()) {
            return;
        }
        $text = mb_substr($text, 0, 255);
        Alert::create(['kind' => $kind, 'text' => $text, 'subject_type' => $subject?->getMorphClass(), 'subject_id' => $subject?->getKey(), 'user_id' => auth()->id()]);

        $title = __(self::KINDS[$kind]).': '.$text;
        $by = auth()->user()?->name;
        foreach (User::role(User::ADMIN_ROLE)->when(! $always, fn ($q) => $q->where('id', '!=', (int) auth()->id()))->where('is_active', true)->pluck('id') as $id) {
            Notice::send((int) $id, $title, $by, route('reports.alerts', [], false));
        }
        if (setting('alerts_whatsapp') === '1' && filled(setting('owner_phone'))) {
            $message = (setting('company_name') ?: tenant()?->name)."\n".$title.($by ? "\n".$by : '');
            DB::afterCommit(fn () => Whatsapp::send(setting('owner_phone'), $message));
        }
    }
}
