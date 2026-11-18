<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\InvoiceLine;
use App\Models\Item;
use App\Services\Trade;
use App\Support\Num;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Items: what the business buys and sells, with the stock of each and what it cost. */
class ItemController extends CrudController
{
    protected string $model = Item::class;

    protected string $route = 'items';

    protected string $permission = 'transactions';

    protected string $title = 'Items';

    protected string $singular = 'Item';

    protected string $icon = 'boxes-stacked';

    protected array $search = ['name', 'code'];

    protected function query(Request $request): Builder
    {
        return Item::query()->when($request->query('show') === 'low', fn ($q) => $q->where('track_stock', true)->whereNotNull('reorder_level')
            ->whereRaw('(select coalesce(sum(s.stock), 0) from item_stocks s where s.item_id = items.id and s.branch_id = ?) <= items.reorder_level', [(int) current_branch()?->id]));
    }

    protected function filters(Request $request): array
    {
        return [['name' => 'show', 'label' => __('All items'), 'options' => ['low' => __('Low in stock')], 'value' => $request->query('show')]];
    }

    protected function fields(?Model $record): array
    {
        $base = Currency::base()->code;

        return [
            ['name' => 'name', 'label' => 'Name', 'rules' => ['required', 'string', 'max:150']],
            ['name' => 'code', 'label' => 'Code or barcode', 'rules' => ['nullable', 'string', 'max:60', Rule::unique('items', 'code')->ignore($record?->id)]],
            ['name' => 'unit', 'label' => 'Unit', 'default' => 'pcs', 'rules' => ['required', 'string', 'max:30'], 'help' => 'Piece, kilo, metre, carton ...'],
            ['name' => 'sale_price', 'label' => 'Sale price', 'type' => 'decimal', 'default' => 0, 'rules' => ['required', 'numeric', 'min:0'], 'help' => __('In :currency. It is offered on a sale and can be changed there.', ['currency' => $base])],
            ['name' => 'cost_price', 'label' => 'Purchase price', 'type' => 'decimal', 'default' => 0, 'rules' => ['required', 'numeric', 'min:0'], 'help' => __('In :currency. It is offered on a purchase.', ['currency' => $base])],
            ['name' => 'reorder_level', 'label' => 'Warn when the stock falls to', 'type' => 'decimal', 'rules' => ['nullable', 'numeric', 'min:0']],
            ['name' => 'track_stock', 'label' => 'Count its stock', 'type' => 'checkbox', 'default' => true, 'help' => 'Switch off for a service.'],
            ['name' => 'is_active', 'label' => 'In use', 'type' => 'checkbox', 'default' => true],
            ['name' => 'notes', 'label' => 'Notes', 'col' => 12, 'rules' => ['nullable', 'string', 'max:500']],
        ];
    }

    protected function columns(): array
    {
        return [
            'Name' => fn (Item $i) => $i->name,
            'Code or barcode' => fn (Item $i) => $i->code,
            'Unit' => fn (Item $i) => $i->unit,
            'Sale price' => fn (Item $i) => money($i->sale_price),
            'In stock' => fn (Item $i) => $i->track_stock ? qty($i->stock) : '—',
            'Average cost' => fn (Item $i) => $i->track_stock ? money($i->avg_cost) : '—',
            'Value of the stock' => fn (Item $i) => $i->track_stock ? money(Num::mul($i->stock, $i->avg_cost)) : '—',
            'In use' => fn (Item $i) => $i->is_active ? __('Yes') : __('No'),
        ];
    }

    protected function deleteProblem(Model $record): ?string
    {
        return InvoiceLine::where('item_id', $record->id)->exists() || \App\Models\ItemStock::where('item_id', $record->id)->where('stock', '!=', 0)->exists()
            ? __('This item is on invoices or has stock. Switch it off instead of deleting it.') : null;   // the stock of the branch one works in; another branch that holds some has it on an invoice
    }

    protected function rowLinks(Model $record): array
    {
        return $record->track_stock ? [['label' => __('Stock card'), 'url' => route('items.stock', ['id' => $record->id])]] : [];
    }

    /** The stock card of one item: every invoice that moved it, and the form to write what was counted. */
    public function stock(Request $request): View
    {
        Gate::authorize('transactions.view');
        $item = Item::findOrFail((int) $request->route('id'));

        return view('trade.stock', [
            'item' => $item, 'base' => Currency::base()->code, 'canManage' => $request->user()->can('transactions.manage'),
            'lines' => InvoiceLine::with('invoice.party')->where('item_id', $item->id)->whereHas('invoice', fn ($q) => $q->whereNull('cancelled_at'))->orderByDesc('id')->paginate(per_page()),
        ]);
    }

    public function count(Request $request): RedirectResponse
    {
        Gate::authorize('transactions.manage');
        $item = Item::findOrFail((int) $request->route('id'));
        $data = $request->validate(['counted' => ['required', 'numeric', 'min:0'], 'unit_cost' => ['nullable', 'numeric', 'min:0'], 'note' => ['nullable', 'string', 'max:255']]);
        Trade::adjust($item, $data['counted'], $data['unit_cost'] ?? null, $data['note'] ?? null);

        return back()->with('success', __('The stock of :item is now :qty.', ['item' => $item->name, 'qty' => qty($data['counted'])]));
    }
}
