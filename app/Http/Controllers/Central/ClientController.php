<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Central\AdminLog;
use App\Models\Central\Backup;
use App\Models\Central\Tenant;
use App\Models\Central\Ticket;
use App\Models\JournalEntry;
use App\Models\Party;
use App\Models\User;
use App\Services\Billing;
use App\Services\Licence;
use App\Tenancy\Support;
use App\Tenancy\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

/** Everything about one business on one page, and the door into its system for the support staff. */
class ClientController extends Controller
{
    public function show(Request $request, Tenancy $tenancy): View
    {
        $tenant = Tenant::findOrFail((int) $request->route('id'));
        try {
            $inside = $tenancy->run($tenant, function () {
                $connection = DB::connection();
                $size = $connection->getDriverName() === 'sqlite'
                    ? (int) @filesize($connection->getDatabaseName())
                    : (int) ($connection->selectOne('select sum(data_length + index_length) as size from information_schema.tables where table_schema = ?', [$connection->getDatabaseName()])->size ?? 0);

                return [
                    'branches' => Branch::orderBy('id')->get(['id', 'name', 'type', 'city', 'province', 'is_active'])
                        ->map(fn (Branch $b) => ['name' => $b->name, 'type' => Branch::TYPES[$b->type] ?? $b->type, 'place' => implode(', ', array_filter([$b->city, $b->province])), 'active' => (bool) $b->is_active,
                            'users' => User::where('branch_id', $b->id)->where('is_active', true)->count()])->all(),
                    'users' => User::where('is_active', true)->count(), 'customers' => Party::count(),
                    'transactions' => JournalEntry::whereNull('voided_at')->count(),
                    'last' => AuditLog::max('created_at'), 'size' => $size,
                ];
            });
        } catch (Throwable $e) {
            report($e);
            $inside = null;   // its database cannot be opened: the page still shows what the panel knows
        }

        return view('central.client', [
            'tenant' => $tenant, 'inside' => $inside, 'licence' => Licence::state($tenant), 'balance' => Billing::balance($tenant),
            'invoices' => $tenant->invoices()->orderByDesc('id')->limit(10)->get(),
            'payments' => $tenant->payments()->orderByDesc('paid_on')->orderByDesc('id')->limit(10)->get(),
            'tickets' => Ticket::where('tenant_id', $tenant->id)->orderByDesc('id')->limit(5)->get(),
            'backups' => Backup::where('tenant_id', $tenant->id)->orderByDesc('id')->limit(5)->get(),
            'logs' => AdminLog::with('admin')->where('tenant_id', $tenant->id)->orderByDesc('id')->limit(10)->get(),
        ]);
    }

    /** The owner to call, and notes of the staff about this business. */
    public function note(Request $request): RedirectResponse
    {
        $tenant = Tenant::findOrFail((int) $request->route('id'));
        $tenant->update($request->validate([
            'owner_name' => ['nullable', 'string', 'max:150'], 'owner_phone' => ['nullable', 'string', 'max:40'], 'owner_email' => ['nullable', 'email', 'max:190'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]));
        AdminLog::record('updated', $tenant, 'Contact and notes');

        return back()->with('success', __('Saved.'));
    }

    /**
     * Into the system of a business to help it. A reason must be given; it is written in the log of
     * the panel and in the audit log of the business, where its owner can read it.
     */
    public function enter(Request $request): RedirectResponse
    {
        $tenant = Tenant::where('status', Tenant::ACTIVE)->findOrFail((int) $request->route('id'));
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:200']]);
        $admin = $request->user('admin');
        AdminLog::record('entered', $tenant, $data['reason']);

        $port = $request->getPort();
        $root = $request->getScheme().'://'.$tenant->domain.(in_array($port, [80, 443, null], true) ? '' : ':'.$port);
        $expires = time() + 60;

        return redirect()->away($root.'/support-enter?'.http_build_query([
            'a' => $admin->id, 'e' => $expires, 'r' => $data['reason'], 's' => Support::seal($tenant->id, $admin->id, $expires, 'support|'.$data['reason']),
        ]));
    }
}
