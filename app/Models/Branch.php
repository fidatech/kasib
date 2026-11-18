<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A place the business works from.
 *   main        holds the licence; there is exactly one
 *   dependent   a representative saraf under the licence, with books of its own
 *   contracted  a partner saraf outside the licence (here or abroad): only accounts, no sign-in
 */
class Branch extends Model
{
    use Audited;

    public const MAIN = 'main';

    public const DEPENDENT = 'dependent';

    public const CONTRACTED = 'contracted';

    public const TYPES = [self::MAIN => 'Main branch', self::DEPENDENT => 'Dependent branch', self::CONTRACTED => 'Contracted branch'];

    // where the branch is, as the DAB indicators report counts them
    public const KABUL = 'kabul';

    public const BORDER = 'border';

    public const OTHER = 'other';

    public const LOCATIONS = [self::KABUL => 'Centre (Kabul)', self::BORDER => 'Border province', self::OTHER => 'Other province'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** The branch that holds the licence. */
    public static function main(): self
    {
        return static::where('type', self::MAIN)->firstOrFail();
    }

    public function isMain(): bool
    {
        return $this->type === self::MAIN;
    }

    /** Branches that keep books and whose people sign in. */
    public function scopeWithBooks($query)
    {
        return $query->whereIn('type', [self::MAIN, self::DEPENDENT]);
    }
}
