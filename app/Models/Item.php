<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/** Something the business buys and sells. A service is an item whose stock is not counted. */
class Item extends Model
{
    protected $guarded = [];

    protected $casts = ['track_stock' => 'boolean', 'is_active' => 'boolean', 'sale_price' => 'decimal:2', 'cost_price' => 'decimal:2'];

    /** Every item is read with its stock and average cost in the branch one works in. */
    protected static function booted(): void
    {
        static::addGlobalScope('stock', function (Builder $query) {
            $branch = (int) current_branch()?->id;
            if ($query->getQuery()->columns === null) {
                $query->select('items.*');
            }
            foreach (['stock', 'avg_cost'] as $column) {
                $query->selectSub(ItemStock::selectRaw('COALESCE(SUM('.$column.'), 0)')->whereColumn('item_stocks.item_id', 'items.id')->where('item_stocks.branch_id', $branch), $column);
            }
        });
    }

    /** Read without the list (a new item, or one read again): asked from the branch. */
    protected function stock(): Attribute
    {
        return Attribute::get(fn ($value, array $all) => array_key_exists('stock', $all) ? ($value ?? '0') : $this->inBranch('stock'));
    }

    protected function avgCost(): Attribute
    {
        return Attribute::get(fn ($value, array $all) => array_key_exists('avg_cost', $all) ? ($value ?? '0') : $this->inBranch('avg_cost'));
    }

    private function inBranch(string $column): string
    {
        return (string) (ItemStock::where('item_id', $this->id)->where('branch_id', (int) current_branch()?->id)->value($column) ?? '0');
    }

    /** Saving an item never writes the two figures it was read with. */
    public function save(array $options = []): bool
    {
        unset($this->attributes['stock'], $this->attributes['avg_cost'], $this->original['stock'], $this->original['avg_cost']);

        return parent::save($options);
    }

    public function label(): string
    {
        return $this->name.($this->code ? ' · '.$this->code : '');
    }
}
