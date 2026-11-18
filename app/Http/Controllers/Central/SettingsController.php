<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Central\AdminLog;
use App\Models\Central\CentralSetting;
use App\Services\Licence;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** Who the service belongs to (printed on invoices and receipts), and the rules of licences and backups. */
class SettingsController extends Controller
{
    public const COMPANY = ['company_name' => 'Fida Technologies', 'company_address' => '', 'company_phone' => '', 'company_email' => '', 'company_website' => 'fida.af', 'invoice_note' => ''];

    public static function company(): array
    {
        $out = [];
        foreach (self::COMPANY as $key => $default) {
            $out[$key] = (string) (CentralSetting::read($key) ?? $default);
        }

        return $out;
    }

    public function edit(): View
    {
        return view('central.settings', [
            'company' => self::company(), 'rules' => Licence::rules(), 'keep' => (int) (CentralSetting::read('backup_keep_days') ?: 14),
            'logo' => (bool) CentralSetting::read('logo_path'), 'prices' => \App\Services\Billing::prices(),
            'trial' => ['enabled' => CentralSetting::read('trial_enabled') === '1', 'days' => (int) (CentralSetting::read('trial_days') ?: 14)],
            'hesabpay' => \App\Services\Billing::hesabpay(), 'videos' => (string) CentralSetting::read('help_videos'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:150'], 'company_address' => ['nullable', 'string', 'max:255'], 'company_phone' => ['nullable', 'string', 'max:60'],
            'company_email' => ['nullable', 'email', 'max:190'], 'company_website' => ['nullable', 'string', 'max:190'], 'invoice_note' => ['nullable', 'string', 'max:1000'],
            'remind_days' => ['required', 'integer', 'min:0', 'max:180'], 'grace_days' => ['required', 'integer', 'min:0', 'max:90'], 'auto_lock' => ['nullable', 'boolean'],
            'backup_keep_days' => ['required', 'integer', 'min:1', 'max:365'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'trial_enabled' => ['nullable', 'boolean'], 'trial_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'hesabpay_number' => ['nullable', 'string', 'max:40'], 'hesabpay_name' => ['nullable', 'string', 'max:150'], 'afn_rate' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'hesabpay_qr' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'help_videos' => ['nullable', 'string', 'max:5000'],
        ] + array_fill_keys(array_keys(\App\Services\Billing::DEFAULTS), ['nullable', 'numeric', 'min:0', 'max:1000000']));
        // the four prices of the service
        foreach (array_keys(\App\Services\Billing::DEFAULTS) as $key) {
            if (isset($data[$key])) {
                CentralSetting::put('price_'.$key, \App\Support\Num::money($data[$key]));
            }
        }
        foreach (array_keys(self::COMPANY) as $key) {
            CentralSetting::put($key, (string) ($data[$key] ?? ''));
        }
        CentralSetting::put('remind_days', (string) $data['remind_days']);
        CentralSetting::put('grace_days', (string) $data['grace_days']);
        CentralSetting::put('auto_lock', $request->boolean('auto_lock') ? '1' : '0');
        CentralSetting::put('backup_keep_days', (string) $data['backup_keep_days']);
        CentralSetting::put('trial_enabled', $request->boolean('trial_enabled') ? '1' : '0');
        CentralSetting::put('trial_days', (string) ($data['trial_days'] ?? 14));
        foreach (['hesabpay_number', 'hesabpay_name', 'afn_rate', 'help_videos'] as $key) {
            CentralSetting::put($key, (string) ($data[$key] ?? ''));
        }
        if ($request->hasFile('hesabpay_qr')) {
            if ($old = CentralSetting::read('hesabpay_qr')) {
                Storage::disk('local')->delete($old);
            }
            CentralSetting::put('hesabpay_qr', $request->file('hesabpay_qr')->store('central', 'local'));
        }
        if ($request->hasFile('logo')) {
            if ($old = CentralSetting::read('logo_path')) {
                Storage::disk('local')->delete($old);
            }
            CentralSetting::put('logo_path', $request->file('logo')->store('central', 'local'));
        }
        Licence::check();
        AdminLog::record('updated', null, 'Settings');

        return back()->with('success', __('Saved.'));
    }

    public function logo(): Response
    {
        $path = CentralSetting::read('logo_path');
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response(Storage::disk('local')->get($path), 200, ['Content-Type' => Storage::disk('local')->mimeType($path) ?: 'image/png', 'Cache-Control' => 'private, max-age=3600']);
    }
}
