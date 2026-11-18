<?php

namespace App\Http\Middleware;

use App\Models\Central\Tenant;
use App\Tenancy\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chooses the business from the address of the request. A central host opens the system
 * administrator panel; any other host must be the domain of a business.
 */
class InitializeTenancy
{
    public function __construct(private Tenancy $tenancy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());

        if (in_array($host, config('tenancy.central_domains'), true)) {
            if ($this->tenancy->tenant) {
                $this->tenancy->end();
            }

            return $next($request);
        }

        // the list of businesses is in the central database, whatever was open before
        if ($this->tenancy->tenant) {
            $this->tenancy->end();
        }
        $tenant = Tenant::where('domain', $host)->first();
        abort_if(! $tenant, 404);

        if (! $tenant->isActive() && $tenant->locked_reason === 'licence') {
            return $this->locked($request, $tenant);
        }
        if (! $tenant->isActive()) {
            return response()->view('errors.suspended', ['name' => $tenant->name, 'licence' => $tenant->locked_reason === 'licence', 'company' => \App\Http\Controllers\Central\SettingsController::company()], 503);
        }

        $this->tenancy->initialize($tenant);

        return $next($request);
    }

    /** The page of a business whose licence ended: what happened, where to pay, and the form "we paid". */
    private function locked(Request $request, Tenant $tenant): Response
    {
        $sent = false;
        $errors = [];
        $limit = 'paid:'.$tenant->id.':'.$request->ip();
        if ($request->isMethod('post') && $request->is('paid') && \Illuminate\Support\Facades\RateLimiter::tooManyAttempts($limit, 6)) {
            $errors = [__('Too many attempts. Please try again later.')];
        } elseif ($request->isMethod('post') && $request->is('paid')) {
            \Illuminate\Support\Facades\RateLimiter::hit($limit, 3600);
            $check = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'amount' => ['required', 'numeric', 'gt:0', 'max:10000000'], 'reference' => ['required', 'string', 'min:3', 'max:120'],
                'picture' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp'], 'sender' => ['nullable', 'string', 'max:150'],
            ]);
            $errors = $check->errors()->all();
            if (! $errors) {
                try {
                    \App\Services\PaymentRequests::submit($tenant, $request->input('amount'), (string) $request->input('reference'), $request->file('picture'), $request->input('sender'));
                    $sent = true;
                } catch (\App\Exceptions\UserError $e) {
                    $errors = [$e->getMessage()];
                }
            }
        }

        return response()->view('errors.suspended', ['name' => $tenant->name, 'licence' => true, 'company' => \App\Http\Controllers\Central\SettingsController::company(),
            'pay' => \App\Http\Controllers\ServiceController::payData($tenant), 'sent' => $sent, 'problems' => $errors], $sent ? 200 : 503);
    }
}
