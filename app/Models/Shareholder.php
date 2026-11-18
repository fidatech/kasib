<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A partner of the licensed company. Partners belong to the licence, not to a branch. */
class Shareholder extends Model
{
    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    public function entries(): HasMany
    {
        return $this->hasMany(CapitalEntry::class);
    }

    public function label(): string
    {
        return (text_dir() === 'rtl' ? $this->name_local : null) ?: $this->name;
    }
}
