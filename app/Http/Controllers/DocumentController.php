<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The document vault: the papers of the business itself (licences, contracts, identity cards),
 * kept outside the public folder, with the day each one expires.
 */
class DocumentController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('settings.view');
        $category = $request->query('category');
        $term = trim((string) $request->query('q', ''));

        return view('documents.index', [
            'documents' => Document::with('creator')->when(isset(Document::CATEGORIES[$category]), fn ($q) => $q->where('category', $category))
                ->when($term !== '', fn ($q) => $q->where('title', 'like', '%'.$term.'%'))->orderByRaw('case when expires_on is null then 1 else 0 end')->orderBy('expires_on')->orderByDesc('id')->paginate(per_page())->withQueryString(),
            'categories' => Document::CATEGORIES, 'term' => $term,
            'canManage' => $request->user()->can('settings.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('settings.manage');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'], 'category' => ['required', Rule::in(array_keys(Document::CATEGORIES))],
            'expires_on' => ['nullable', 'date'], 'notes' => ['nullable', 'string', 'max:500'],
            'file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx'],
        ]);
        $file = $data['file'];
        unset($data['file']);
        $document = Document::create($data + [
            'path' => $file->store('documents/'.tenant()->id, 'local'), 'name' => mb_substr($file->getClientOriginalName(), 0, 200),
            'mime' => $file->getMimeType(), 'size' => (int) $file->getSize(), 'created_by' => $request->user()->id,
        ]);
        audit('created', null, 'Document '.$document->title);

        return back()->with('success', __('The document is in the vault.'));
    }

    public function show(Request $request): Response
    {
        Gate::authorize('settings.view');
        $document = Document::findOrFail((int) $request->route('id'));
        abort_unless(Storage::disk('local')->exists($document->path), 404);
        audit('viewed', null, 'Document '.$document->title);

        return response(Storage::disk('local')->get($document->path), 200, [
            'Content-Type' => $document->mime ?: 'application/octet-stream',
            'Content-Disposition' => (str_starts_with((string) $document->mime, 'image/') || $document->mime === 'application/pdf' ? 'inline' : 'attachment').'; filename="'.addcslashes($document->name, '"\\').'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Gate::authorize('settings.manage');
        $document = Document::findOrFail((int) $request->route('id'));
        Storage::disk('local')->delete($document->path);
        $document->delete();
        audit('deleted', null, 'Document '.$document->title);

        return back()->with('success', __(':name deleted.', ['name' => $document->title]));
    }
}
