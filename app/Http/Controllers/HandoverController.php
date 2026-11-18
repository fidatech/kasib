<?php

namespace App\Http\Controllers;

use App\Exceptions\UserError;
use App\Models\AccountGroup;
use App\Models\Branch;
use App\Models\Handover;
use App\Models\Party;
use App\Models\User;
use App\Services\Accounts;
use App\Services\Vouchers;
use App\Support\Num;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * A cash box for every cashier, and the handover at the end of a shift: the cashier counts
 * his box, the counted money moves to the main box (or to the next cashier), and what the
 * count differs from the books is written down for the owner.
 */
class HandoverController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('transactions.view');
        $boxes = $this->boxes();
        $mine = $boxes->firstWhere('user_id', $request->user()->id);
        $from = $boxes->firstWhere('id', (int) $request->query('box')) ?? $mine ?? $boxes->first();

        return view('handovers.index', [
            'boxes' => $boxes, 'from' => $from, 'mine' => $mine,
            'balances' => $from ? $from->accounts->filter(fn ($a) => ! Num::isZero($a->total?->balance ?? 0))->values() : collect(),
            'handovers' => Handover::with(['from', 'to', 'user', 'acceptedBy'])->orderByDesc('id')->paginate(per_page()),
            'users' => User::where('is_active', true)->orderBy('name')->get(),
            'canManage' => $request->user()->can('transactions.manage'),
            'canAssign' => $request->user()->can('users.manage'),
        ]);
    }

    /** Which user holds which cash box. */
    public function assign(Request $request): RedirectResponse
    {
        Gate::authorize('users.manage');
        $data = $request->validate(['holder' => ['array'], 'holder.*' => ['nullable', 'integer', 'exists:users,id']]);
        foreach ($this->boxes() as $box) {
            if (array_key_exists($box->id, $data['holder'] ?? [])) {
                $box->update(['user_id' => $data['holder'][$box->id] ?: null]);
            }
        }
        audit('updated', null, 'Cash boxes of the users');

        return back()->with('success', __('Saved.'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('transactions.manage');
        $data = $request->validate([
            'from' => ['required', 'integer'], 'to' => ['required', 'integer', 'different:from'],
            'counted' => ['required', 'array'], 'counted.*' => ['nullable', 'numeric', 'min:0'], 'note' => ['nullable', 'string', 'max:500'],
        ]);
        $boxes = $this->boxes();
        $from = $boxes->firstWhere('id', (int) $data['from']) ?? abort(404);
        $to = $boxes->firstWhere('id', (int) $data['to']) ?? abort(404);
        // a cashier hands over his own box; those who manage users may hand over any
        if ((int) $from->user_id !== (int) $request->user()->id && ! $request->user()->can('users.manage')) {
            throw new UserError(__('You can hand over only your own cash box.'));
        }

        $handover = DB::transaction(function () use ($from, $to, $data, $request) {
            $lines = [];
            foreach ($from->accounts as $account) {
                $book = Num::money($account->total?->balance ?? 0);
                $typed = $data['counted'][$account->id] ?? null;
                if ($typed === null || $typed === '') {
                    continue;
                }
                $counted = Num::money($typed);
                if (Num::isPositive($counted)) {
                    Vouchers::transfer($account, Accounts::party($to, $account->currency), today_iso(), $counted, 0, __('Handover').': '.$from->name.' → '.$to->name);
                }
                $lines[] = ['currency' => $account->currency->code, 'book' => $book, 'counted' => $counted, 'difference' => Num::money(Num::sub($counted, $book))];
            }
            if (! $lines) {
                throw new UserError(__('Write what was counted in at least one currency.'));
            }

            return Handover::create(['date' => today_iso(), 'from_party_id' => $from->id, 'to_party_id' => $to->id, 'lines' => $lines, 'note' => $data['note'] ?? null, 'user_id' => $request->user()->id]);
        });
        audit('created', null, 'Handover '.$from->name.' → '.$to->name);
        if (collect($handover->lines)->contains(fn ($line) => ! Num::isZero($line['difference']))) {
            \App\Services\Alerts::raise('cash', $from->name.': '.collect($handover->lines)->filter(fn ($l) => ! Num::isZero($l['difference']))->map(fn ($l) => money($l['difference'], $l['currency']))->implode(', '), $handover);
        }

        return redirect()->route('handovers.index')->with('success', __('The handover was recorded.'));
    }

    /** The one who received the money says he has it. */
    public function accept(Request $request): RedirectResponse
    {
        Gate::authorize('transactions.manage');
        $handover = Handover::with('to')->whereNull('accepted_at')->findOrFail((int) $request->route('id'));
        if ((int) $handover->user_id === (int) $request->user()->id && ! $request->user()->can('users.manage')) {
            throw new UserError(__('The one who receives the money accepts it, not the one who hands it over.'));
        }
        $handover->update(['accepted_by' => $request->user()->id, 'accepted_at' => now()]);

        return back()->with('success', __('Saved.'));
    }

    /** The cash boxes: the accounts of the category "Cash". */
    private function boxes()
    {
        Accounts::ensureCash(Branch::main());

        return Party::with(['accounts.total', 'accounts.currency'])->where('is_active', true)
            ->where('account_group_id', AccountGroup::byKey('cash')->id)->orderBy('id')->get();
    }
}
