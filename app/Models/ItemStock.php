<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** What a branch has of one item, and what a unit of it cost on average. */
class ItemStock extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /** The row of an item in a warehouse (the main one of the branch when none is named), locked for the rest of the transaction. Made the first time it is needed. */
    public static function of(Item|int $item, Branch $branch, ?Warehouse $warehouse = null): self
    {
        $id = $item instanceof Item ? $item->id : $item;
        $warehouse ??= Warehouse::main($branch);
        static::firstOrCreate(['item_id' => $id, 'warehouse_id' => $warehouse->id], ['branch_id' => $warehouse->branch_id]);

        return static::where('item_id', $id)->where('warehouse_id', $warehouse->id)->lockForUpdate()->firstOrFail();
    }

    /** One number for the average cost of several rows: by weight of what each holds; with nothing in stock, the last known. */
    public const AVERAGE = 'CASE WHEN COALESCE(SUM(stock), 0) > 0 THEN SUM(stock * avg_cost) / SUM(stock) ELSE COALESCE(MAX(avg_cost), 0) END';
}
