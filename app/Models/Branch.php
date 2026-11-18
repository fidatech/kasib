<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A place the business works from.
 *   main        holds the licence; there is exactly one
 *   dependent   a further branch of the business, with books of its own
 *   contracted  not used in Kasib (kept from the system this one grew out of)
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

    /** Set while a job works as one branch (the nightly jobs go branch by branch). */
    public static ?Branch $acting = null;

    /** True while a consolidated report reads the books of all the branches together. */
    public static bool $everywhere = false;

    /** @var array<string, ?Branch> the working branch of this request, remembered */
    private static array $working = [];

    /**
     * The branch whose books are open: the one a job acts as, else the one a person of the main
     * branch who manages the settings switched to, else the user's own, else the main branch.
     */
    public static function working(): ?self
    {
        if (static::$acting) {
            return static::$acting;
        }
        $user = auth()->user();
        $user = $user instanceof User ? $user : null;
        $chosen = $user && request()->hasSession() ? (int) request()->session()->get('working_branch', 0) : 0;
        $key = (tenant()?->id ?? 0).'|'.($user?->id ?? 0).'|'.$chosen;
        if (! array_key_exists($key, static::$working)) {
            $own = $user?->branch ?? static::where('type', self::MAIN)->first();
            $branch = $own;
            if ($chosen && $own && $own->isMain() && $chosen !== (int) $own->id && $user->can('settings.manage')) {
                $branch = static::withBooks()->where('is_active', true)->find($chosen) ?? $own;
            }
            static::$working = [$key => $branch];
        }

        return static::$working[$key];
    }

    public static function forgetWorking(): void
    {
        static::$working = [];
    }

    /** Do something as one branch: what is read and written inside belongs to it. */
    public static function as(Branch $branch, callable $work): mixed
    {
        [$before, static::$acting] = [static::$acting, $branch];
        try {
            return $work();
        } finally {
            static::$acting = $before;
        }
    }

    /** Read the books of all the branches together, for a consolidated report. */
    public static function everywhere(callable $work): mixed
    {
        [$before, static::$everywhere] = [static::$everywhere, true];
        try {
            return $work();
        } finally {
            static::$everywhere = $before;
        }
    }

    /** @return list<int> the branches a report reads now: the working one, or all of them */
    public static function reading(): array
    {
        return static::$everywhere ? static::withBooks()->pluck('id')->map(fn ($id) => (int) $id)->all() : [(int) current_branch()?->id];
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
