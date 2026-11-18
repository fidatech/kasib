<?php

namespace App\Http\Middleware;

use App\Services\Demo;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * The demo is one user shared by every visitor: what would lock the others out or reach outside
 * the demo (its password, its users, backups, messages, keys) is switched off in it.
 */
class DemoGuard
{
    private const LOCKED = ['profile.update', 'password.update', 'users.*', 'roles.*', 'two-step.*', 'backups.*', 'sessions.*', 'whatsapp.*', 'settings.update', 'setup.store',
        'businesses.*', 'connections.*', 'assistant.*', 'service.*', 'schedules.*', 'documents.*'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethodSafe() && Demo::is(tenant()) && Str::is(self::LOCKED, (string) $request->route()?->getName())) {
            $message = __('This is switched off in the demo.');

            return $request->expectsJson() ? response()->json(['message' => $message], 403) : back()->with('warning', $message);
        }

        return $next($request);
    }
}
