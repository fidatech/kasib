<?php

namespace App\Http\Controllers;

use App\Exceptions\UserError;
use App\Models\Branch;
use App\Models\Period;
use App\Services\Closing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Closing the period of the whole business, and the list of periods closed before. */
class ClosingController extends Controller
{
    public function index(): View
    {
        Gate::authorize('settings.view');

        return view('closing.index', [
            'number' => (int) setting('period', '1'),
            'branches' => Branch::withBooks()->orderBy('id')->get()->map(fn (Branch $b) => ['branch' => $b, 'profit' => Closing::open($b)['profit']]),
            'periods' => Period::with('closer')->orderByDesc('number')->get(),
            'canClose' => auth()->user()->can('settings.manage') && current_branch()->isMain(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('settings.manage');
        abort_unless(current_branch()->isMain(), 403);
        $data = $request->validate(['to' => ['array'], 'to.*' => [Rule::in(['retained', 'capital'])], 'sure' => ['accepted']]);
        if (Transaction::where('status', 'pending')->exists()) {
            throw new UserError(__('Transactions still wait for approval. Approve or reject them before closing.'));
        }
        $period = Closing::close($data['to'] ?? [], $request->user());

        return back()->with('success', __('Period :number is closed. Hawala numbers start from 1 again.', ['number' => $period->number]));
    }
}
