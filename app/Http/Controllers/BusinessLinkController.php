<?php

namespace App\Http\Controllers;

use App\Models\Central\LinkedLogin;
use App\Models\Central\Tenant;
use App\Models\User;
use App\Support\Totp;
use App\Tenancy\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Several businesses, one login. A person who has a user in another business links it here by
 * proving it is his: its address, its email and its password (and the code of the phone when
 * that user has two-step sign-in). From then on he opens the other business from this page
 * without signing in again. A link is between two users of two businesses and nothing else:
 * nobody gets into a business he has no user in.
 */
class BusinessLinkController extends Controller
{
    public function index(Request $request): View
    {
        return view('service.businesses', ['links' => $this->others($request)]);
    }

    public function store(Request $request, Tenancy $tenancy): RedirectResponse
    {
        $data = $request->validate(['address' => ['required', 'string', 'max:190'], 'email' => ['required', 'email', 'max:190'], 'password' => ['required', 'string', 'max:200'], 'code' => ['nullable', 'string', 'max:10']]);
        $slug = preg_replace('/\..*$/', '', strtolower(trim($data['address'])));
        $other = Tenant::where('status', Tenant::ACTIVE)->where(fn ($q) => $q->where('slug', $slug)->orWhere('domain', strtolower(trim($data['address']))))->first();
        $wrong = fn () => back()->withErrors(['address' => __('The address, the email or the password is not right.')])->withInput($request->only('address', 'email'));
        if (! $other || (int) $other->id === (int) tenant()->id) {
            return $wrong();
        }
        // the proof is checked in the books of the other business
        $found = $tenancy->run($other, function () use ($data) {
            $user = User::where('email', strtolower($data['email']))->where('is_active', true)->first();
            if (! $user || ! Hash::check($data['password'], $user->password)) {
                return null;
            }
            if ($user->two_factor_confirmed_at && ! Totp::verify(Crypt::decryptString($user->two_factor_secret), (string) ($data['code'] ?? ''))) {
                return 'code';
            }

            return ['id' => (int) $user->id, 'email' => $user->email];
        });
        if ($found === 'code') {
            return back()->withErrors(['code' => __('That user has two-step sign-in. Write the code of its phone.')])->withInput($request->only('address', 'email'));
        }
        if (! $found) {
            return $wrong();
        }

        DB::connection((new LinkedLogin)->getConnectionName())->transaction(function () use ($request, $other, $found) {
            $mine = LinkedLogin::firstOrNew(['tenant_id' => tenant()->id, 'user_id' => $request->user()->id]);
            $theirs = LinkedLogin::firstOrNew(['tenant_id' => $other->id, 'user_id' => $found['id']]);
            $group = $mine->group ?: ($theirs->group ?: (string) Str::uuid());
            // two groups that turn out to be one person become one
            if ($theirs->exists && $theirs->group !== $group) {
                LinkedLogin::where('group', $theirs->group)->update(['group' => $group]);
            }
            $mine->fill(['group' => $group, 'email' => $request->user()->email])->save();
            $theirs->fill(['group' => $group, 'email' => $found['email']])->save();
        });
        audit('linked', null, 'Business '.$other->name.' ('.$found['email'].')');

        return redirect()->route('businesses.index')->with('success', __(':name is linked. Open it from this page any time.', ['name' => $other->name]));
    }

    /** Go to a linked business: a pass that is good once, for a minute, is handed to its address. */
    public function open(Request $request): RedirectResponse
    {
        $link = $this->others($request)->firstWhere('id', (int) $request->route('id')) ?? abort(404);
        abort_unless($link->tenant && $link->tenant->isActive(), 404);
        $token = Str::random(48);
        DB::connection((new LinkedLogin)->getConnectionName())->table('login_tokens')->insert(['token' => hash('sha256', $token), 'tenant_id' => $link->tenant_id, 'user_id' => $link->user_id, 'expires_at' => now()->addMinute()]);
        audit('opened', null, 'Business '.$link->tenant->name);
        $port = $request->getPort() && ! in_array($request->getPort(), [80, 443], true) ? ':'.$request->getPort() : '';

        return redirect()->away(($request->isSecure() ? 'https://' : 'http://').$link->tenant->domain.$port.'/enter/'.$token);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $link = $this->others($request)->firstWhere('id', (int) $request->route('id')) ?? abort(404);
        $link->delete();
        audit('unlinked', null, 'Business '.$link->tenant?->name);

        return back()->with('success', __('The link was removed.'));
    }

    /** The other side: the pass is checked and its user is signed in. */
    public function enter(Request $request): RedirectResponse
    {
        $central = DB::connection((new LinkedLogin)->getConnectionName());
        $hash = hash('sha256', (string) $request->route('token'));
        $pass = $central->table('login_tokens')->where('token', $hash)->first();
        $central->table('login_tokens')->where('token', $hash)->orWhere('expires_at', '<', now())->delete();
        $user = $pass && (int) $pass->tenant_id === (int) tenant()->id && now()->lt($pass->expires_at) ? User::where('is_active', true)->find($pass->user_id) : null;
        if (! $user) {
            return redirect()->route('login')->with('warning', __('That pass is no longer good. Sign in here, or open the business again from the other one.'));
        }
        Auth::login($user);
        $request->session()->regenerate();
        audit('signed in', null, $user->email.' (from a linked business)');

        return redirect()->route('dashboard');
    }

    /** The users of other businesses that belong to the same person as the signed-in user. */
    private function others(Request $request)
    {
        $mine = LinkedLogin::where('tenant_id', tenant()->id)->where('user_id', $request->user()->id)->first();

        return $mine ? LinkedLogin::with('tenant')->where('group', $mine->group)->where('id', '!=', $mine->id)->orderBy('id')->get() : collect();
    }
}
