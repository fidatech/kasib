<?php

namespace App\Http\Controllers;

use App\Models\AssistantQuestion;
use App\Services\Assistant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Ask the books a question in plain words. */
class AssistantController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('reports.view');

        return view('service.assistant', [
            'ready' => Assistant::ready(),
            'questions' => AssistantQuestion::where('user_id', $request->user()->id)->orderByDesc('id')->limit(20)->get(),
            'left' => max(0, Assistant::limit() - AssistantQuestion::where('created_at', '>=', now()->startOfDay())->count()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('reports.view');
        $data = $request->validate(['question' => ['required', 'string', 'min:3', 'max:1000']]);
        Assistant::ask($request->user(), trim($data['question']));

        return redirect()->route('assistant.index');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Gate::authorize('reports.view');
        AssistantQuestion::where('user_id', $request->user()->id)->delete();

        return back()->with('success', __('The questions were cleared.'));
    }
}
