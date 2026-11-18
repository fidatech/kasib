<?php

namespace App\Http\Controllers\Central;

use App\Exceptions\UserError;
use App\Http\Controllers\Controller;
use App\Models\Central\AdminLog;
use App\Models\Central\Signup;
use App\Models\Central\Tenant;
use App\Services\Billing;
use App\Tenancy\TenantManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/** The requests made on the website: the administrator approves one (the business is opened) or rejects it. */
class SignupController extends Controller
{
    public function index(Request $request): View
    {
        $status = array_key_exists((string) $request->query('status'), Signup::STATUSES) ? (string) $request->query('status') : 'pending';

        return view('central.signups', [
            'signups' => Signup::with(['tenant', 'decider'])->where('status', $status)->orderByDesc('id')->paginate(25)->withQueryString(),
            'status' => $status, 'counts' => Signup::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'), 'prices' => Billing::prices(),
        ]);
    }

    /** Approve: the business is opened with the password its owner chose, and the request is closed. */
    public function approve(Request $request, TenantManager $manager): RedirectResponse
    {
        $signup = Signup::findOrFail((int) $request->route('id'));
        if (! $signup->isPending()) {
            throw new UserError(__('This request is already decided.'));
        }
        $request->merge(['slug' => strtolower(trim((string) $request->input('slug', $signup->slug)))]);
        $data = $request->validate([
            'slug' => ['required', 'string', 'regex:/^[a-z][a-z0-9-]{2,39}$/', Rule::notIn(config('tenancy.reserved_slugs')), Rule::unique(Tenant::class, 'slug')],
            'licence_expires_on' => ['nullable', 'date'],
            'max_branches' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);
        $domain = $data['slug'].'.'.config('tenancy.base_domain');
        if (Tenant::where('domain', $domain)->exists()) {
            throw new UserError(__('The address :domain is already used by another business.', ['domain' => $domain]));
        }

        try {
            $tenant = $manager->create([
                'name' => $signup->name, 'name_local' => $signup->name_local, 'slug' => $data['slug'], 'licence_no' => $signup->licence_no,
                'licence_expires_on' => $data['licence_expires_on'] ?? null, 'max_branches' => $data['max_branches'] ?? null, 'plan' => $signup->plan ?: 'starter',
                'owner_name' => $signup->owner_name, 'owner_email' => $signup->owner_email, 'owner_password' => $signup->password,   // already hashed
                'notes' => trim(__('Registered on the website.').' '.$signup->owner_phone.' '.$signup->message),
            ]);
        } catch (Throwable $e) {
            report($e);

            throw new UserError(__('The business could not be created: :reason', ['reason' => $e->getMessage()]));
        }
        $tenant->forceFill(['owner_name' => $signup->owner_name, 'owner_phone' => $signup->owner_phone, 'owner_email' => $signup->owner_email])->save();
        $signup->update(['status' => 'approved', 'slug' => $data['slug'], 'tenant_id' => $tenant->id, 'decided_by' => $request->user('admin')->id, 'decided_at' => now(), 'password' => null]);
        \App\Services\Commissions::attach($tenant, $signup->ref);
        AdminLog::record('created', $tenant, 'Approved the request of the website');

        return redirect()->route('admin.signups.index', ['status' => 'approved'])->with('success', __(':name is ready at :domain.', ['name' => $tenant->name, 'domain' => $tenant->domain]));
    }

    public function reject(Request $request): RedirectResponse
    {
        $signup = Signup::findOrFail((int) $request->route('id'));
        if (! $signup->isPending()) {
            throw new UserError(__('This request is already decided.'));
        }
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $signup->update(['status' => 'rejected', 'reason' => $data['reason'], 'decided_by' => $request->user('admin')->id, 'decided_at' => now(), 'password' => null]);
        AdminLog::record('rejected', null, 'Request of '.$signup->name);

        return back()->with('success', __('The request of :name was rejected.', ['name' => $signup->name]));
    }
}
