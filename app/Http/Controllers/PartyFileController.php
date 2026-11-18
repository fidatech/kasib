<?php

namespace App\Http\Controllers;

use App\Models\AccountGroup;
use App\Models\Attachment;
use App\Models\Party;
use App\Models\PartyNote;
use App\Models\Voucher;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * What is kept beside the money: the notes of an account ("he will pay on Thursday", with a
 * day to be reminded), and the photos and PDFs attached to an account or to a paper.
 */
class PartyFileController extends Controller
{
    /** The notes of one account, and the form of a new one. */
    public function notes(Request $request): View
    {
        $party = $this->party($request);
        abort_unless(Permissions::group($request->user(), $party->group->key, 'view'), 403);

        return view('accounts.notes', [
            'party' => $party, 'group' => $party->group,
            'notes' => PartyNote::with('creator')->where('party_id', $party->id)->orderByRaw('case when done_at is null then 0 else 1 end')->orderByDesc('id')->limit(500)->get(),
            'canManage' => Permissions::group($request->user(), $party->group->key, 'manage'),
        ]);
    }

    public function storeNote(Request $request): RedirectResponse
    {
        $party = $this->party($request);
        abort_unless(Permissions::group($request->user(), $party->group->key, 'manage'), 403);
        $data = $request->validate(['note' => ['required', 'string', 'max:500'], 'remind_on' => ['nullable', 'date']]);
        PartyNote::create($data + ['party_id' => $party->id, 'created_by' => $request->user()->id]);

        return back()->with('success', __('Saved.'));
    }

    /** Done, or not done after all. */
    public function toggleNote(Request $request): RedirectResponse
    {
        Gate::authorize('accounts.manage');
        $note = PartyNote::findOrFail((int) $request->route('note'));
        $note->update(['done_at' => $note->done_at ? null : now()]);

        return back()->with('success', __('Saved.'));
    }

    /** Every note that is not done yet, the ones whose day has come first. */
    public function reminders(): View
    {
        Gate::authorize('accounts.view');

        return view('accounts.reminders', [
            'notes' => PartyNote::with(['party.group', 'creator'])->whereNull('done_at')->orderByRaw('case when remind_on is null then 1 else 0 end')->orderBy('remind_on')->orderByDesc('id')->paginate(per_page(50)),
            'canManage' => auth()->user()->can('accounts.manage'),
        ]);
    }

    /** The files kept with an account or a paper. */
    public function attachments(Request $request): View
    {
        [$type, $subject, $title, $back, $permission] = $this->subject($request);
        Gate::authorize($permission.'.view');

        return view('accounts.attachments', [
            'type' => $type, 'subject' => $subject, 'title' => $title, 'back' => $back,
            'files' => Attachment::with('creator')->where('subject_type', $type)->where('subject_id', $subject->getKey())->orderByDesc('id')->get(),
            'canManage' => $request->user()->can($permission.'.manage'),
        ]);
    }

    public function attach(Request $request): RedirectResponse
    {
        [$type, $subject, , , $permission] = $this->subject($request);
        Gate::authorize($permission.'.manage');
        $file = $request->validate(['file' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf,mp3,m4a,mp4,ogg,oga,webm,wav,aac,weba']])['file'];

        // kept in the folder of the business, outside the public folder
        $path = $file->store('attachments/'.tenant()->id.'/'.$type.'/'.$subject->getKey(), 'local');
        Attachment::create([
            'subject_type' => $type, 'subject_id' => $subject->getKey(), 'path' => $path, 'name' => mb_substr($file->getClientOriginalName(), 0, 200),
            'mime' => $file->getMimeType(), 'size' => (int) $file->getSize(), 'created_by' => $request->user()->id,
        ]);

        return back()->with('success', __('File attached.'));
    }

    public function file(Request $request): Response
    {
        [$type, $subject, , , $permission] = $this->subject($request);
        Gate::authorize($permission.'.view');
        $attachment = Attachment::where('subject_type', $type)->where('subject_id', $subject->getKey())->findOrFail((int) $request->route('file'));
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return response(Storage::disk('local')->get($attachment->path), 200, [
            'Content-Type' => $attachment->mime ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.addcslashes($attachment->name, '"\\').'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function detach(Request $request): RedirectResponse
    {
        [$type, $subject, , , $permission] = $this->subject($request);
        Gate::authorize($permission.'.manage');
        $attachment = Attachment::where('subject_type', $type)->where('subject_id', $subject->getKey())->findOrFail((int) $request->route('file'));
        Storage::disk('local')->delete($attachment->path);
        $attachment->delete();
        audit('deleted', null, 'Attachment '.$attachment->name);

        return back()->with('success', __(':name deleted.', ['name' => __('File')]));
    }

    private function party(Request $request): Party
    {
        $group = AccountGroup::active()->where('key', (string) $request->route('group'))->firstOrFail();

        return Party::with('group')->where('account_group_id', $group->id)->findOrFail((int) $request->route('id'));
    }

    /** @return array{0: string, 1: \Illuminate\Database\Eloquent\Model, 2: string, 3: string, 4: string} type, record, title, the page to go back to, permission */
    private function subject(Request $request): array
    {
        $type = (string) $request->route('type');
        abort_unless(isset(Attachment::SUBJECTS[$type]), 404);
        if ($type === 'party') {
            $party = Party::with('group')->findOrFail((int) $request->route('id'));

            return [$type, $party, $party->name, route('accounts.index', ['group' => $party->group->key]), 'accounts'];
        }
        $voucher = Voucher::findOrFail((int) $request->route('id'));
        $list = ['money_in' => 'money-in.index', 'money_out' => 'money-out.index', 'transfer' => 'transfers.index', 'exchange' => 'exchanges.index', 'income' => 'income.index'][$voucher->kind] ?? null;

        return [$type, $voucher, $voucher->number, $list ? route($list) : route('deals.show', ['id' => $voucher->deal_id]), 'transactions'];
    }
}
