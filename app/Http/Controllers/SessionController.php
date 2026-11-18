<?php

namespace App\Http\Controllers;

use App\Models\UserSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Active sessions: the browsers a user is signed in from, with a button to sign any of them
 * out. Those who manage users see the sessions of everyone.
 */
class SessionController extends Controller
{
    public function index(Request $request): View
    {
        // a session nobody used for longer than a sign-in lasts is over
        UserSession::where('last_seen_at', '<', now()->subMinutes((int) config('session.lifetime', 480)))->delete();
        $all = $request->user()->can('users.manage');

        return view('sessions.index', [
            'sessions' => UserSession::with('user')->when(! $all, fn ($q) => $q->where('user_id', $request->user()->id))->orderByDesc('last_seen_at')->limit(500)->get(),
            'current' => UserSession::current($request),
            'all' => $all,
        ]);
    }

    /** Sign one browser out. It notices at its next click. */
    public function destroy(Request $request): RedirectResponse
    {
        $session = UserSession::with('user')->findOrFail((int) $request->route('id'));
        abort_unless((int) $session->user_id === (int) $request->user()->id || $request->user()->can('users.manage'), 403);
        abort_if($session->token === UserSession::current($request), 422, __('Use "Sign out" for the browser you are in.'));
        $session->delete();
        audit('signed out', null, $session->user?->name.' · '.$session->device().' · '.$session->ip);

        return back()->with('success', __('That browser was signed out.'));
    }

    /** Every other browser of the signed-in user. */
    public function others(Request $request): RedirectResponse
    {
        $count = UserSession::where('user_id', $request->user()->id)->where('token', '!=', UserSession::current($request))->delete();
        audit('signed out', null, $request->user()->name.' · '.$count);

        return back()->with('success', __(':count other browsers were signed out.', ['count' => $count]));
    }
}
