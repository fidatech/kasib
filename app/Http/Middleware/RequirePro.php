<?php

namespace App\Http\Middleware;

use App\Services\Billing;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** The pages of version 2 (sales, purchases, stock ...) belong to the Pro plan. A Starter business is shown what Pro adds. */
class RequirePro
{
    public function handle(Request $request, Closure $next): Response
    {
        if (tenant() && Billing::plan(tenant()) !== 'pro') {
            return response()->view('service.upgrade', ['plans' => Billing::plans()], 403);
        }

        return $next($request);
    }
}
