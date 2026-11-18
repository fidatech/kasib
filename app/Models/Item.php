<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Something the business buys and sells. A service is an item whose stock is not counted. */
class Item extends Model
{
    protected $guarded = [];

    protected $casts = ['track_stock' => 'boolean', 'is_active' => 'boolean', 'sale_price' => 'decimal:2', 'cost_price' => 'decimal:2'];

    public function label(): string
    {
        return $this->name.($this->code ? ' · '.$this->code : '');
    }
}
