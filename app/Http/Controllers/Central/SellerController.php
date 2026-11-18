<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Central\AdminLog;
use App\Models\Central\CentralSetting;
use App\Models\Central\Commission;
use App\Models\Central\Seller;
use App\Support\Num;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Sellers and what they earned; the rewards of clients who referred other clients. */
class SellerController extends Controller
{
    public function index(Request $request): View
    {
        $open = $request->query('show') !== 'paid';

        return view('central.sellers', [
            'sellers' => Seller::withCount('tenants')->orderBy('name')->get(),
            'commissions' => Commission::with(['seller', 'referrer', 'tenant', 'payment'])->whereNull('cancelled_at')->when($open, fn ($q) => $q->whereNull('paid_on'), fn ($q) => $q->whereNotNull('paid_on'))->orderByDesc('id')->paginate(50)->withQueryString(),
            'open' => $open, 'owed' => Num::money(Commission::whereNull('cancelled_at')->whereNull('paid_on')->sum('amount')), 'reward' => (string) (CentralSetting::read('referral_reward') ?: '0'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['code' => strtolower(trim((string) $request->input('code')))]);
        $data = $request->validate($this->rules(null));
        $seller = Seller::create($data + ['is_active' => true]);
        AdminLog::record('created', null, 'Seller '.$seller->name);

        return back()->with('success', __(':name saved.', ['name' => $seller->name]));
    }

    public function update(Request $request): RedirectResponse
    {
        $seller = Seller::findOrFail((int) $request->route('id'));
        $request->merge(['code' => strtolower(trim((string) $request->input('code')))]);
        $seller->update($request->validate($this->rules($seller)) + ['is_active' => $request->boolean('is_active')]);
        AdminLog::record('updated', null, 'Seller '.$seller->name);

        return back()->with('success', __(':name saved.', ['name' => $seller->name]));
    }

    /** The reward of a client whose referral paid for the first time. */
    public function reward(Request $request): RedirectResponse
    {
        $data = $request->validate(['referral_reward' => ['required', 'numeric', 'min:0', 'max:100000']]);
        CentralSetting::put('referral_reward', Num::money($data['referral_reward']));
        AdminLog::record('updated', null, 'Referral reward '.$data['referral_reward']);

        return back()->with('success', __('Saved.'));
    }

    /** The company paid a commission out. */
    public function paid(Request $request): RedirectResponse
    {
        $commission = Commission::whereNull('cancelled_at')->whereNull('paid_on')->findOrFail((int) $request->route('id'));
        $commission->update(['paid_on' => now()->toDateString()]);
        AdminLog::record('commission paid', $commission->tenant, '$'.$commission->amount.' '.($commission->seller?->name ?? $commission->referrer?->name));

        return back()->with('success', __('Saved.'));
    }

    private function rules(?Seller $seller): array
    {
        return [
            'name' => ['required', 'string', 'max:150'], 'code' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9-]+$/', Rule::unique(Seller::class, 'code')->ignore($seller?->id)],
            'phone' => ['nullable', 'string', 'max:50'], 'email' => ['nullable', 'email', 'max:190'],
            'first_percent' => ['required', 'numeric', 'min:0', 'max:100'], 'renewal_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
