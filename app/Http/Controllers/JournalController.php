<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\User;
use App\Support\Num;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Roznamcha: the general ledger of a branch. Every line that any screen has posted, to be
 * filtered by dates, account, kind of transaction, currency and user, and searched by anything.
 * Nothing is typed in here: entries come from the transactions themselves.
 */
class JournalController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('journal.view');

        $f = $request->validate([
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'q' => ['nullable', 'string', 'max:100'],
            'account' => ['nullable', 'integer'], 'type' => ['nullable', 'string', 'max:40'], 'currency' => ['nullable', 'integer'],
            'user' => ['nullable', 'integer'], 'cancelled' => ['nullable', 'boolean'],
        ]);
        $from = $f['from'] ?? today_iso();
        $to = $f['to'] ?? today_iso();
        $branch = viewed_branch();
        $term = trim((string) ($f['q'] ?? ''));
        $number = is_numeric(str_replace(',', '', $term)) ? str_replace(',', '', $term) : null;

        $lines = JournalLine::with(['account.currency', 'entry.creator'])
            ->where('journal_lines.branch_id', $branch?->id)
            ->whereDayOf('journal_lines.date', '>=', $from)->whereDayOf('journal_lines.date', '<=', $to)
            ->when(empty($f['cancelled']), fn ($q) => $q->where('is_void', false))
            ->when($f['account'] ?? null, fn ($q, $id) => $q->where('account_id', $id))
            ->when($f['currency'] ?? null, fn ($q, $id) => $q->where('currency_id', $id))
            ->when($f['type'] ?? null, fn ($q, $type) => $q->whereHas('entry', fn ($e) => $e->where('type', $type)))
            ->when($f['user'] ?? null, fn ($q, $id) => $q->whereHas('entry', fn ($e) => $e->where('created_by', $id)))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->whereHas('entry', fn ($e) => $e->where('number', 'like', "%$term%")->orWhere('description', 'like', "%$term%")->orWhere('type', 'like', "%$term%")
                    ->orWhereHas('creator', fn ($u) => $u->where('name', 'like', "%$term%")))
                ->orWhereHas('account', fn ($a) => $a->where('name', 'like', "%$term%")->orWhere('code', 'like', "%$term%"))
                ->when($number !== null, fn ($n) => $n->orWhere('debit', $number)->orWhere('credit', $number))))
            ->orderBy('date')->orderBy('journal_entry_id')->orderBy('id');

        $totals = (clone $lines)->reorder()->where('is_void', false)->selectRaw('COALESCE(SUM(base_debit),0) as debit, COALESCE(SUM(base_credit),0) as credit')->toBase()->first();

        return view('journal.index', [
            'lines' => $lines->paginate(per_page(100))->withQueryString(),
            'from' => $from, 'to' => $to, 'term' => $term, 'f' => $f, 'branch' => $branch,
            'branches' => can_oversee() ? Branch::withBooks()->orderBy('id')->pluck('name', 'id')->all() : [],
            'accounts' => Account::with('currency')->where('branch_id', $branch?->id)->orderBy('name')->get(),
            'types' => cache()->remember('journal-types:'.tenant()?->id.':'.$branch?->id, 600, fn () => JournalEntry::where('branch_id', $branch?->id)->distinct()->orderBy('type')->pluck('type')->all()),   // a plain list: the cache does not bring objects back
            'currencies' => Currency::orderByDesc('is_base')->orderBy('code')->get(),
            'users' => User::orderBy('name')->pluck('name', 'id'),
            'totals' => ['debit' => Num::money($totals->debit ?? 0), 'credit' => Num::money($totals->credit ?? 0)],
            'balances' => $branch ? $this->balances($branch, $from, $to, $f['account'] ?? null) : [],
            'oneDay' => $from === $to && $from === today_iso(),
            // one day on the screen: whether that day is open or closed and locked
            'day' => null,
            'single' => $from === $to,
        ]);
    }

    /**
     * Balance before the first day on the screen, balance at the end of the last day, and the
     * difference: of the chosen account, or of cash and banks currency by currency.
     */
    private function balances(Branch $branch, string $from, string $to, ?int $accountId): array
    {
        $accounts = Account::with(['currency', 'category'])->where('branch_id', $branch->id)
            ->when($accountId, fn ($q) => $q->where('id', $accountId), fn ($q) => $q->whereIn('account_category_id', \App\Models\AccountCategory::moneyIds()))->get();
        if ($accounts->isEmpty()) {
            return [];
        }
        $sum = fn (string $operator, string $date) => JournalLine::whereIn('account_id', $accounts->pluck('id'))->where('is_void', false)->whereDayOf('date', $operator, $date)
            ->groupBy('currency_id')->selectRaw('currency_id, COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as amount')->toBase()->pluck('amount', 'currency_id');
        if ($to >= today_iso() && ! JournalLine::whereIn('account_id', $accounts->pluck('id'))->where('date', '>', $to.' 23:59:59')->exists()) {
            // up to today: what every account holds is already known, and only the days shown are added up
            $after = \Illuminate\Support\Facades\DB::table('account_balances as b')->join('accounts as a', 'a.id', '=', 'b.account_id')->whereIn('a.id', $accounts->pluck('id'))
                ->groupBy('a.currency_id')->selectRaw('a.currency_id as currency_id, COALESCE(SUM(b.balance), 0) as amount')->pluck('amount', 'currency_id');
            $moved = JournalLine::whereIn('account_id', $accounts->pluck('id'))->where('is_void', false)->where('date', '>=', $from)
                ->groupBy('currency_id')->selectRaw('currency_id, COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as amount')->toBase()->pluck('amount', 'currency_id');
            $before = $after->map(fn ($amount, $currency) => Num::sub($amount, $moved[$currency] ?? '0'));
        } else {
            $before = $sum('<', $from);
            $after = $sum('<=', $to);
        }

        $rows = [];
        foreach ($accounts->pluck('currency')->unique('id')->sortByDesc('is_base') as $currency) {
            $was = Num::money($before[$currency->id] ?? '0');
            $is = Num::money($after[$currency->id] ?? '0');
            if (Num::isZero($was) && Num::isZero($is)) {
                continue;
            }
            $rows[] = ['currency' => $currency, 'before' => $was, 'after' => $is, 'difference' => Num::money(Num::sub($is, $was))];
        }

        return $rows;
    }
}
