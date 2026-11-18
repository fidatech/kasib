<?php

namespace App\Http\Controllers;

use App\Models\BalanceConfirmation;
use App\Services\Summary;
use App\Services\Whatsapp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Balance confirmations: who was sent his balance, and who agreed with it. */
class ConfirmationController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('accounts.view');
        $status = $request->query('status');

        return view('confirmations.index', [
            'rows' => BalanceConfirmation::with('party.group')->when(isset(BalanceConfirmation::STATUSES[$status]), fn ($q) => $q->where('status', $status))->orderByDesc('id')->paginate(per_page())->withQueryString(),
            'statuses' => BalanceConfirmation::STATUSES,
            'canManage' => $request->user()->can('accounts.manage'),
        ]);
    }

    /** Ask everybody who was not asked this month. */
    public function send(): RedirectResponse
    {
        Gate::authorize('accounts.manage');
        if (! Whatsapp::enabled()) {
            return back()->with('error', __('Switch the WhatsApp messages on first.'));
        }

        return back()->with('success', __(':count balances were sent.', ['count' => Summary::confirmBalances()]));
    }

    /** The party answered: by phone, on WhatsApp or at the counter. */
    public function answer(Request $request): RedirectResponse
    {
        Gate::authorize('accounts.manage');
        $data = $request->validate(['status' => ['required', 'in:confirmed,disputed'], 'note' => ['nullable', 'string', 'max:255']]);
        $row = BalanceConfirmation::with('party')->findOrFail((int) $request->route('id'));
        $row->update($data + ['answered_at' => now(), 'answered_by' => $request->user()->id]);
        audit('updated', $row->party, 'Balance '.$data['status'].': '.$row->balance);

        return back()->with('success', __('Saved.'));
    }
}
