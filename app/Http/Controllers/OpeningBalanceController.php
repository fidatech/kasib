<?php

namespace App\Http\Controllers;

use App\Exceptions\UserError;
use App\Models\JournalEntry;
use App\Services\OpeningBalances;
use App\Support\Xlsx;
use App\Support\XlsxReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Throwable;

/** Opening balances: the accounts of an existing business and what each starts with, from an Excel or CSV file. */
class OpeningBalanceController extends Controller
{
    public function index(): View
    {
        Gate::authorize('accounts.view');

        return view('opening.index', [
            'entries' => JournalEntry::with(['creator', 'lines.account.currency'])->where('type', 'opening')->orderByDesc('id')->paginate(per_page(25)),
            'canManage' => auth()->user()->can('accounts.manage'),
        ]);
    }

    /** The empty sheet to fill in, with two rows that show how. */
    public function template(): Response
    {
        Gate::authorize('accounts.view');
        $rows = [
            ['bold' => true, 'cells' => OpeningBalances::COLUMNS],
            ['cells' => ['Customers', 'Ahmad Trading', '0700123456', 'AFN', ['n' => '150000'], 'we owe']],
            ['cells' => ['Suppliers', 'Karim Ltd', '', 'USD', ['n' => '500'], 'owes us']],
            ['cells' => ['Bank', 'AIB current account', '', 'AFN', ['n' => '80000'], '']],
        ];

        return response(Xlsx::build($rows, 'Opening balances'), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="kasib-opening-balances.xlsx"',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('accounts.manage');
        $file = $request->validate(['file' => ['required', 'file', 'max:5120', 'extensions:xlsx,csv']])['file'];
        try {
            $rows = XlsxReader::rows($file->getRealPath(), $file->getClientOriginalExtension());
        } catch (Throwable $e) {
            throw new UserError(__('The file cannot be read. Save it as Excel (.xlsx) or CSV and try again.'));
        }
        $result = OpeningBalances::import($rows);

        return redirect()->route('opening.index')->with('success', __(':accounts new accounts and :balances opening balances recorded.', $result));
    }
}
