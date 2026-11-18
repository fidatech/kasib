<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** A key another system connects with. Only its fingerprint is kept; the key is shown once. */
class ApiKey extends Model
{
    protected $guarded = [];

    protected $casts = ['can_write' => 'boolean', 'last_used_at' => 'datetime', 'revoked_at' => 'datetime'];

    protected $hidden = ['token'];

    /** @return array{0: self, 1: string} the record and the key to hand over */
    public static function issue(string $name, ?int $branchId, bool $write, ?int $userId): array
    {
        $plain = 'ksb_'.Str::random(44);
        $key = static::create(['name' => $name, 'prefix' => substr($plain, 0, 10), 'token' => hash('sha256', $plain), 'branch_id' => $branchId, 'user_id' => $userId, 'can_write' => $write]);

        return [$key, $plain];
    }

    public static function match(?string $plain): ?self
    {
        return filled($plain) ? static::whereNull('revoked_at')->where('token', hash('sha256', (string) $plain))->first() : null;
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
