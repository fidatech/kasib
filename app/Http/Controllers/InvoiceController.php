<?php

namespace App\Http\Controllers;

use App\Models\AccountGroup;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Party;
use App\Services\Accounts;
use App\Services\Trade;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Sales, purchases and their returns: one list, one form and one printed invoice for the four of them. */
class InvoiceController extends Controller
{
    private const ROWS = 12;

    public function index(Request $request): View
    {
        Gate::authorize('transactions.view');
        $kind = $this->kind($request);
        $f = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'party' => ['nullable', 'integer'], 'q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:open,cancelled']]);

        $rows = Invoice::with(['party', 'currency', 'creator'])->where('kind', $kind)
            ->when($f['from'] ?? null, fn ($q, $v) => $q->whereDayOf('date', '>=', $v))->when($f['to'] ?? null, fn ($q, $v) => $q->whereDayOf('date', '<=', $v))
            ->when($f['party'] ?? null, fn ($q, $v) => $q->where('party_id', $v))
            ->when(($f['status'] ?? null) === 'cancelled', fn ($q) => $q->whereNotNull('cancelled_at'))->when(($f['status'] ?? null) === 'open', fn ($q) => $q->whereNull('cancelled_at'))
            ->when(trim((string) ($f['q'] ?? '')) !== '', fn ($q) => $q->where(fn ($w) => $w->where('number', 'like', '%'.trim($f['q']).'%')->orWhere('note', 'like', '%'.trim($f['q']).'%')))
            ->orderByDesc('date')->orderByDesc('id')->paginate(per_page())->withQueryString();

        return view('trade.index', [
            'kind' => $kind, 'about' => Invoice::KINDS[$kind], 'rows' => $rows, 'filters' => $f, 'parties' => $this->parties(),
            'canManage' => $request->user()->can('transactions.manage'),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('transactions.manage');
        $kind = $this->kind($request);

        return view('trade.form', [
            'kind' => $kind, 'about' => Invoice::KINDS[$kind], 'rows' => self::ROWS, 'parties' => $this->parties(), 'walkIn' => Trade::walkIn(),
            'items' => Item::where('is_active', true)->orderBy('name')->limit(5000)->get(), 'currencies' => Currency::active()->orderBy('sort')->get(), 'base' => Currency::base(),
            'money' => Accounts::money(current_branch()), 'selling' => in_array($kind, [Invoice::SALE, Invoice::SALE_RETURN], true),
            'warehouses' => \App\Models\Warehouse::where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('transactions.manage');
        $kind = $this->kind($request);
        $data = $request->validate([
            'date' => ['required', 'date'], 'party_id' => ['required', 'integer'], 'currency_id' => ['required', Rule::exists('currencies', 'id')->where('is_active', true)],
            'lines' => ['required', 'array', 'max:200'], 'lines.*.item_id' => ['nullable', 'integer'], 'lines.*.qty' => ['nullable', 'numeric', 'min:0'], 'lines.*.price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'discount' => ['nullable', 'numeric', 'min:0'], 'paid' => ['nullable', 'numeric', 'min:0'], 'account_id' => ['nullable', 'integer'],
            'due_date' => ['nullable', 'date'], 'note' => ['nullable', 'string', 'max:500'], 'warehouse_id' => ['nullable', 'integer'],
            'costs' => ['nullable', 'array', 'max:20'], 'costs.*.name' => ['nullable', 'string', 'max:100'], 'costs.*.amount' => ['nullable', 'numeric', 'min:0'], 'costs.*.account_id' => ['nullable', 'integer'],
        ]);
        // the rows left empty are not lines
        $lines = array_values(array_filter($data['lines'], fn (array $line) => ! empty($line['item_id']) && (float) ($line['qty'] ?? 0) > 0));
        $party = $this->parties()->firstWhere('id', (int) $data['party_id']) ?? abort(422);
        $money = ! empty($data['account_id']) ? Accounts::money(current_branch())->firstWhere('id', (int) $data['account_id']) : null;

        // the costs of a purchase: the rows with an amount, each with the money account it is paid from
        $accounts = Accounts::money(current_branch());
        $costs = [];
        foreach ($kind === Invoice::PURCHASE ? ($data['costs'] ?? []) : [] as $cost) {
            if ((float) ($cost['amount'] ?? 0) > 0) {
                $costs[] = ['name' => (string) ($cost['name'] ?? ''), 'amount' => $cost['amount'], 'account' => $accounts->firstWhere('id', (int) ($cost['account_id'] ?? 0))];
            }
        }
        $warehouse = ! empty($data['warehouse_id']) ? (\App\Models\Warehouse::find((int) $data['warehouse_id']) ?? abort(422)) : null;

        $invoice = Trade::record($kind, $party, Currency::findOrFail($data['currency_id']), $data['date'], $lines, $data['discount'] ?? 0, $data['paid'] ?? 0, $money, $data['due_date'] ?? null, $data['note'] ?? null, $costs, $warehouse);

        return redirect()->route('trade.show', ['kind' => $invoice->slug(), 'id' => $invoice->id])->with('success', __(':number saved.', ['number' => $invoice->number]));
    }

    public function show(Request $request): View
    {
        Gate::authorize('transactions.view');
        $kind = $this->kind($request);
        $invoice = Invoice::with(['lines.item', 'party', 'currency', 'creator', 'paymentVoucher', 'costs.account', 'costs.currency', 'warehouse'])->where('kind', $kind)->findOrFail((int) $request->route('id'));

        return view('trade.show', ['invoice' => $invoice, 'about' => Invoice::KINDS[$kind], 'canManage' => $request->user()->can('transactions.manage'), 'business' => (string) (setting('company_name') ?: tenant()?->name)]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Gate::authorize('transactions.manage');
        $kind = $this->kind($request);
        $invoice = Invoice::where('kind', $kind)->findOrFail((int) $request->route('id'));
        Trade::cancel($invoice, $request->input('reason'));

        return redirect()->route('trade.index', ['kind' => $invoice->slug()])->with('success', __(':number cancelled.', ['number' => $invoice->number]));
    }

    private function kind(Request $request): string
    {
        return Invoice::kindOf((string) $request->route('kind')) ?? abort(404);
    }

    private function parties()
    {
        Trade::walkIn();

        return Party::with('group')->where('is_active', true)->whereHas('group', fn ($q) => $q->where('type', AccountGroup::PARTY))->orderByDesc('is_system')->orderBy('name')->limit(5000)->get();
    }
}
