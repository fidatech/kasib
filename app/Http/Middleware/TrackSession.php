<?php

namespace App\Http\Middleware;

use App\Models\UserSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the list of browsers a user of a business is signed in from. A session that was on the
 * list and is not any more was signed out from another browser: it ends at its next request.
 */
class TrackSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! tenant() || ! Auth::guard('web')->check() || ! $request->hasSession()) {
            return $next($request);
        }
        $user = Auth::guard('web')->user();
        $token = UserSession::current($request);
        $row = $token === '' ? null : UserSession::where('token', $token)->first();

        if ($token !== '' && ! $row) {
            // it was listed, and somebody removed it: signed out from elsewhere
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson() ? response()->json(['message' => __('You were signed out.')], 401) : redirect()->route('login')->with('warning', __('You were signed out from another browser.'));
        }
        if (! $row || (int) $row->user_id !== (int) $user->id) {
            $key = \Illuminate\Support\Str::random(40);
            UserSession::create(['user_id' => $user->id, 'token' => UserSession::token($key), 'ip' => $request->ip(), 'agent' => mb_substr((string) $request->userAgent(), 0, 255), 'last_seen_at' => now()]);
            $request->session()->put('tracked', $key);
        } elseif ($row->last_seen_at === null || $row->last_seen_at->lt(now()->subMinute())) {
            $row->update(['last_seen_at' => now(), 'ip' => $request->ip()]);
        }

        return $next($request);
    }
}
