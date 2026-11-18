<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Branch;
use App\Models\JournalLine;
use App\Services\Accounts;
use App\Support\Num;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Bank reconciliation, the simple way: the lines of a bank (or saraf) account are ticked as they
 * are found on the statement. What is ticked should add up to the balance the bank shows; what
 * is not ticked is still on its way.
 */
class ReconciliationController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('accounts.view');
        $f = $request->validate(['account' => ['nullable', 'integer'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'statement' => ['nullable', 'numeric'], 'show' => ['nullable', 'in:all,open']]);
        $accounts = Accounts::money(current_branch());
        $account = $accounts->firstWhere('id', (int) ($f['account'] ?? 0));
        [$from, $to] = [$f['from'] ?? now()->startOfMonth()->toDateString(), $f['to'] ?? today_iso()];

        $lines = collect();
        $figures = null;
        if ($account) {
            $cleared = DB::table('cleared_lines')->pluck('journal_line_id')->flip();
            $lines = JournalLine::with('entry')->where('account_id', $account->id)->where('is_void', false)
                ->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)->orderBy('date')->orderBy('id')->limit(3000)->get()
                ->each(fn (JournalLine $line) => $line->setAttribute('is_cleared', $cleared->has($line->id)))
                ->when(($f['show'] ?? 'all') === 'open', fn ($all) => $all->where('is_cleared', false)->values());

            $sum = fn ($query) => Num::money((clone $query)->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as total')->value('total') ?? 0);
            $upTo = JournalLine::where('account_id', $account->id)->where('is_void', false)->whereDate('date', '<=', $to);
            $book = $sum($upTo);
            $ticked = $sum((clone $upTo)->whereIn('id', DB::table('cleared_lines')->select('journal_line_id')));
            $figures = ['book' => $book, 'cleared' => $ticked, 'open' => Num::money(Num::sub($book, $ticked)),
                'difference' => isset($f['statement']) ? Num::money(Num::sub($f['statement'], $ticked)) : null];
        }

        return view('reconciliation.index', [
            'accounts' => $accounts, 'account' => $account, 'from' => $from, 'to' => $to, 'lines' => $lines, 'figures' => $figures,
            'statement' => $f['statement'] ?? null, 'show' => $f['show'] ?? 'all', 'canManage' => $request->user()->can('accounts.manage'),
        ]);
    }

    /** Save the ticks of the lines that were on the page. */
    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('accounts.manage');
        $data = $request->validate(['account' => ['required', 'integer'], 'shown' => ['array', 'max:3000'], 'shown.*' => ['integer'], 'cleared' => ['array', 'max:3000'], 'cleared.*' => ['integer']]);
        $account = Accounts::money(current_branch())->firstWhere('id', (int) $data['account']) ?? abort(404);
        // only lines of this account that were shown can change
        $shown = JournalLine::where('account_id', $account->id)->where('is_void', false)->whereIn('id', $data['shown'] ?? [])->pluck('id')->all();
        $ticked = array_values(array_intersect($shown, array_map('intval', $data['cleared'] ?? [])));

        DB::transaction(function () use ($shown, $ticked, $request) {
            DB::table('cleared_lines')->whereIn('journal_line_id', array_diff($shown, $ticked))->delete();
            $have = DB::table('cleared_lines')->whereIn('journal_line_id', $ticked)->pluck('journal_line_id')->all();
            foreach (array_diff($ticked, $have) as $id) {
                DB::table('cleared_lines')->insert(['journal_line_id' => $id, 'user_id' => $request->user()->id, 'cleared_at' => now()]);
            }
        });
        audit('updated', null, 'Reconciliation '.$account->name.': '.count($ticked).' / '.count($shown));

        return back()->with('success', __('Saved. :count lines are ticked.', ['count' => count($ticked)]));
    }
}
