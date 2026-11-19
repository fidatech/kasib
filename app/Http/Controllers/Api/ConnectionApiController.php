<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\UserError;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\Currency;
use App\Models\JournalLine;
use App\Models\Party;
use App\Models\Voucher;
use App\Services\Accounts;
use App\Services\Ledger;
use App\Services\Rates;
use App\Services\Vouchers;
use App\Support\Num;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The connection with SarafBook (or any other system): read the accounts, their balances and
 * statements and the rates, and record money in and money out. Every answer is JSON.
 *
 * Balances are read the way the screens show them: for a customer or a supplier, above zero is
 * what the business owes it and below zero what it owes the business; for cash and bank, above
 * zero is money held.
 */
class ConnectionApiController extends Controller
{
    public function business(Request $request): JsonResponse
    {
        $branch = current_branch();

        return response()->json([
            'name' => (string) (setting('company_name') ?: tenant()->name), 'branch' => ['id' => $branch->id, 'name' => $branch->name],
            'base_currency' => Currency::base()->code, 'currencies' => Currency::where('is_active', true)->orderBy('id')->pluck('code')->all(),
            'categories' => AccountGroup::active()->orderBy('sort')->orderBy('id')->get()->map(fn (AccountGroup $g) => ['key' => $g->key, 'name' => $g->name, 'kind' => $g->isMoney() ? 'money' : 'party'])->all(),
            'date' => today_iso(), 'can_write' => (bool) $request->attributes->get('api_key')->can_write,
        ]);
    }

    public function rates(): JsonResponse
    {
        $branch = current_branch();
        $base = Currency::base();
        $rates = [];
        foreach (Currency::where('is_active', true)->where('is_base', false)->orderBy('id')->get() as $currency) {
            $rates[] = ['currency' => $currency->code, 'rate' => Rates::current($branch, $currency)];
        }

        return response()->json(['base_currency' => $base->code, 'date' => today_iso(), 'rates' => $rates]);
    }

    public function accounts(Request $request): JsonResponse
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'category' => ['nullable', 'string', 'max:60'], 'kind' => ['nullable', 'in:money,party'], 'page' => ['nullable', 'integer', 'min:1']]);
        $query = Party::with(['group', 'accounts.currency', 'accounts.total'])->where('is_active', true)->orderBy('id');
        if (filled($filters['search'] ?? null)) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $filters['search']).'%';
            $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('phone', 'like', $like));
        }
        if (filled($filters['category'] ?? null)) {
            $query->whereHas('group', fn ($q) => $q->where('key', $filters['category']));
        }
        if (filled($filters['kind'] ?? null)) {
            $query->whereHas('group', fn ($q) => $q->where('type', $filters['kind'] === 'money' ? AccountGroup::MONEY : AccountGroup::PARTY));
        }
        $page = $query->paginate(100);

        return response()->json(['page' => $page->currentPage(), 'pages' => $page->lastPage(), 'total' => $page->total(), 'accounts' => collect($page->items())->map(fn (Party $p) => $this->party($p))->all()]);
    }

    public function account(Request $request): JsonResponse
    {
        return response()->json($this->party($this->find($request)));
    }

    /** A new customer or supplier. One with the same phone in the same category is answered instead of a twin. */
    public function storeAccount(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'phone' => ['nullable', 'string', 'max:50'], 'address' => ['nullable', 'string', 'max:255'], 'category' => ['nullable', 'string', 'max:60']]);
        $group = filled($data['category'] ?? null)
            ? AccountGroup::active()->where('key', $data['category'])->first()
            : AccountGroup::active()->where('type', AccountGroup::PARTY)->orderBy('sort')->orderBy('id')->first();
        if (! $group || $group->isMoney()) {
            throw new UserError('Choose a category of customers or suppliers (see "categories" of /business).');
        }
        $twin = filled($data['phone'] ?? null) ? Party::where('account_group_id', $group->id)->where('phone', $data['phone'])->first() : null;
        if ($twin) {
            return response()->json($this->party($twin->load(['group', 'accounts.currency', 'accounts.total'])) + ['repeated' => true]);
        }
        $party = Party::create(['account_group_id' => $group->id, 'name' => $data['name'], 'phone' => $data['phone'] ?? null, 'address' => $data['address'] ?? null]);

        return response()->json($this->party($party->fresh()->load(['group', 'accounts.currency', 'accounts.total'])), 201);
    }

    /** The lines of an account in one currency between two dates, with the balance after each. */
    public function statement(Request $request): JsonResponse
    {
        $party = $this->find($request);
        $filters = $request->validate(['currency' => ['nullable', 'string', 'max:10'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        $currency = filled($filters['currency'] ?? null) ? $this->currency($filters['currency']) : Currency::base();
        $from = $filters['from'] ?? now()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? today_iso();
        $account = $party->accounts->firstWhere('currency_id', $currency->id);
        $sign = $party->group->isMoney() ? '1' : '-1';

        $opening = $account ? Num::money(Num::mul(Ledger::balanceOn($account->id, $from, true), $sign)) : '0.00';
        $running = $opening;
        $lines = [];
        if ($account) {
            foreach (JournalLine::with('entry')->where('account_id', $account->id)->where('is_void', false)->whereDay('date', '>=', $from)->whereDay('date', '<=', $to)->orderBy('date')->orderBy('id')->limit(5000)->get() as $line) {
                $running = Num::money(Num::add($running, Num::mul(Num::sub($line->debit, $line->credit), $sign)));
                $lines[] = ['date' => substr((string) $line->date, 0, 10), 'number' => $line->entry?->number, 'description' => $line->memo ?: $line->entry?->description,
                    'debit' => Num::money($line->debit), 'credit' => Num::money($line->credit), 'balance' => $running];
            }
        }

        return response()->json(['account' => ['id' => $party->id, 'name' => $party->name], 'currency' => $currency->code, 'from' => $from, 'to' => $to, 'opening' => $opening, 'lines' => $lines, 'closing' => $running]);
    }

    public function moneyIn(Request $request): JsonResponse
    {
        return $this->record($request, Voucher::MONEY_IN);
    }

    public function moneyOut(Request $request): JsonResponse
    {
        return $this->record($request, Voucher::MONEY_OUT);
    }

    public function paper(Request $request): JsonResponse
    {
        return response()->json($this->voucher($this->findPaper($request)));
    }

    /** Only a paper this connection recorded (one that carries a reference) is cancelled from outside. */
    public function cancel(Request $request): JsonResponse
    {
        $voucher = $this->findPaper($request);
        if (! filled($voucher->reference)) {
            throw new UserError('This paper was recorded inside the system. Cancel it there.');
        }
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        Vouchers::cancel($voucher, $data['reason'] ?? 'Cancelled through the connection');

        return response()->json($this->voucher($voucher->fresh()));
    }

    private function record(Request $request, string $kind): JsonResponse
    {
        $data = $request->validate([
            'account' => ['required', 'integer'], 'currency' => ['required', 'string', 'max:10'], 'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'date' => ['nullable', 'date'], 'due' => ['nullable', 'date'], 'note' => ['nullable', 'string', 'max:255'], 'reference' => ['nullable', 'string', 'max:100'], 'money' => ['nullable', 'integer'],
        ]);
        // the same reference sent twice is the same paper: the first one is answered, nothing is recorded again
        if (filled($data['reference'] ?? null) && ($old = Voucher::where('reference', $data['reference'])->whereNull('cancelled_at')->first())) {
            return response()->json($this->voucher($old) + ['repeated' => true]);
        }
        $party = Party::with('group')->where('is_active', true)->find($data['account']);
        if (! $party || $party->group->isMoney()) {
            throw new UserError('"account" is the id of a customer or a supplier.');
        }
        $currency = $this->currency($data['currency']);
        $branch = current_branch();
        if (filled($data['money'] ?? null)) {
            $box = Party::with('group')->where('is_active', true)->find($data['money']);
            if (! $box || ! $box->group->isMoney()) {
                throw new UserError('"money" is the id of a cash box or a bank account.');
            }
            $money = Accounts::party($box, $currency, $branch);
        } else {
            $money = Accounts::cash($branch, $currency);
        }
        $date = $data['date'] ?? today_iso();

        $voucher = DB::transaction(function () use ($kind, $party, $money, $date, $data) {
            $voucher = $kind === Voucher::MONEY_IN
                ? Vouchers::moneyIn($party, $money, $date, $data['amount'], $data['due'] ?? null, $data['note'] ?? null)
                : Vouchers::moneyOut($party, $money, $date, $data['amount'], $data['note'] ?? null);
            $voucher->forceFill(['reference' => $data['reference'] ?? null])->save();

            return $voucher;
        });

        return response()->json($this->voucher($voucher->fresh()), 201);
    }

    private function find(Request $request): Party
    {
        return Party::with(['group', 'accounts.currency', 'accounts.total'])->findOrFail((int) $request->route('id'));
    }

    private function findPaper(Request $request): Voucher
    {
        $ref = (string) $request->route('reference');

        return Voucher::whereIn('kind', [Voucher::MONEY_IN, Voucher::MONEY_OUT])->where(fn ($q) => $q->where('reference', $ref)->orWhere('number', $ref))->orderByDesc('id')->firstOrFail();
    }

    private function currency(string $code): Currency
    {
        return Currency::where('is_active', true)->where('code', strtoupper(trim($code)))->first() ?? throw new UserError('The business does not use the currency '.strtoupper(trim($code)).'.');
    }

    private function party(Party $party): array
    {
        $sign = $party->group->isMoney() ? '1' : '-1';
        $balances = [];
        foreach ($party->accounts as $account) {
            /** @var Account $account */
            $amount = Num::money(Num::mul($account->total?->balance ?? 0, $sign));
            if (! Num::isZero($amount)) {
                $balances[$account->currency->code] = $amount;
            }
        }

        return ['id' => $party->id, 'name' => $party->name, 'phone' => $party->phone, 'category' => $party->group->key, 'kind' => $party->group->isMoney() ? 'money' : 'party', 'balances' => (object) $balances];
    }

    private function voucher(Voucher $voucher): array
    {
        $voucher->loadMissing(['party', 'currency']);

        return [
            'number' => $voucher->number, 'reference' => $voucher->reference, 'kind' => $voucher->kind, 'date' => $voucher->date?->toDateString(),
            'account' => ['id' => $voucher->party_id, 'name' => $voucher->party?->name], 'currency' => $voucher->currency?->code, 'amount' => Num::money($voucher->amount),
            'note' => $voucher->description, 'cancelled' => $voucher->isCancelled(),
        ];
    }
}
