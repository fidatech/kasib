<?php

namespace App\Models\Concerns;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Builder;

/**
 * A paper that belongs to one branch. A list shows the papers of the branch one works in, and a
 * new paper is written into that branch, without every page having to say so. Inside
 * Branch::everywhere() the papers of all the branches are read.
 */
trait BranchScoped
{
    public static function bootBranchScoped(): void
    {
        static::addGlobalScope('branch', function (Builder $query) {
            if (! Branch::$everywhere && ($branch = current_branch())) {
                $query->where($query->getModel()->getTable().'.branch_id', $branch->id);
            }
        });
        static::creating(function ($model) {
            if (empty($model->branch_id) && ($branch = current_branch())) {
                $model->branch_id = $branch->id;
            }
        });
    }
}
