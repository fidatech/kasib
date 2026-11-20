<?php

namespace App\Http\Middleware;

use App\Services\Billing;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** A business on Starter is shown the upgrade page instead of a page of the Pro plan (Billing::PRO_PAGES). */
class ProPages
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Billing::allows(tenant(), $request->route()?->getName())) {
            return $request->expectsJson()
                ? response()->json(['message' => __('This belongs to the Pro plan.')], 403)
                : response()->view('service.upgrade', ['plans' => Billing::plans()], 403);
        }

        return $next($request);
    }
}
