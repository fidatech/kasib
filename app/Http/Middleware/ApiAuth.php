<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\Branch;
use App\Services\Billing;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The door of the connection: another system (SarafBook) shows a key of this business in the
 * "Authorization: Bearer" header. The key works in the books of one branch, and what it records
 * is written under the name of the user who made the key.
 */
class ApiAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');
        if (! tenant()) {
            return response()->json(['message' => 'Use the address of the business, not the address of the system.'], 404);
        }
        if (Billing::plan(tenant()) !== 'pro') {
            return response()->json(['message' => 'The connection belongs to the Pro plan.'], 403);
        }
        $key = ApiKey::match($request->bearerToken());
        if (! $key) {
            return response()->json(['message' => 'The key is missing, wrong or switched off.'], 401);
        }
        if (! $request->isMethodSafe() && ! $key->can_write) {
            return response()->json(['message' => 'This key may only read.'], 403);
        }
        $branch = $key->branch_id ? Branch::withBooks()->where('is_active', true)->find($key->branch_id) : Branch::main();
        if (! $branch) {
            return response()->json(['message' => 'The branch of this key is closed.'], 403);
        }
        if ($key->last_used_at === null || $key->last_used_at->lt(now()->subMinute())) {
            $key->forceFill(['last_used_at' => now()])->saveQuietly();
        }
        if ($key->user && $key->user->is_active) {
            Auth::guard('web')->setUser($key->user);
        }
        $request->attributes->set('api_key', $key);

        return Branch::as($branch, fn () => $next($request));
    }
}
