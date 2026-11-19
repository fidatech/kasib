<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Item;
use App\Support\Num;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** The reports of sales and purchases: what each item earned, what is in stock, and who buys the most. */
class TradeReportController extends Controller
{
    /** Sales by item between two dates: quantity, value, cost and profit, returns taken off. Amounts in the base currency. */
    public function items(Request $request): View
    {
        Gate::authorize('reports.view');
        [$from, $to] = $this->dates($request);
        $sums = DB::table('invoice_lines as l')->join('invoices as i', 'i.id', '=', 'l.invoice_id')->join('items as t', 't.id', '=', 'l.item_id')
            ->whereNull('i.cancelled_at')->where('i.branch_id', (int) current_branch()?->id)->whereIn('i.kind', [Invoice::SALE, Invoice::SALE_RETURN])->whereDay('i.date', '>=', $from)->whereDay('i.date', '<=', $to)
            ->groupBy('t.id', 't.name', 't.unit', 'i.kind')->selectRaw('t.id as id, t.name as name, t.unit as unit, i.kind as kind, SUM(l.qty) as qty, SUM(l.base_amount) as value, SUM(l.base_cost) as cost')->get()->groupBy('id');

        $list = [];
        foreach ($sums as $group) {
            $sale = $group->firstWhere('kind', Invoice::SALE);
            $back = $group->firstWhere('kind', Invoice::SALE_RETURN);
            $value = Num::sub($sale->value ?? 0, $back->value ?? 0);
            $cost = Num::sub($sale->cost ?? 0, $back->cost ?? 0);
            $list[] = ['name' => $group->first()->name, 'unit' => $group->first()->unit, 'qty' => Num::sub($sale->qty ?? 0, $back->qty ?? 0), 'value' => $value, 'cost' => $cost, 'profit' => Num::sub($value, $cost)];
        }
        usort($list, fn ($a, $b) => Num::cmp($b['profit'], $a['profit']));
        $rows = array_map(fn ($r) => ['cells' => [$r['name'], qty($r['qty']).' '.$r['unit'], money($r['value']), money($r['cost']), money($r['profit']),
            Num::isPositive($r['value']) ? Num::round(Num::div(Num::mul($r['profit'], '100'), $r['value']), 1).'%' : '']], $list);
        if ($rows) {
            $value = Num::sum(array_column($list, 'value'));
            $profit = Num::sum(array_column($list, 'profit'));
            $rows[] = ['total' => true, 'cells' => [__('Total'), '', money($value), money(Num::sum(array_column($list, 'cost'))), money($profit), Num::isPositive($value) ? Num::round(Num::div(Num::mul($profit, '100'), $value), 1).'%' : '']];
        }
        $base = Currency::base()->code;

        return $this->sheet('Sales by Item', 'boxes-stacked', [['Item'], ['Sold', 'end'], ['Sales '.$base, 'end'], ['Cost '.$base, 'end'], ['Profit '.$base, 'end'], ['Margin', 'end']], $rows, $from, $to);
    }

    /** What is in stock now, with its value at the average cost. */
    public function stock(Request $request): View
    {
        Gate::authorize('reports.view');
        $low = $request->query('show') === 'low';
        $rows = [];
        $total = '0';
        $warehouses = \App\Models\Warehouse::orderByDesc('is_default')->orderBy('name')->get();
        $warehouse = $warehouses->firstWhere('id', (int) $request->query('warehouse'));
        $held = $warehouse ? \App\Models\ItemStock::where('warehouse_id', $warehouse->id)->get()->keyBy('item_id') : null;
        foreach (Item::where('track_stock', true)->where('is_active', true)->orderBy('name')->limit(20000)->get() as $item) {
            if ($held !== null) {
                // one warehouse: what it holds of the item, at its own average cost
                $item->setRawAttributes(['stock' => (string) ($held[$item->id]->stock ?? '0'), 'avg_cost' => (string) ($held[$item->id]->avg_cost ?? '0')] + $item->getAttributes());
                if (\App\Support\Num::isZero($item->stock)) {
                    continue;
                }
            }
            $isLow = $item->reorder_level !== null && Num::cmp($item->stock, $item->reorder_level) <= 0;
            if ($low && ! $isLow) {
                continue;
            }
            $value = Num::money(Num::mul($item->stock, $item->avg_cost));
            $total = Num::add($total, $value);
            $rows[] = ['cells' => [['text' => $item->name, 'url' => route('items.stock', ['id' => $item->id])], (string) $item->code, qty($item->stock).' '.$item->unit, money($item->avg_cost), money($value), $isLow ? __('Low in stock') : '']];
        }
        if ($rows) {
            $rows[] = ['total' => true, 'cells' => [__('Total'), '', '', '', money($total), '']];
        }

        return view('reports.sheet', ['title' => __('Stock on Hand'), 'icon' => 'boxes-stacked', 'columns' => [['Item'], ['Code or barcode'], ['In stock', 'end'], ['Average cost', 'end'], ['Value '.Currency::base()->code, 'end'], ['Status']], 'rows' => $rows,
            'from' => null, 'to' => null, 'filters' => array_merge([['name' => 'show', 'label' => 'Show', 'options' => ['all' => __('All items'), 'low' => __('Low in stock')], 'value' => $low ? 'low' : 'all']],
                $warehouses->count() > 1 ? [['name' => 'warehouse', 'label' => 'Warehouse', 'options' => ['' => __('All warehouses')] + $warehouses->pluck('name', 'id')->all(), 'value' => (string) ($warehouse?->id ?? '')]] : [])]);
    }

    /** Sales by customer and purchases by supplier between two dates, returns taken off, in the base currency. */
    public function parties(Request $request): View
    {
        Gate::authorize('reports.view');
        [$from, $to] = $this->dates($request);
        $side = $request->query('side') === 'purchases' ? 'purchases' : 'sales';
        [$main, $back] = $side === 'sales' ? [Invoice::SALE, Invoice::SALE_RETURN] : [Invoice::PURCHASE, Invoice::PURCHASE_RETURN];
        $sums = Invoice::with('party')->whereNull('cancelled_at')->whereIn('kind', [$main, $back])->whereDay('date', '>=', $from)->whereDay('date', '<=', $to)
            ->groupBy('party_id', 'kind')->selectRaw('party_id, kind, COUNT(*) as papers, SUM(base_total) as total')->get()->groupBy('party_id');
        $list = [];
        foreach ($sums as $group) {
            $a = $group->firstWhere('kind', $main);
            $b = $group->firstWhere('kind', $back);
            $list[] = ['name' => (string) $group->first()->party?->name, 'papers' => (int) ($a->papers ?? 0), 'total' => $a->total ?? 0, 'back' => $b->total ?? 0, 'net' => Num::sub($a->total ?? 0, $b->total ?? 0)];
        }
        usort($list, fn ($x, $y) => Num::cmp($y['net'], $x['net']));
        $rows = array_map(fn ($r) => ['cells' => [$r['name'], $r['papers'], money($r['total']), money($r['back']), money($r['net'])]], $list);
        if ($rows) {
            $rows[] = ['total' => true, 'cells' => [__('Total'), array_sum(array_column($list, 'papers')), money(Num::sum(array_column($list, 'total'))), money(Num::sum(array_column($list, 'back'))), money(Num::sum(array_column($list, 'net')))]];
        }

        return $this->sheet($side === 'sales' ? 'Sales by Customer' : 'Purchases by Supplier', 'users', [['Account'], ['Invoices', 'end'], ['Invoiced '.Currency::base()->code, 'end'], ['Returned', 'end'], ['Net', 'end']], $rows, $from, $to,
            [['name' => 'side', 'label' => 'Show', 'options' => ['sales' => __('Sales by Customer'), 'purchases' => __('Purchases by Supplier')], 'value' => $side]]);
    }

    private function sheet(string $title, string $icon, array $columns, array $rows, ?string $from, ?string $to, array $filters = []): View
    {
        return view('reports.sheet', ['title' => __($title), 'icon' => $icon, 'columns' => $columns, 'rows' => $rows, 'from' => $from, 'to' => $to, 'filters' => $filters]);
    }

    private function dates(Request $request): array
    {
        $f = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        return [$f['from'] ?? now()->startOfMonth()->toDateString(), $f['to'] ?? today_iso()];
    }
}
