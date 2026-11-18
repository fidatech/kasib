<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Central\SettingsController;
use App\Models\Central\Signup;
use App\Models\Central\Tenant;
use App\Services\Billing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * The public website on the central address: what the system is, what it costs, and the form a
 * business asks for it with. A request waits for the administrator; nothing is opened by itself.
 */
class SiteController extends Controller
{
    public function home(): View
    {
        return view('site.home', ['prices' => Billing::prices(), 'company' => SettingsController::company(), 'trial' => Billing::trialDays(), 'plans' => Billing::plans()]);
    }

    public function register(): View
    {
        return view('site.register', ['prices' => Billing::prices(), 'company' => SettingsController::company(), 'trial' => Billing::trialDays(), 'plans' => Billing::plans(),
            'provinces' => \App\Models\Central\Province::with('cities')->orderBy('sort')->get()]);
    }

    /** Asked by the form while the visitor types: is this address free? */
    public function check(Request $request): \Illuminate\Http\JsonResponse
    {
        $slug = strtolower(trim((string) $request->query('slug')));
        if (! preg_match('/^[a-z][a-z0-9-]{2,39}$/', $slug)) {
            return response()->json(['free' => false, 'message' => __('The address starts with a letter and has 3 to 40 small letters, digits or dashes.')]);
        }
        $taken = in_array($slug, config('tenancy.reserved_slugs'), true) || Tenant::where('slug', $slug)->exists() || Signup::where('slug', $slug)->where('status', 'pending')->exists();

        return response()->json(['free' => ! $taken, 'message' => $taken ? __('This address is taken; choose another.') : __('This address is free.')]);
    }

    public function store(Request $request): RedirectResponse
    {
        // a box people do not see: only a robot fills it
        if (filled($request->input('website'))) {
            return redirect('/');
        }
        $request->merge(['slug' => strtolower(trim((string) $request->input('slug')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'name_local' => ['nullable', 'string', 'max:150'],
            'slug' => ['required', 'string', 'regex:/^[a-z][a-z0-9-]{2,39}$/', Rule::notIn(config('tenancy.reserved_slugs')), Rule::unique(Tenant::class, 'slug'),
                Rule::unique(Signup::class, 'slug')->where('status', 'pending')],
            'licence_no' => ['nullable', 'string', 'max:60'],
            'province' => ['nullable', 'string', 'max:100', Rule::exists(\App\Models\Central\Province::class, 'name')],
            'city' => ['nullable', 'string', 'max:100'],
            'branches' => ['nullable', 'integer', 'min:0', 'max:500'],
            'plan' => ['nullable', Rule::in(array_keys(Billing::PLANS))],
            'ref' => ['nullable', 'string', 'max:60'],
            'owner_name' => ['required', 'string', 'max:150'],
            'owner_email' => ['required', 'email', 'max:190'],
            'owner_phone' => ['required', 'string', 'min:7', 'max:40'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'message' => ['nullable', 'string', 'max:1000'],
        ], ['slug.regex' => __('The address starts with a letter and has 3 to 40 small letters, digits or dashes.'), 'slug.unique' => __('This address is taken; choose another.'),
            'slug.not_in' => __('This address is taken; choose another.')]);

        $signup = Signup::create(['token' => Str::lower(Str::random(32)), 'password' => Hash::make($data['password']), 'branches' => (int) ($data['branches'] ?? 0),
            'status' => 'pending', 'ip' => $request->ip(), 'plan' => $data['plan'] ?? 'starter'] + collect($data)->except(['password', 'branches', 'plan'])->all());

        $this->openTrial($signup, $request);

        return redirect()->route('site.status', ['token' => $signup->token]);
    }

    /**
     * The free trial: when it is switched on, the business is opened at once, with a licence
     * that ends after the days of the trial. Not more than three from one address in a day;
     * whatever cannot be opened by itself waits for the administrator like any request.
     */
    private function openTrial(Signup $signup, Request $request): void
    {
        $days = Billing::trialDays();
        if ($days === null || Signup::where('ip', $request->ip())->where('created_at', '>=', now()->subDay())->count() > 3) {
            return;
        }
        try {
            $tenant = app(\App\Tenancy\TenantManager::class)->create([
                'name' => $signup->name, 'name_local' => $signup->name_local, 'slug' => $signup->slug, 'licence_no' => $signup->licence_no,
                'licence_expires_on' => now()->addDays($days)->toDateString(), 'plan' => $signup->plan ?: 'starter',
                'owner_name' => $signup->owner_name, 'owner_email' => $signup->owner_email, 'owner_password' => $signup->password,   // already hashed
                'notes' => trim(__('Free trial from the website.').' '.$signup->owner_phone.' '.$signup->message),
            ]);
            $tenant->forceFill(['owner_name' => $signup->owner_name, 'owner_phone' => $signup->owner_phone, 'owner_email' => $signup->owner_email])->save();
            \App\Services\Commissions::attach($tenant, $signup->ref);
            $signup->update(['status' => 'approved', 'tenant_id' => $tenant->id, 'decided_at' => now(), 'password' => null]);
            \App\Models\Central\AdminLog::record('created', $tenant, 'Free trial of '.$days.' days, opened from the website');
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** The applicant follows the request here: waiting, approved (with the address) or rejected (with the reason). */
    public function status(Request $request): View
    {
        $signup = Signup::with('tenant')->where('token', (string) $request->route('token'))->firstOrFail();

        return view('site.status', ['signup' => $signup, 'company' => SettingsController::company()]);
    }

    /** "Sign in": a client types the name of its address and is taken to its own sign-in page. */
    public function open(Request $request): RedirectResponse
    {
        $slug = strtolower(trim((string) $request->query('address')));
        $slug = preg_replace('/\..*$/', '', $slug);   // "kabul.primesarafi.com" is fine too
        $tenant = $slug !== '' ? Tenant::where('slug', $slug)->first() : null;
        if (! $tenant) {
            return redirect('/#signin')->with('danger', __('No business has this address. Check the spelling, or register.'));
        }

        return redirect()->away(($request->isSecure() ? 'https://' : 'http://').$tenant->domain.($request->getPort() && ! in_array($request->getPort(), [80, 443], true) ? ':'.$request->getPort() : '').'/login');
    }
}
