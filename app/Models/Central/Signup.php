<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A request for the system made on the website, waiting for the administrator or decided. */
class Signup extends Model
{
    use UsesCentralConnection;

    public const STATUSES = ['pending' => 'Waiting', 'approved' => 'Approved', 'rejected' => 'Rejected'];

    protected $guarded = [];

    protected $hidden = ['password'];

    protected $casts = ['decided_at' => 'datetime', 'branches' => 'integer'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'decided_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /** The address the business asked for. */
    public function domain(): string
    {
        return $this->slug.'.'.config('tenancy.base_domain');
    }
}
