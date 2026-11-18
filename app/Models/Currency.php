<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use Audited;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_base' => 'boolean', 'is_active' => 'boolean', 'quote_unit' => 'integer'];
    }

    /** AFN: every report of the business is in this currency. */
    public static function base(): self
    {
        return static::where('is_base', true)->firstOrFail();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function auditLabel(): string
    {
        return $this->code;
    }
}
