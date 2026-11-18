<?php

namespace App\Http\Controllers;

use App\Services\Summary;
use App\Services\Whatsapp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Alerts & Summary: what the owner is told about, when, and on which number. */
class OwnerController extends Controller
{
    public function index(): View
    {
        Gate::authorize('settings.view');

        return view('settings.owner', ['summary' => Summary::evening(), 'confirm' => Summary::CONFIRM, 'canManage' => auth()->user()->can('settings.manage')]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('settings.manage');
        $data = $request->validate([
            'owner_phone' => ['nullable', 'string', 'max:50'],
            'owner_locale' => ['nullable', 'in:en,fa,ps'],
            'alert_large' => ['nullable', 'numeric', 'min:0'],
            'alert_backdate' => ['required', 'integer', 'min:0', 'max:3650'],
            'alert_from' => ['nullable', 'integer', 'min:0', 'max:23', 'required_with:alert_to'],
            'alert_to' => ['nullable', 'integer', 'min:1', 'max:24', 'required_with:alert_from', 'gt:alert_from'],
            'summary_hour' => ['required', 'integer', 'min:0', 'max:23'],
            'confirm_day' => ['required', 'integer', 'min:0', 'max:28'],
            'whatsapp_tpl_confirm' => ['nullable', 'string', 'max:1000'],
        ]);
        foreach (['alerts_enabled', 'alerts_whatsapp', 'summary_enabled'] as $switch) {
            $data[$switch] = $request->boolean($switch) ? '1' : '0';
        }
        foreach ($data as $key => $value) {
            Summary::remember($key, $value === null ? null : (string) $value);
        }
        audit('updated', null, 'Alerts & Summary');

        return redirect()->route('owner.index')->with('success', __('Settings saved.'));
    }

    /** Send the evening summary now, to see what it looks like. */
    public function summary(): RedirectResponse
    {
        Gate::authorize('settings.manage');
        if (! Whatsapp::enabled() || ! filled(setting('owner_phone'))) {
            return back()->with('error', __('Switch the WhatsApp messages on and write the number of the owner first.'));
        }

        return Summary::sendEvening() ? back()->with('success', __('The summary was sent.')) : back()->with('error', __('The message could not be sent. Check the address and the key of the sender.'));
    }
}
