<?php

namespace App\Tenancy;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * The account the support staff of Fida Technologies work as inside a business, when its owner
 * asked for help. They come in from the administration panel with a ticket for one minute and
 * the reason they gave; nobody can sign in to this user with a password, and every entry is
 * written in the audit log of the business.
 */
class Support
{
    public const EMAIL = 'support@fida.af';

    /** A seal nobody else can make: what the ticket is for, where, who, until when. */
    public static function seal(int $tenantId, int $personId, int $expires, string $what = 'support'): string
    {
        return hash_hmac('sha256', $what.'|'.$tenantId.'|'.$personId.'|'.$expires, (string) config('app.key'));
    }

    public static function user(): User
    {
        $user = User::firstOrNew(['email' => self::EMAIL]);
        if (! $user->exists) {
            $user->password = Str::random(48);
        }
        $user->forceFill(['name' => 'Fida Support', 'is_active' => true, 'branch_id' => Branch::main()->id])->save();
        $user->syncRoles([User::ADMIN_ROLE]);

        return $user;
    }
}
