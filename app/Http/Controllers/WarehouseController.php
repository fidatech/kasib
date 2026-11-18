<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\ItemStock;
use App\Models\StockMove;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;

/** Warehouses of the branch one works in: where its goods are kept. */
class WarehouseController extends CrudController
{
    protected string $model = Warehouse::class;

    protected string $route = 'warehouses';

    protected string $permission = 'transactions';

    protected string $title = 'Warehouses';

    protected string $singular = 'Warehouse';

    protected string $icon = 'warehouse';

    protected array $search = ['name', 'address'];

    protected function fields(?Model $record): array
    {
        return [
            ['name' => 'name', 'label' => 'Name', 'rules' => ['required', 'string', 'max:100']],
            ['name' => 'address', 'label' => 'Address', 'rules' => ['nullable', 'string', 'max:255']],
            ['name' => 'is_active', 'label' => 'In use', 'type' => 'checkbox', 'default' => true],
        ];
    }

    protected function columns(): array
    {
        return [
            'Name' => fn (Warehouse $w) => $w->name.($w->is_default ? ' · '.__('Main') : ''),
            'Address' => fn (Warehouse $w) => $w->address,
            'Items in it' => fn (Warehouse $w) => (string) ItemStock::where('warehouse_id', $w->id)->where('stock', '>', 0)->count(),
            'Value of the stock' => fn (Warehouse $w) => money(ItemStock::where('warehouse_id', $w->id)->selectRaw('COALESCE(SUM(stock * avg_cost), 0) as value')->value('value')),
            'In use' => fn (Warehouse $w) => $w->is_active ? __('Yes') : __('No'),
        ];
    }

    protected function rowLinks(Model $record): array
    {
        return [['label' => __('Stock on Hand'), 'url' => route('trade.reports.stock', ['warehouse' => $record->id])]];
    }

    protected function deleteProblem(Model $record): ?string
    {
        if ($record->is_default) {
            return __('The main warehouse of a branch stays.');
        }

        return ItemStock::where('warehouse_id', $record->id)->where('stock', '!=', 0)->exists() || Invoice::where('warehouse_id', $record->id)->exists()
            || StockMove::where('from_warehouse_id', $record->id)->orWhere('to_warehouse_id', $record->id)->exists()
            ? __('This warehouse holds goods or is on papers. Switch it off instead of deleting it.') : null;
    }
}
