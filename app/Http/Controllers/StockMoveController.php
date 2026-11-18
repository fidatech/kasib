<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\StockMove;
use App\Models\Warehouse;
use App\Services\Trade;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Goods moved between the warehouses of the branch: the list, and the form to move some. */
class StockMoveController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('transactions.view');

        return view('trade.moves', [
            'moves' => StockMove::with(['item', 'from', 'to', 'creator'])->orderByDesc('id')->paginate(per_page()),
            'warehouses' => Warehouse::where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(),
            'items' => Item::where('is_active', true)->where('track_stock', true)->orderBy('name')->limit(5000)->get(),
            'canManage' => $request->user()->can('transactions.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('transactions.manage');
        $data = $request->validate(['date' => ['required', 'date'], 'item_id' => ['required', 'integer'], 'from_warehouse_id' => ['required', 'integer'], 'to_warehouse_id' => ['required', 'integer', 'different:from_warehouse_id'],
            'qty' => ['required', 'numeric', 'gt:0'], 'note' => ['nullable', 'string', 'max:255']]);
        $move = Trade::move(Item::findOrFail((int) $data['item_id']), Warehouse::findOrFail((int) $data['from_warehouse_id']), Warehouse::findOrFail((int) $data['to_warehouse_id']), $data['qty'], $data['date'], $data['note'] ?? null);

        return redirect()->route('stock-moves.index')->with('success', __(':number saved.', ['number' => $move->number]));
    }
}
