<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Goods moved from one warehouse of a branch to another. */
class StockMove extends Model
{
    use \App\Models\Concerns\BranchScoped;

    protected $guarded = [];

    protected $casts = ['date' => 'date:Y-m-d'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function from(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
