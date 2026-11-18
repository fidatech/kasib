<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Deal;
use App\Models\Voucher;
use App\Services\Accounts;
use App\Services\Deals;
use App\Services\Vouchers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Deals: something bought to be sold again. The money paid for it and the money that came in
 * from it are recorded on the deal; its profit or loss is worked out, never typed.
 */
class DealController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('transactions.view');
        $status = in_array($request->query('status'), ['open', 'closed'], true) ? $request->query('status') : null;
        $term = trim((string) $request->query('q', ''));

        $deals = Deal::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$term.'%')->orWhere('number', 'like', '%'.$term.'%')))
            ->orderByRaw("case when status = 'open' then 0 else 1 end")->orderByDesc('id')
            ->paginate(per_page(25))->withQueryString();

        return view('deals.index', [
            'deals' => $deals, 'figures' => $deals->getCollection()->mapWithKeys(fn (Deal $d) => [$d->id => Deals::figures($d)])->all(),
            'status' => $status, 'term' => $term, 'base' => \App\Models\Currency::base()->code,
            'canManage' => $request->user()->can('transactions.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('transactions.manage');
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $deal = Deals::open($data['name'], $data['notes'] ?? null);

        return redirect()->route('deals.show', ['id' => $deal->id])->with('success', __(':name saved.', ['name' => __('Deal')]));
    }

    public function show(Request $request): View
    {
        Gate::authorize('transactions.view');
        $deal = Deal::findOrFail((int) $request->route('id'));

        return view('deals.show', [
            'deal' => $deal, 'figures' => Deals::figures($deal), 'base' => \App\Models\Currency::base()->code,
            'papers' => Voucher::with(['account', 'currency', 'creator'])->where('deal_id', $deal->id)->orderBy('date')->orderBy('id')->get(),
            'money' => Accounts::money(current_branch())->mapWithKeys(fn (Account $a) => [$a->id => $a->label().' · '.$a->currency->code])->all(),
            'cash' => Accounts::cash(current_branch(), \App\Models\Currency::base())->id,
            'canManage' => $request->user()->can('transactions.manage'),
        ]);
    }

    /** Money paid for the deal, or money that came in from it. */
    public function move(Request $request): RedirectResponse
    {
        Gate::authorize('transactions.manage');
        $deal = Deal::findOrFail((int) $request->route('id'));
        $data = $request->validate([
            'kind' => ['required', Rule::in(['cost', 'receipt'])],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'account_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
        $money = Account::with('currency')->findOrFail($data['account_id']);
        $data['kind'] === 'cost'
            ? Vouchers::dealCost($deal, $money, $data['date'], $data['amount'], $data['description'] ?? null)
            : Vouchers::dealReceipt($deal, $money, $data['date'], $data['amount'], $data['description'] ?? null);

        return redirect()->route('deals.show', ['id' => $deal->id])->with('success', __('Saved.'));
    }

    public function cancel(Request $request): RedirectResponse
    {
        Gate::authorize('transactions.manage');
        $voucher = Voucher::where('deal_id', (int) $request->route('id'))->findOrFail((int) $request->route('paper'));
        Vouchers::cancel($voucher, $request->validate(['reason' => ['nullable', 'string', 'max:255']])['reason'] ?? null);

        return back()->with('success', __('Cancelled.'));
    }

    public function close(Request $request): RedirectResponse
    {
        Gate::authorize('transactions.manage');
        $deal = Deals::close(Deal::findOrFail((int) $request->route('id')));

        return redirect()->route('deals.show', ['id' => $deal->id])->with('success', \App\Support\Num::isNegative($deal->result)
            ? __('Deal closed with a loss of :amount.', ['amount' => money(\App\Support\Num::abs($deal->result))])
            : __('Deal closed with a profit of :amount.', ['amount' => money($deal->result)]));
    }
}
