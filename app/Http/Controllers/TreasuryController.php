<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\Branch;
use App\Models\Currency;
use App\Services\Days;
use App\Support\Num;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The treasury: how much of every currency the business holds now, and where it is
 * (cash, bank, saraf and any other category of money accounts).
 */
class TreasuryController extends Controller
{
    public function index(): View
    {
        Gate::authorize('accounts.view');
        $branch = current_branch();
        $totals = Days::totals($branch);
        $groups = AccountGroup::active()->where('type', AccountGroup::MONEY)->orderBy('sort')->get();
        $accounts = Account::with(['total', 'party'])->where('branch_id', $branch->id)->whereNotNull('party_id')
            ->whereHas('party', fn ($q) => $q->whereIn('account_group_id', $groups->pluck('id')))->get();

        $rows = [];
        $worth = '0';
        foreach (Currency::active()->orderBy('sort')->get() as $currency) {
            $total = $totals[$currency->id] ?? null;
            $split = [];
            foreach ($groups as $group) {
                $split[$group->id] = Num::sum($accounts->where('currency_id', $currency->id)->filter(fn (Account $a) => (int) $a->party->account_group_id === (int) $group->id)->map(fn (Account $a) => $a->total?->balance ?? 0));
            }
            $rows[] = ['currency' => $currency, 'split' => $split, 'balance' => $total['balance'] ?? '0.00', 'rate' => $total['rate'] ?? null, 'base' => $total['base'] ?? '0.00'];
            $worth = Num::add($worth, $total['base'] ?? 0);
        }

        return view('treasury.index', ['groups' => $groups, 'rows' => $rows, 'worth' => Num::money($worth), 'base' => Currency::base()->code]);
    }
}
