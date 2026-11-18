<?php

namespace App\Http\Controllers;

use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\Branch;
use App\Models\JournalEntry;
use App\Services\Ledger;
use App\Support\Num;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * A journal entry written by hand, for a correction or for anything the other screens do not
 * cover. Every line names an account and a debit or a credit, in the currency of that account;
 * the entry must balance.
 */
class JournalEntryController extends Controller
{
    private const LINES = 8;

    public function create(): View
    {
        Gate::authorize('journal.manage');

        return view('journal.create', [
            'accounts' => Account::active()->with(['currency', 'category'])->orderBy('account_category_id')->orderBy('name')->orderBy('currency_id')->get(),
            'count' => self::LINES,
            'recent' => JournalEntry::with('creator')->where('type', 'manual')->orderByDesc('id')->limit(15)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('journal.manage');
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['required', 'string', 'max:500'],
            'lines' => ['required', 'array', 'max:'.self::LINES],
            'lines.*.account_id' => ['nullable', 'integer'],
            'lines.*.debit' => ['nullable', 'numeric', 'gte:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'gte:0'],
            'lines.*.memo' => ['nullable', 'string', 'max:500'],
        ]);

        $lines = [];
        foreach ($data['lines'] as $line) {
            $debit = Num::money($line['debit'] ?? 0);
            $credit = Num::money($line['credit'] ?? 0);
            if (empty($line['account_id']) || (Num::isZero($debit) && Num::isZero($credit))) {
                continue;
            }
            $lines[] = ['account' => (int) $line['account_id'], 'debit' => $debit, 'credit' => $credit, 'memo' => $line['memo'] ?? null];
        }
        if (count($lines) < 2) {
            throw new UserError(__('An entry needs at least two lines: one debit and one credit.'));
        }
        $entry = Ledger::post(current_branch(), 'manual', $data['date'], $data['description'], $lines);
        audit('created', $entry, 'Journal entry '.$entry->number);

        return redirect()->route('journal.create')->with('success', __('Journal entry :number saved.', ['number' => $entry->number]));
    }

    /** Only an entry written by hand is cancelled here; every other entry is cancelled with its own paper. */
    public function destroy(Request $request): RedirectResponse
    {
        Gate::authorize('journal.manage');
        $entry = JournalEntry::where('type', 'manual')->findOrFail((int) $request->route('id'));
        Ledger::void($entry, $request->validate(['reason' => ['nullable', 'string', 'max:255']])['reason'] ?? null);

        return back()->with('success', __('Cancelled.'));
    }
}
