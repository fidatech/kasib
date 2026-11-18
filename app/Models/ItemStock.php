<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** What a branch has of one item, and what a unit of it cost on average. */
class ItemStock extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /** The row of an item in a branch, locked for the rest of the transaction. Made the first time it is needed. */
    public static function of(Item|int $item, Branch $branch): self
    {
        $id = $item instanceof Item ? $item->id : $item;
        static::firstOrCreate(['item_id' => $id, 'branch_id' => $branch->id]);

        return static::where('item_id', $id)->where('branch_id', $branch->id)->lockForUpdate()->firstOrFail();
    }
}
