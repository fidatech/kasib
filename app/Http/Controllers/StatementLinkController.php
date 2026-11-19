<?php

namespace App\Http\Controllers;

use App\Models\AccountGroup;
use App\Models\JournalLine;
use App\Models\Party;
use App\Support\Num;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The statement link: a private address a customer opens, without signing in, to read his own
 * balance and the last lines of his account. Nothing can be changed from it. A new link makes
 * the old one stop working.
 */
class StatementLinkController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $party = $this->party($request);
        $party->update(['share_token' => Str::random(40)]);
        audit('updated', $party, 'Statement link made');

        return back()->with('success', __('The link is ready. Send it to the customer.'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $party = $this->party($request);
        $party->update(['share_token' => null]);
        audit('updated', $party, 'Statement link switched off');

        return back()->with('success', __('The link no longer works.'));
    }

    /** What the holder of the link sees. */
    public function show(Request $request): View
    {
        $token = (string) $request->route('token');
        $party = strlen($token) === 40 ? Party::with(['group', 'accounts.currency', 'accounts.total'])->where('share_token', $token)->where('is_active', true)->first() : null;
        abort_unless($party && ! $party->group->isMoney(), 404);

        $since = now()->subDays(90)->toDateString();
        // the customer deals with the business, whichever branch served him: one balance per currency
        $accounts = [];
        foreach ($party->allAccounts()->with(['currency', 'total'])->get()->groupBy('currency_id')->sortKeys() as $group) {
            $lines = JournalLine::whereIn('account_id', $group->pluck('id'))->where('is_void', false)->whereDay('date', '>=', $since)->orderByDesc('date')->orderByDesc('id')->limit(200)->get();
            // from the side of the customer: above zero the business owes him
            $balance = Num::money(Num::neg(Num::sum($group->map(fn ($account) => $account->total?->balance ?? 0))));
            if ($lines->isEmpty() && Num::isZero($balance)) {
                continue;
            }
            $accounts[] = ['currency' => $group->first()->currency->code, 'balance' => $balance, 'lines' => $lines];
        }

        return view('verify.statement', ['party' => $party, 'accounts' => $accounts, 'business' => (string) (setting('company_name') ?: tenant()?->name), 'since' => $since]);
    }

    private function party(Request $request): Party
    {
        $group = AccountGroup::where('key', (string) $request->route('group'))->firstOrFail();
        abort_unless(Permissions::group($request->user(), $group->key, 'manage'), 403);
        abort_if($group->isMoney(), 404);

        return Party::where('account_group_id', $group->id)->findOrFail((int) $request->route('id'));
    }
}
