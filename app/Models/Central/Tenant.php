<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

/** One business (a customer of Fida Technologies) with its address, database and software licence. */
class Tenant extends Model
{
    use UsesCentralConnection;

    public const ACTIVE = 'active';

    public const SUSPENDED = 'suspended';

    public const ARCHIVED = 'archived';

    public const STATUSES = [self::ACTIVE => 'Active', self::SUSPENDED => 'Suspended', self::ARCHIVED => 'Archived'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'main_branch_price' => 'decimal:2',
            'branch_price' => 'decimal:2',
            'max_branches' => 'integer',
            'licence_expires_on' => 'date',
        ];
    }

    public function invoices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Days until the licence ends: negative when it has ended, null when there is no date. */
    public function daysLeft(?string $today = null): ?int
    {
        if (! $this->licence_expires_on) {
            return null;
        }

        return (int) \Illuminate\Support\Carbon::parse($today ?: now()->toDateString())->startOfDay()->diffInDays($this->licence_expires_on->copy()->startOfDay(), false);
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }
}
