<?php

namespace App\Http\Controllers;

use App\Exceptions\UserError;
use App\Models\Setting;
use App\Services\Whatsapp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** WhatsApp messages: the sender of the business, the messages it sends, and a test. */
class WhatsappController extends Controller
{
    private const KEYS = ['whatsapp_url', 'whatsapp_token', 'whatsapp_phone_field', 'whatsapp_message_field', 'whatsapp_country', 'whatsapp_tpl_money_in', 'whatsapp_tpl_money_out', 'whatsapp_tpl_paper', 'whatsapp_tpl_due'];

    public function index(): View
    {
        Gate::authorize('settings.view');

        return view('settings.whatsapp', ['templates' => Whatsapp::TEMPLATES, 'canManage' => auth()->user()->can('settings.manage')]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('settings.manage');
        $data = $request->validate([
            'whatsapp_url' => ['nullable', 'url', 'max:500', 'required_if:whatsapp_enabled,1'],
            'whatsapp_token' => ['nullable', 'string', 'max:500'],
            'whatsapp_phone_field' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_]+$/'],
            'whatsapp_message_field' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_]+$/'],
            'whatsapp_country' => ['required', 'digits_between:1,4'],
            'whatsapp_tpl_money_in' => ['nullable', 'string', 'max:1000'],
            'whatsapp_tpl_money_out' => ['nullable', 'string', 'max:1000'],
            'whatsapp_tpl_paper' => ['nullable', 'string', 'max:1000'],
            'whatsapp_tpl_due' => ['nullable', 'string', 'max:1000'],
        ]);
        // an empty key box keeps the key that is there
        if (! filled($data['whatsapp_token'] ?? null) && ! $request->boolean('clear_token')) {
            unset($data['whatsapp_token']);
        }
        $data['whatsapp_enabled'] = $request->boolean('whatsapp_enabled') ? '1' : '0';
        foreach ($data as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value === null ? null : (string) $value]);
        }
        app()->forgetInstance('erp.settings');
        audit('updated', null, 'WhatsApp messages');

        return redirect()->route('whatsapp.index')->with('success', __('Settings saved.'));
    }

    /** One message to a number of the business itself, to see that the sender answers. */
    public function test(Request $request): RedirectResponse
    {
        Gate::authorize('settings.manage');
        $data = $request->validate(['phone' => ['required', 'string', 'max:30']]);
        if (! Whatsapp::enabled()) {
            throw new UserError(__('Switch the messages on and write the address of the sender first.'));
        }
        if (! Whatsapp::send($data['phone'], __('This is a test message from :name.', ['name' => setting('company_name') ?: config('app.name')]))) {
            throw new UserError(__('The message was not sent. Check the address, the key and the names of the fields; the reason is in the log of the system.'));
        }

        return back()->with('success', __('The test message was sent.'));
    }
}
