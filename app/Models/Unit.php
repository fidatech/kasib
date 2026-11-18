<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A unit items are counted in: piece, kilo, carton ... */
class Unit extends Model
{
    public const DEFAULTS = ['pcs' => 'دانه', 'kg' => 'کیلو', 'gram' => 'گرام', 'ton' => 'تن', 'ser' => 'سیر', 'litre' => 'لیتر', 'metre' => 'متر', 'carton' => 'کارتن', 'bag' => 'بوجی', 'box' => 'قطی',
        'pack' => 'بسته', 'bottle' => 'بوتل', 'can' => 'قوطی', 'dozen' => 'درجن', 'pair' => 'جوره', 'set' => 'ست'];

    protected $guarded = [];

    public function label(): string
    {
        return text_dir() === 'rtl' && filled($this->name_local) ? $this->name_local.' ('.$this->name.')' : $this->name;
    }

    /** name => label, for the pick list of an item; a unit an item already carries is always on it */
    public static function options(?string $keep = null): array
    {
        $options = static::orderBy('name')->get()->mapWithKeys(fn (self $unit) => [$unit->name => $unit->label()])->all();
        if (filled($keep) && ! isset($options[$keep])) {
            $options[$keep] = $keep;
        }

        return $options;
    }
}
