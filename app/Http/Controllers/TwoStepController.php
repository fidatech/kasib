<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\QrCode;
use App\Support\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Two-step sign-in. A user who switches it on is asked, after the password, for the six-digit
 * code of an authenticator app on his phone. Each user decides for himself.
 */
class TwoStepController extends Controller
{
    /** How long the code may be typed after the password was accepted. */
    private const MINUTES = 5;

    public function show(Request $request): View
    {
        $user = $request->user();
        $pending = $user->two_factor_secret && ! $user->two_factor_confirmed_at ? Crypt::decryptString($user->two_factor_secret) : null;

        return view('two-step.setup', [
            'on' => (bool) $user->two_factor_confirmed_at,
            'secret' => $pending,
            'qr' => $pending ? QrCode::svg(Totp::uri($pending, $user->email, (string) (setting('company_name') ?: config('app.name'))), 190) : null,
        ]);
    }

    /** Start: a new secret is made and shown; nothing is asked at sign-in until a code confirms it. */
    public function start(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user->two_factor_confirmed_at, 403);
        $user->forceFill(['two_factor_secret' => Crypt::encryptString(Totp::secret()), 'two_factor_confirmed_at' => null])->save();

        return redirect()->route('two-step.show');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate(['code' => ['required', 'string', 'max:10']]);
        abort_unless($user->two_factor_secret && ! $user->two_factor_confirmed_at, 403);
        if (! Totp::verify(Crypt::decryptString($user->two_factor_secret), $data['code'])) {
            throw ValidationException::withMessages(['code' => __('This code is not right. Look at the app again: the code changes every 30 seconds.')]);
        }
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        audit('updated', null, 'Two-step sign-in switched on: '.$user->name);

        return redirect()->route('two-step.show')->with('success', __('Two-step sign-in is on.'));
    }

    public function disable(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $user = $request->user();
        $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();
        audit('updated', null, 'Two-step sign-in switched off: '.$user->name);

        return redirect()->route('two-step.show')->with('success', __('Two-step sign-in is off.'));
    }

    /** The page between the password and the system. */
    public function challenge(Request $request): View|RedirectResponse
    {
        return $this->waiting($request) ? view('two-step.challenge') : redirect()->route('login');
    }

    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:10']]);
        $user = $this->waiting($request);
        if (! $user) {
            return redirect()->route('login');
        }
        if (! Totp::verify(Crypt::decryptString($user->two_factor_secret), $data['code'])) {
            throw ValidationException::withMessages(['code' => __('This code is not right. Look at the app again: the code changes every 30 seconds.')]);
        }

        $remember = (bool) $request->session()->pull('two_step.remember', false);
        $request->session()->forget('two_step');
        Auth::login($user, $remember);
        $request->session()->regenerate();
        audit('signed in', null, $user->name);

        return redirect()->intended(route('dashboard'));
    }

    /** The user whose password was just accepted and who still owes the code, or null. */
    private function waiting(Request $request): ?User
    {
        $id = $request->session()->get('two_step.user');
        $at = (int) $request->session()->get('two_step.at', 0);
        if (! $id || $at < time() - self::MINUTES * 60) {
            $request->session()->forget('two_step');

            return null;
        }

        return User::where('is_active', true)->whereNotNull('two_factor_confirmed_at')->find($id);
    }
}
