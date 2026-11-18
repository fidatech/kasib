<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The keys another system (SarafBook) connects with: make one, see when it was used, switch it off. */
class ConnectionController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('settings.manage');

        return view('service.connections', [
            'keys' => ApiKey::with(['branch', 'user'])->orderByDesc('id')->get(),
            'branches' => Branch::withBooks()->where('is_active', true)->orderBy('id')->get(),
            'base' => $request->getSchemeAndHttpHost().'/api/v1',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('settings.manage');
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'branch_id' => ['nullable', 'integer', Rule::exists(Branch::class, 'id')], 'can_write' => ['nullable', 'boolean']]);
        [$key, $plain] = ApiKey::issue($data['name'], isset($data['branch_id']) ? (int) $data['branch_id'] : Branch::main()->id, $request->boolean('can_write'), $request->user()->id);
        audit('created', null, 'Connection key '.$key->name);

        return redirect()->route('connections.index')->with('new_key', $plain)->with('success', __('The key is made. Copy it now: it is shown only this once.'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Gate::authorize('settings.manage');
        $key = ApiKey::findOrFail((int) $request->route('id'));
        $key->forceFill(['revoked_at' => now()])->save();
        audit('deleted', null, 'Connection key '.$key->name);

        return back()->with('success', __('The key was switched off.'));
    }
}
