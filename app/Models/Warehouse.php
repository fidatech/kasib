<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A place of a branch where goods are kept. Every branch has one from the start and may add more. */
class Warehouse extends Model
{
    use \App\Models\Concerns\BranchScoped;

    protected $guarded = [];

    protected $casts = ['is_default' => 'boolean', 'is_active' => 'boolean'];

    /** The warehouse a branch uses when none is chosen. Made the first time it is asked for. */
    public static function main(?Branch $branch = null): self
    {
        $branch ??= current_branch();
        $query = fn () => static::withoutGlobalScope('branch')->where('branch_id', $branch->id);

        return $query()->where('is_default', true)->orderBy('id')->first()
            ?? $query()->orderBy('id')->first()
            ?? static::create(['branch_id' => $branch->id, 'name' => 'Main warehouse', 'is_default' => true])->fresh();
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ItemStock::class);
    }
}
