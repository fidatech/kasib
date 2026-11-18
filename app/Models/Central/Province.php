<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A province of Afghanistan, with its name in the three languages. */
class Province extends Model
{
    use UsesCentralConnection;

    public $timestamps = false;

    protected $guarded = [];

    public function cities(): HasMany
    {
        return $this->hasMany(City::class)->orderBy('sort');
    }

    public function label(): string
    {
        return match (app()->getLocale()) {
            'fa' => $this->name_fa, 'ps' => $this->name_ps, default => $this->name,
        };
    }
}
