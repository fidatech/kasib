<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Someone who sells the system and earns a share of what his clients pay. */
class Seller extends Model
{
    use UsesCentralConnection;

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }
}
