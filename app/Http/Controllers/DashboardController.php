<?php

namespace App\Http\Controllers;

use App\Models\AccountGroup;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Party;
use App\Models\Voucher;
use App\Services\Days;
use App\Services\Vouchers;
use App\Support\Num;
use Illuminate\View\View;

/** The first page: the money of the business, what it owes and is owed, what is due, and today's papers. */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = auth()->user();
        $seesMoney = $user->can('accounts.view');
        $seesPapers = $user->can('transactions.view');

        $money = [];
        $worth = '0';
        if ($seesMoney) {
            foreach (Days::totals(current_branch()) as $row) {
                if ($row['currency']->is_base || ! Num::isZero($row['balance'])) {
                    $money[] = $row;
                }
                $worth = Num::add($worth, $row['base']);
            }
        }

        // per currency: what the business owes its parties, and what they owe it
        $owed = [];
        if ($seesMoney) {
            $parties = Party::with(['group', 'accounts.total', 'accounts.currency'])
                ->whereIn('account_group_id', AccountGroup::where('type', AccountGroup::PARTY)->pluck('id'))->limit(5000)->get();
            foreach ($parties as $party) {
                foreach (Vouchers::balances($party) as $balance) {
                    $code = $balance['currency']->code;
                    $owed[$code] ??= ['we' => '0', 'they' => '0'];
                    $side = Num::isPositive($balance['amount']) ? 'we' : 'they';
                    $owed[$code][$side] = Num::add($owed[$code][$side], Num::abs($balance['amount']));
                }
            }
            ksort($owed);
        }

        $today = ['in' => 0, 'out' => 0, 'exchanges' => 0, 'profit' => '0'];
        $recent = collect();
        $due = 0;
        if ($seesPapers) {
            $papers = Voucher::whereNull('cancelled_at')->whereDate('date', today_iso())->get(['kind', 'profit']);
            $today = [
                'in' => $papers->where('kind', Voucher::MONEY_IN)->count(),
                'out' => $papers->where('kind', Voucher::MONEY_OUT)->count(),
                'exchanges' => $papers->where('kind', Voucher::EXCHANGE)->count(),
                'profit' => Num::sum($papers->where('kind', Voucher::EXCHANGE)->pluck('profit')),
            ];
            $recent = Voucher::with(['party', 'currency', 'otherCurrency', 'account'])->orderByDesc('id')->limit(10)->get();
            $due = Voucher::where('kind', Voucher::MONEY_IN)->whereNull('cancelled_at')->whereNotNull('due_date')->whereDate('due_date', '<=', now()->addDays(7)->toDateString())->count();
        }

        // what the business earned and spent, the money of the last days, and who stands where
        $insight = null;
        if ($seesMoney && $user->can('reports.view')) {
            $months = \App\Services\Insights::months(6);
            $this_ = end($months);
            $last = $months[count($months) - 2];
            $flow = \App\Services\Insights::flow(14);
            $insight = [
                'months' => $months, 'month' => $this_, 'flow' => $flow, 'parties' => \App\Services\Insights::parties(6),
                'changes' => ['income' => \App\Services\Insights::change($this_['income'], $last['income']), 'expense' => \App\Services\Insights::change($this_['expense'], $last['expense']), 'profit' => \App\Services\Insights::change($this_['profit'], $last['profit'])],
                'top_month' => max(1, ...array_map(fn ($m) => max((float) $m['income'], (float) $m['expense']), $months)),
                'top_flow' => max(1, ...array_map(fn ($d) => max((float) $d['in'], (float) $d['out']), $flow)),
                'low_stock' => \App\Services\Billing::plan(tenant()) === 'pro' ? \App\Models\Item::where('track_stock', true)->where('is_active', true)->whereNotNull('reorder_level')
                    ->whereRaw('(select coalesce(sum(s.stock), 0) from item_stocks s where s.item_id = items.id and s.branch_id = ?) <= items.reorder_level', [(int) current_branch()?->id])->count() : null,
            ];
        }

        return view('dashboard', [
            'insight' => $insight,
            'money' => $money, 'worth' => Num::money($worth), 'owed' => $owed, 'today' => $today, 'recent' => $recent, 'due' => $due,
            'base' => Currency::base()->code, 'seesMoney' => $seesMoney, 'seesPapers' => $seesPapers,
            'needsSetup' => $user->can('settings.manage') && setting('setup_done') !== '1',
            'reminders' => $seesMoney ? \App\Models\PartyNote::due()->count() : 0,
        ]);
    }
}
