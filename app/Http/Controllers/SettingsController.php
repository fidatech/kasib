<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        Gate::authorize('settings.view');

        return view('settings.index', ['fonts' => array_map(fn (array $font) => $font['name'], config('fonts'))]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('settings.manage');

        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:150'],
            'company_name_local' => ['nullable', 'string', 'max:150'],
            'company_phone' => ['nullable', 'string', 'max:50'],
            'company_email' => ['nullable', 'email', 'max:150'],
            'company_address' => ['nullable', 'string', 'max:255'],
            'calendar' => ['sometimes', 'required', Rule::in(['shamsi', 'gregorian'])],
            'font_fa' => ['nullable', Rule::in(array_keys(config('fonts')))],
            'font_ps' => ['nullable', Rule::in(array_keys(config('fonts')))],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'remove_logo' => ['nullable', 'boolean'],
            'simple_mode' => ['nullable', 'boolean'],
            'backdate_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
        ]);
        $data['simple_mode'] = $request->boolean('simple_mode') ? '1' : '0';

        // the logo is kept in the business's own folder, outside the public folder
        $logo = $data['logo'] ?? null;
        $remove = ! empty($data['remove_logo']);
        unset($data['logo'], $data['remove_logo']);
        if ($logo || $remove) {
            if ($old = setting('company_logo')) {
                Storage::disk('local')->delete($old);
            }
            $data['company_logo'] = $logo ? $logo->storeAs('logos/'.tenant()->id, 'logo-'.time().'.'.$logo->extension(), 'local') : null;
        }

        foreach ($data as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value === null ? null : (string) $value]);
        }
        app()->forgetInstance('erp.settings');
        audit('updated', null, 'Settings');

        return redirect()->route('settings.index')->with('success', __('Settings saved.'));
    }

    /** The logo of the business, for the menu, the sign-in page and printed papers. */
    public function logo(): \Symfony\Component\HttpFoundation\Response
    {
        $path = setting('company_logo');
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response(Storage::disk('local')->get($path), 200, [
            'Content-Type' => Storage::disk('local')->mimeType($path) ?: 'image/png',
            'Cache-Control' => 'public, max-age=604800',   // its address changes with the logo (?v=...)
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
