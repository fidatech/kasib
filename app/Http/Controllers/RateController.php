<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Services\Days;
use App\Services\Rates;
use App\Support\Num;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** The exchange rates of the business: one rate per currency, in the base currency. */
class RateController extends Controller
{
    public function index(): View
    {
        Gate::authorize('days.view');
        $branch = current_branch();
        $totals = Days::totals($branch);

        $rows = [];
        foreach (Currency::active()->where('is_base', false)->orderBy('sort')->get() as $currency) {
            $rate = ExchangeRate::where('branch_id', $branch->id)->where('currency_id', $currency->id)->orderByDesc('id')->first();
            $rows[] = ['currency' => $currency, 'rate' => $rate, 'balance' => $totals[$currency->id]['balance'] ?? '0.00'];
        }

        return view('rates.index', [
            'branch' => $branch,
            'rows' => $rows,
            'history' => ExchangeRate::with('currency')->where('branch_id', $branch->id)->orderByDesc('id')->limit(30)->get(),
            'canManage' => auth()->user()->can('days.manage'),
        ]);
    }

    /**
     * New rates. A rate that did not change is not written again. When any rate changed, the
     * foreign money of the business is valued again and the gain or loss goes to the books.
     */
    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('days.manage');
        $branch = current_branch();
        $data = $request->validate(['rates' => ['nullable', 'array'], 'rates.*.rate' => ['nullable', 'numeric', 'gt:0']]);
        $all = $data['rates'] ?? [];

        $changed = DB::transaction(function () use ($branch, $all) {
            $changed = 0;
            foreach (Currency::active()->where('is_base', false)->get() as $currency) {
                $given = $all[$currency->id]['rate'] ?? null;
                if (! Num::isPositive($given ?? 0)) {
                    continue;
                }
                $now = Rates::current($branch, $currency);
                if ($now === null || Num::cmp($now, $given) !== 0) {
                    Rates::set($branch, $currency, $given);
                    $changed++;
                }
            }
            if ($changed) {
                Days::revalue($branch);
            }

            return $changed;
        });
        audit('updated', null, 'Exchange rates ('.$changed.')');

        // saved from the pop-up of the row under the navbar: back to the page one was on
        return ($request->boolean('stay') ? back() : redirect()->route('rates.index'))->with('success', __('Rates saved.'));
    }
}
