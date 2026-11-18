<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\PartyNote;
use App\Models\RecurringExpense;
use App\Models\Voucher;
use App\Support\Shamsi;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The calendar: one month on a page, in the calendar of the business, with what falls on each
 * day: money to be paid back, reminders, recurring expenses and papers of the vault that expire.
 */
class CalendarController extends Controller
{
    private const SHAMSI = ['حمل', 'ثور', 'جوزا', 'سرطان', 'اسد', 'سنبله', 'میزان', 'عقرب', 'قوس', 'جدی', 'دلو', 'حوت'];

    public function index(Request $request): View
    {
        Gate::authorize('reports.view');
        $offset = max(-120, min(120, (int) $request->query('month', 0)));
        [$first, $last, $title] = $this->month($offset);

        $events = [];
        $add = function (string $date, string $kind, string $text, ?string $url = null) use (&$events) {
            $events[$date][] = ['kind' => $kind, 'text' => $text, 'url' => $url];
        };
        foreach (Voucher::with(['party.group', 'currency'])->where('kind', Voucher::MONEY_IN)->whereNull('cancelled_at')->whereDate('due_date', '>=', $first)->whereDate('due_date', '<=', $last)->orderBy('due_date')->limit(2000)->get() as $voucher) {
            $add($voucher->due_date->toDateString(), 'due', $voucher->party?->name.' · '.money($voucher->amount, $voucher->currency->code),
                $voucher->party?->group ? route('accounts.statement', ['group' => $voucher->party->group->key, 'id' => $voucher->party->id, 'currency' => $voucher->currency_id]) : null);
        }
        if ($request->user()->can('accounts.view')) {
            foreach (PartyNote::with('party')->whereNull('done_at')->whereDate('remind_on', '>=', $first)->whereDate('remind_on', '<=', $last)->limit(2000)->get() as $note) {
                $add(substr((string) $note->remind_on, 0, 10), 'reminder', $note->party?->name.': '.$note->note, route('reminders.index'));
            }
        }
        if ($request->user()->can('expenses.view')) {
            $recurring = RecurringExpense::with(['category', 'paidAccount.currency'])->where('is_active', true)->get();
            for ($day = Carbon::parse($first); $day->toDateString() <= $last; $day->addDay()) {
                foreach ($recurring->where('day', $day->day) as $expense) {
                    $add($day->toDateString(), 'expense', $expense->category->label().' · '.money($expense->amount, $expense->paidAccount->currency->code), route('recurring-expenses.index'));
                }
            }
        }
        if ($request->user()->can('settings.view')) {
            foreach (Document::whereDate('expires_on', '>=', $first)->whereDate('expires_on', '<=', $last)->get() as $document) {
                $add($document->expires_on->toDateString(), 'document', $document->title, route('documents.index'));
            }
        }

        // weeks start on Saturday
        $days = [];
        $lead = (Carbon::parse($first)->dayOfWeek + 1) % 7;
        for ($i = 0; $i < $lead; $i++) {
            $days[] = null;
        }
        for ($day = Carbon::parse($first); $day->toDateString() <= $last; $day->addDay()) {
            $iso = $day->toDateString();
            $days[] = ['date' => $iso, 'number' => calendar() === 'shamsi' ? Shamsi::fromGregorian($day->year, $day->month, $day->day)[2] : $day->day, 'today' => $iso === today_iso(), 'events' => $events[$iso] ?? []];
        }

        return view('calendar.index', ['weeks' => array_chunk($days, 7), 'title' => $title, 'offset' => $offset,
            'kinds' => ['due' => 'To be paid back', 'reminder' => 'Reminder', 'expense' => 'Recurring expense', 'document' => 'Document expires'],
            'weekdays' => ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']]);
    }

    /** @return array{0: string, 1: string, 2: string} the first and the last day of the month, and its name */
    private function month(int $offset): array
    {
        $today = Carbon::parse(today_iso());
        if (calendar() !== 'shamsi') {
            $month = $today->copy()->startOfMonth()->addMonthsNoOverflow($offset);

            return [$month->toDateString(), $month->copy()->endOfMonth()->toDateString(), $month->format('F Y')];
        }
        [$y, $m] = Shamsi::fromGregorian($today->year, $today->month, $today->day);
        $index = ($y * 12 + ($m - 1)) + $offset;
        [$y, $m] = [intdiv($index, 12), $index % 12 + 1];
        $first = fn (int $y, int $m) => vsprintf('%04d-%02d-%02d', Shamsi::toGregorian($y, $m, 1));
        $next = $m === 12 ? $first($y + 1, 1) : $first($y, $m + 1);

        return [$first($y, $m), Carbon::parse($next)->subDay()->toDateString(), self::SHAMSI[$m - 1].' '.$y];
    }
}
