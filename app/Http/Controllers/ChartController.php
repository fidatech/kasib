<?php

namespace App\Http\Controllers;

use App\Models\AccountCategory;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** The chart of accounts: every group under its nature, with the total its accounts hold. */
class ChartController extends Controller
{
    public function index(): View
    {
        Gate::authorize('accounts.view');

        $counts = \App\Models\Account::query()
            ->selectRaw('account_category_id, COUNT(*) as n')->groupBy('account_category_id')->pluck('n', 'account_category_id');
        // debits minus credits of every group, in the base currency
        $totals = \Illuminate\Support\Facades\DB::table('account_balances as b')->join('accounts as a', 'a.id', '=', 'b.account_id')
            ->selectRaw('a.account_category_id as id, SUM(b.base_balance) as total')->groupBy('a.account_category_id')->pluck('total', 'id');

        return view('chart.index', [
            'groups' => AccountCategory::orderBy('sort')->get()->groupBy('nature'),
            'counts' => $counts,
            'totals' => $totals,
            'base' => \App\Models\Currency::base()->code,
        ]);
    }
}
