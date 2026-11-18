<?php

namespace App\Http\Controllers;

use App\Exceptions\UserError;
use App\Models\Central\Backup;
use App\Services\Backups;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Backup and restore, by the owner of the business: the copies of its own database, a new copy
 * at any moment, a download, and putting a copy back. Before a copy is put back, what the
 * business has now is saved as a new copy, so a wrong restore can be undone.
 */
class BackupController extends Controller
{
    private function mine(Request $request): Backup
    {
        return Backup::where('tenant_id', tenant()->id)->findOrFail((int) $request->route('id'));
    }

    public function index(): View
    {
        Gate::authorize('settings.manage');

        return view('backups.index', ['backups' => Backup::where('tenant_id', tenant()->id)->orderByDesc('id')->paginate(per_page(25))]);
    }

    public function store(): RedirectResponse
    {
        Gate::authorize('settings.manage');
        try {
            $backup = Backups::make(tenant(), 'manual');
        } catch (Throwable $e) {
            report($e);

            throw new UserError(__('The backup could not be made: :reason', ['reason' => $e->getMessage()]));
        }
        audit('created', null, 'Backup '.basename($backup->file));

        return back()->with('success', __('Backup made.'));
    }

    public function download(Request $request): BinaryFileResponse
    {
        Gate::authorize('settings.manage');
        $backup = $this->mine($request);
        abort_unless(is_file($backup->path()), 404);
        audit('downloaded', null, 'Backup '.basename($backup->file));

        return response()->download($backup->path(), basename($backup->file));
    }

    /** The password of the signed-in user must be typed: everything recorded after this copy is replaced. */
    public function restore(Request $request): RedirectResponse
    {
        Gate::authorize('settings.manage');
        $request->validate(['password' => ['required', 'current_password']]);
        $backup = $this->mine($request);
        $file = basename($backup->file);
        try {
            Backups::restore($backup);
        } catch (Throwable $e) {
            report($e);

            throw new UserError(__('The backup could not be put back: :reason', ['reason' => $e->getMessage()]));
        }
        app()->forgetInstance('erp.settings');
        audit('restored', null, 'Backup '.$file);

        return redirect()->route('backups.index')->with('success', __('The backup was put back. What you had before was saved as a new backup.'));
    }
}
