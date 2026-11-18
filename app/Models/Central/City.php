<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

/** A city or district centre of a province. */
class City extends Model
{
    use UsesCentralConnection;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['is_centre' => 'boolean'];

    public function label(): string
    {
        return app()->getLocale() === 'en' ? $this->name : $this->name_local;
    }
}
