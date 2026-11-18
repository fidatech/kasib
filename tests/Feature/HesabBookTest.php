<?php

namespace Tests\Feature;

use App\Models\AccountBalance;
use App\Models\AccountCategory;
use App\Models\AccountGroup;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Party;
use App\Models\Voucher;
use App\Services\Accounts;
use App\Services\Ledger;
use App\Services\Vouchers;
use App\Support\Menu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The accounts of every category, the daily papers and the reports of a business. */
class HesabBookTest extends TestCase
{
    use RefreshDatabase;

    private function currency(string $code): Currency
    {
        return Currency::where('code', $code)->firstOrFail();
    }

    private function cash(string $code = 'AFN'): \App\Models\Account
    {
        return Accounts::cash(Branch::main(), $this->currency($code));
    }

    private function party(string $group, string $name): Party
    {
        return Party::create(['account_group_id' => AccountGroup::byKey($group)->id, 'name' => $name])->fresh();
    }

    /** What the business owes the party in one currency (below zero: the party owes the business). */
    private function owed(Party $party, string $code): string
    {
        foreach (Vouchers::balances($party->fresh()) as $row) {
            if ($row['currency']->code === $code) {
                return $row['amount'];
            }
        }

        return '0.00';
    }

    private function booksBalance(): void
    {
        $this->assertSame(0.0, round((float) AccountBalance::sum('base_balance'), 2), 'Debits and credits of the whole ledger must be equal.');
    }

    public function test_a_new_business_starts_with_its_account_categories_and_every_page_opens(): void
    {
        $this->business();
        $this->owner();

        $this->assertSame(['cash', 'bank', 'saraf', 'customers', 'suppliers'], AccountGroup::orderBy('sort')->pluck('key')->all());
        $this->assertSame('Cash', Accounts::cashBox()->name);
        $this->assertTrue(AccountGroup::byKey('cash')->isMoney());
        $this->assertFalse(AccountGroup::byKey('customers')->isMoney());

        $this->get('/')->assertOk()->assertSee('Cash Accounts')->assertSee('Saraf Accounts')->assertSee('Suppliers')->assertSee('Money In');

        foreach (['/accounts/cash', '/accounts/bank', '/accounts/saraf', '/accounts/customers', '/accounts/suppliers', '/accounts/customers/create',
            '/money-in', '/money-in/create', '/money-out', '/money-out/create', '/transfers', '/transfers/create', '/exchanges', '/exchanges/create',
            '/income', '/income/create', '/expenses', '/expenses/create', '/journal', '/chart-of-accounts', '/shareholders', '/capital', '/distributions',
            '/assets', '/depreciation', '/liabilities', '/closing', '/reports', '/reports/trial-balance', '/reports/profit-and-loss', '/reports/balance-sheet',
            '/reports/balances', '/reports/due-payments', '/currency-position', '/users', '/roles', '/permissions', '/audit', '/settings',
            '/account-categories', '/account-categories/create', '/currencies', '/rates', '/expense-categories', '/expense-categories/create',
            '/deals', '/cash-counts', '/cash-counts/create', '/journal/entries', '/backups', '/journal?cancelled=1'] as $page) {
            $this->get($page)->assertOk();
        }
        $this->get('/accounts/nothing-here')->assertNotFound();
        $this->get('/accounts/'.Accounts::cashBox()->group->key.'/'.Accounts::cashBox()->id.'/statement')->assertOk()->assertSee('Cash');
    }

    public function test_a_new_account_category_gets_a_page_of_its_own_in_the_menu(): void
    {
        $this->business();
        $user = $this->owner();

        $this->post('/account-categories', ['name' => 'Employees', 'name_fa' => 'کارمندان', 'type' => 'party', 'icon' => 'user-tie', 'sort' => 60, 'is_active' => 1])
            ->assertSessionHasNoErrors()->assertRedirect('/account-categories');
        $group = AccountGroup::byKey('employees');
        $this->assertSame(['party', 'liability', false], [$group->type, $group->chart->nature, $group->is_system]);

        // it is in the menu at once, and its page works like every other category
        $this->get('/')->assertOk()->assertSee('Employees');
        $this->get('/accounts/employees')->assertOk()->assertSee('Employees');
        $this->post('/accounts/employees', ['name' => 'Karim', 'phone' => '0700111222', 'is_active' => 1])->assertSessionHasNoErrors()->assertRedirect('/accounts/employees');
        $karim = Party::where('name', 'Karim')->firstOrFail();
        $this->assertSame($group->id, (int) $karim->account_group_id);
        $this->get('/accounts/employees')->assertSee('Karim')->assertSee('0700111222');
        // an account of one category is not found on the page of another
        $this->get('/accounts/customers/'.$karim->id.'/statement')->assertNotFound();

        // a category with accounts, and the categories every business starts with, stay
        $this->from('/account-categories')->delete('/account-categories/'.$group->id)->assertSessionHas('danger');
        $this->from('/account-categories')->delete('/account-categories/'.AccountGroup::byKey('customers')->id)->assertSessionHas('danger');
        $this->assertNotNull(AccountGroup::find($group->id));

        // switched off: out of the menu, and its page is gone
        $this->put('/account-categories/'.$group->id, ['name' => 'Employees', 'icon' => 'user-tie', 'sort' => 60, 'is_active' => 0])->assertSessionHasNoErrors();
        $labels = collect(Menu::for($user))->firstWhere('label', 'Accounts')['children'];
        $this->assertNotContains('Employees', array_column($labels, 'label'));
        $this->get('/accounts/employees')->assertNotFound();

        // an empty category that the business added can be removed, with its group in the chart of accounts
        $this->post('/account-categories', ['name' => 'Mobile Money', 'type' => 'money', 'icon' => 'mobile-screen', 'sort' => 35, 'is_active' => 1])->assertSessionHasNoErrors();
        $mobile = AccountGroup::byKey('mobile-money');
        $this->assertSame('asset', $mobile->chart->nature);
        $this->delete('/account-categories/'.$mobile->id)->assertSessionHasNoErrors()->assertRedirect('/account-categories');
        $this->assertNull(AccountGroup::find($mobile->id));
        $this->assertFalse(AccountCategory::where('key', 'group_mobile_money')->exists());
    }

    public function test_an_account_keeps_a_separate_balance_in_every_currency(): void
    {
        $this->business();
        $this->owner();
        $this->rates(['USD' => '70']);
        $afn = $this->currency('AFN')->id;
        $usd = $this->currency('USD')->id;

        // opens owing the customer 1,000 AFN, while the customer owes 50 USD
        $this->post('/accounts/customers', ['name' => 'Ahmad Trading', 'is_active' => 1, 'currencies' => [
            $afn => ['on' => 1, 'opening' => '1000', 'side' => 'credit'],
            $usd => ['on' => 1, 'opening' => '50', 'side' => 'debit'],
        ]])->assertSessionHasNoErrors()->assertRedirect('/accounts/customers');

        $ahmad = Party::where('name', 'Ahmad Trading')->firstOrFail();
        $this->assertSame(2, $ahmad->accounts()->count());
        $this->assertSame('1000.00', $this->owed($ahmad, 'AFN'));
        $this->assertSame('-50.00', $this->owed($ahmad, 'USD'));
        $this->booksBalance();

        $this->get('/accounts/customers')->assertOk()->assertSee('1,000.00 AFN')->assertSee('50.00 USD');
        $this->get('/accounts/customers/'.$ahmad->id.'/statement?currency='.$usd)->assertOk()->assertSee('Ahmad Trading')->assertSee('USD');
        $this->get('/reports/balances')->assertOk()->assertSee('Ahmad Trading');
        $this->get('/reports/balance-sheet')->assertOk()->assertSee('Customer accounts');

        // an account with entries is switched off, not deleted
        $this->from('/accounts/customers')->delete('/accounts/customers/'.$ahmad->id)->assertSessionHas('danger');
        $this->assertNotNull(Party::find($ahmad->id));
    }

    public function test_money_in_and_money_out_move_cash_and_what_is_owed(): void
    {
        $this->business();
        $this->owner();
        $ahmad = $this->party('customers', 'Ahmad');
        $cash = $this->cash();

        $this->post('/money-in', ['date' => today_iso(), 'party_id' => $ahmad->id, 'account_id' => $cash->id, 'amount' => '5000', 'due_date' => now()->addDays(3)->toDateString(), 'description' => 'Amanat'])
            ->assertSessionHasNoErrors()->assertRedirect('/money-in');
        $in = Voucher::where('kind', 'money_in')->firstOrFail();
        $this->assertSame('MI-000001', $in->number);
        $this->assertSame('5000.00', Ledger::balance($cash));
        $this->assertSame('5000.00', $this->owed($ahmad, 'AFN'));
        $this->get('/money-in')->assertOk()->assertSee('MI-000001')->assertSee('Ahmad');
        $this->get('/money-in?q=Amanat&currency='.$cash->currency_id)->assertOk()->assertSee('MI-000001');
        $this->get('/money-in?q=nothing-like-this')->assertOk()->assertDontSee('MI-000001');
        $this->get('/reports/due-payments')->assertOk()->assertSee('Ahmad')->assertSee('MI-000001');

        $this->post('/money-out', ['date' => today_iso(), 'party_id' => $ahmad->id, 'account_id' => $cash->id, 'amount' => '2000'])->assertSessionHasNoErrors();
        $this->assertSame('3000.00', Ledger::balance($cash));
        $this->assertSame('3000.00', $this->owed($ahmad, 'AFN'));

        // cash never goes below zero: the paper is refused and nothing of it is kept
        $this->from('/money-out/create')->post('/money-out', ['date' => today_iso(), 'party_id' => $ahmad->id, 'account_id' => $cash->id, 'amount' => '9000'])->assertSessionHas('danger');
        $this->assertSame(1, Voucher::where('kind', 'money_out')->count());
        // a paper cannot be dated in the future
        $this->post('/money-in', ['date' => now()->addDay()->toDateString(), 'party_id' => $ahmad->id, 'account_id' => $cash->id, 'amount' => '10'])->assertSessionHasErrors('date');

        // cancelled: its entry is taken back and it stays on the list
        $out = Voucher::where('kind', 'money_out')->firstOrFail();
        $this->delete('/money-out/'.$out->id, ['reason' => 'Wrong amount'])->assertSessionHasNoErrors();
        $this->assertTrue($out->fresh()->isCancelled());
        $this->assertSame('5000.00', Ledger::balance($cash));
        $this->assertSame('5000.00', $this->owed($ahmad, 'AFN'));
        $this->get('/money-out/'.$out->id.'/receipt')->assertOk();
        $this->booksBalance();
    }

    public function test_an_exchange_keeps_the_profit_or_loss_of_the_deal(): void
    {
        $this->business();
        $this->owner();
        $this->rates(['USD' => '70']);
        $this->openingCash('AFN', '100000');
        $afn = $this->cash('AFN');
        $usd = $this->cash('USD');

        // 1,000 USD bought for 71,000 AFN while the rate of the business is 70: a loss of 1,000
        $this->post('/exchanges', ['date' => today_iso(), 'account_id' => $afn->id, 'amount' => '71000', 'other_account_id' => $usd->id, 'other_amount' => '1000'])->assertSessionHasNoErrors();
        $buy = Voucher::where('kind', 'exchange')->orderBy('id')->firstOrFail();
        $this->assertSame(['EX-000001', '-1000.00'], [$buy->number, $buy->profit]);

        // and sold for 72,000 AFN: a profit of 2,000
        $this->post('/exchanges', ['date' => today_iso(), 'account_id' => $usd->id, 'amount' => '1000', 'other_account_id' => $afn->id, 'other_amount' => '72000'])->assertSessionHasNoErrors();
        $this->assertSame('2000.00', Voucher::where('kind', 'exchange')->orderByDesc('id')->firstOrFail()->profit);

        $this->assertSame('101000.00', Ledger::balance($afn));
        $this->assertSame('0.00', Ledger::balance($usd));
        $this->assertSame('-1000.00', Ledger::baseBalance(Accounts::system(Branch::main(), 'exchange_income')));
        $this->booksBalance();

        // the same currency on both sides is not an exchange
        $this->from('/exchanges/create')->post('/exchanges', ['date' => today_iso(), 'account_id' => $afn->id, 'amount' => '10', 'other_account_id' => $afn->id, 'other_amount' => '10'])->assertSessionHas('danger');
        $this->get('/exchanges')->assertOk()->assertSee('EX-000002');
        $this->get('/reports/profit-and-loss')->assertOk()->assertSee('Income from currency exchange')->assertSee('1,000.00');
    }

    public function test_a_transfer_moves_money_between_accounts_of_one_currency_and_books_its_fee(): void
    {
        $this->business();
        $this->owner();
        $this->rates(['USD' => '70']);
        $this->openingCash('AFN', '10000');
        $cash = $this->cash();
        $bank = Accounts::party($this->party('bank', 'AIB current account'), $this->currency('AFN'));

        $this->post('/transfers', ['date' => today_iso(), 'account_id' => $cash->id, 'other_account_id' => $bank->id, 'amount' => '5000', 'fee' => '50'])->assertSessionHasNoErrors();
        $this->assertSame('4950.00', Ledger::balance($cash));
        $this->assertSame('5000.00', Ledger::balance($bank));
        $this->assertSame('50.00', Ledger::baseBalance(Accounts::expense(Branch::main(), AccountCategory::byKey('expense_fees'))));
        $this->get('/currency-position')->assertOk()->assertSee('4,950.00')->assertSee('5,000.00');

        // between two currencies it is an exchange, not a transfer
        $this->from('/transfers/create')->post('/transfers', ['date' => today_iso(), 'account_id' => $cash->id, 'other_account_id' => $this->cash('USD')->id, 'amount' => '100'])->assertSessionHas('danger');
        $this->assertSame(1, Voucher::where('kind', 'transfer')->count());
        $this->booksBalance();
    }

    public function test_income_and_expenses_reach_the_profit_and_loss_statement(): void
    {
        $this->business();
        $this->owner();
        $this->openingCash('AFN', '1000');
        $cash = $this->cash();

        $this->post('/income', ['date' => today_iso(), 'account_category_id' => AccountCategory::byKey('trade_income')->id, 'account_id' => $cash->id, 'amount' => '3000', 'description' => 'Sold a shipment'])
            ->assertSessionHasNoErrors();
        $this->post('/expenses', ['account_category_id' => AccountCategory::byKey('expense_rent')->id, 'paid_account_id' => $cash->id, 'amount' => '1200'])->assertSessionHasNoErrors();

        $this->assertSame('2800.00', Ledger::balance($cash));
        $this->get('/reports/profit-and-loss')->assertOk()->assertSee('Profit from trade')->assertSee('3,000.00')->assertSee('Rent')->assertSee('1,200.00')->assertSee('Profit of the period')->assertSee('1,800.00');
        $this->get('/reports/balance-sheet')->assertOk()->assertSee('Profit not closed yet')->assertSee('1,800.00');
        $this->get('/reports/trial-balance')->assertOk()->assertSee('Profit from trade');
        $this->get('/reports/trial-balance?export=csv')->assertOk();

        // a category the business adds itself works like the others
        $this->post('/expense-categories', ['name' => 'Tea and guests', 'nature' => 'expense'])->assertSessionHasNoErrors();
        $tea = AccountCategory::where('name', 'Tea and guests')->firstOrFail();
        $this->assertSame(['expense', false], [$tea->nature, $tea->is_system]);
        $this->post('/expenses', ['account_category_id' => $tea->id, 'paid_account_id' => $cash->id, 'amount' => '300'])->assertSessionHasNoErrors();
        $this->get('/reports/profit-and-loss')->assertSee('Tea and guests')->assertSee('1,500.00');
        $this->booksBalance();
    }

    public function test_new_rates_value_foreign_money_again_and_money_held_for_others_adds_nothing(): void
    {
        $this->business();
        $this->owner();
        $this->rates(['USD' => '70']);
        $this->openingCash('USD', '1000');
        $usd = $this->currency('USD');
        $income = fn () => Ledger::baseBalance(Accounts::system(Branch::main(), 'exchange_income'));

        // the business's own 1,000 USD: 70 -> 72 is a gain of 2,000
        $this->put('/rates', ['rates' => [$usd->id => ['rate' => '72']]])->assertSessionHasNoErrors()->assertRedirect('/rates');
        $this->assertSame('-2000.00', $income());
        $this->assertSame('72000.00', Ledger::baseBalance($this->cash('USD')));

        // 500 USD held for a customer: 72 -> 75 gains on the cash and loses the same on the debt; only the own 1,000 USD count
        $ahmad = $this->party('customers', 'Ahmad');
        Vouchers::moneyIn($ahmad, $this->cash('USD'), today_iso(), '500');
        $this->put('/rates', ['rates' => [$usd->id => ['rate' => '75']]])->assertSessionHasNoErrors();
        $this->assertSame('-5000.00', $income());
        $this->assertSame('500.00', $this->owed($ahmad, 'USD'));
        $this->booksBalance();

        // the same rate again changes nothing
        $entries = \App\Models\JournalEntry::count();
        $this->put('/rates', ['rates' => [$usd->id => ['rate' => '75']]])->assertSessionHasNoErrors();
        $this->assertSame($entries, \App\Models\JournalEntry::count());
    }

    public function test_simple_mode_hides_the_finance_pages_from_the_menu(): void
    {
        $this->business();
        $user = $this->owner();
        $sections = fn () => array_column(Menu::for($user), 'label');

        $this->assertContains('Finance', $sections());
        $this->put('/settings', ['company_name' => 'Acme business', 'simple_mode' => 1])->assertSessionHasNoErrors()->assertRedirect('/settings');
        $this->assertNotContains('Finance', $sections());
        $this->assertContains('Transactions', $sections());

        $this->put('/settings', ['company_name' => 'Acme business', 'simple_mode' => 0])->assertSessionHasNoErrors();
        $this->assertContains('Finance', $sections());
    }

    public function test_a_deal_works_out_its_own_profit_or_loss(): void
    {
        $this->business();
        $this->owner();
        $this->openingCash('AFN', '100000');
        $cash = $this->cash();
        $main = Branch::main();

        $this->post('/deals', ['name' => 'Flour from Mazar', 'notes' => '200 sacks'])->assertSessionHasNoErrors();
        $deal = \App\Models\Deal::firstOrFail();
        $this->assertSame(['DL-000001', 'open'], [$deal->number, $deal->status]);
        $move = fn (string $kind, string $amount) => $this->from('/deals/'.$deal->id)->post('/deals/'.$deal->id.'/moves', ['kind' => $kind, 'date' => today_iso(), 'account_id' => $cash->id, 'amount' => $amount]);

        $move('cost', '60000')->assertSessionHasNoErrors();
        $move('receipt', '45000')->assertSessionHasNoErrors();
        $move('receipt', '25000')->assertSessionHasNoErrors();
        $this->assertSame('110000.00', Ledger::balance($cash));
        $this->get('/deals/'.$deal->id)->assertOk()->assertSee('Flour from Mazar')->assertSee('60,000.00')->assertSee('70,000.00')->assertSee('10,000.00');
        $this->get('/deals')->assertOk()->assertSee('DL-000001');
        // nothing reaches the profit and loss statement before the deal is closed
        $this->assertSame('0.00', Ledger::baseBalance(Accounts::expense($main, AccountCategory::byKey('trade_income'))));

        $this->post('/deals/'.$deal->id.'/close')->assertSessionHasNoErrors()->assertRedirect('/deals/'.$deal->id);
        $deal->refresh();
        $this->assertSame(['closed', '10000.00'], [$deal->status, $deal->result]);
        $this->assertSame('-10000.00', Ledger::baseBalance(Accounts::expense($main, AccountCategory::byKey('trade_income'))));
        $this->assertSame('0.00', Ledger::baseBalance($deal->account_id));
        // a closed deal takes nothing more
        $move('cost', '100')->assertSessionHas('danger');
        $this->from('/deals/'.$deal->id)->post('/deals/'.$deal->id.'/close')->assertSessionHas('danger');

        // a deal that brought in less than it cost closes with a loss
        $this->post('/deals', ['name' => 'Rice'])->assertSessionHasNoErrors();
        $rice = \App\Models\Deal::orderByDesc('id')->firstOrFail();
        $this->post('/deals/'.$rice->id.'/moves', ['kind' => 'cost', 'date' => today_iso(), 'account_id' => $cash->id, 'amount' => '5000'])->assertSessionHasNoErrors();
        $this->post('/deals/'.$rice->id.'/moves', ['kind' => 'receipt', 'date' => today_iso(), 'account_id' => $cash->id, 'amount' => '3000'])->assertSessionHasNoErrors();
        $this->post('/deals/'.$rice->id.'/close')->assertSessionHasNoErrors();
        $this->assertSame('-2000.00', $rice->fresh()->result);
        $this->assertSame('2000.00', Ledger::baseBalance(Accounts::expense($main, AccountCategory::byKey('trade_loss'))));
        $this->booksBalance();
    }

    public function test_the_daily_cash_count_books_a_shortage_or_an_excess(): void
    {
        $this->business();
        $this->owner();
        $this->openingCash('AFN', '10000');
        $cash = $this->cash();
        $main = Branch::main();

        $this->get('/cash-counts/create')->assertOk();
        $this->post('/cash-counts', ['account_id' => $cash->id, 'counted' => '9800', 'note' => 'Evening'])->assertSessionHasNoErrors()->assertRedirect('/cash-counts');
        $first = \App\Models\CashCount::firstOrFail();
        $this->assertSame(['CC-000001', '10000.00', '9800.00', '-200.00'], [$first->number, $first->book, $first->counted, $first->difference]);
        $this->assertSame('9800.00', Ledger::balance($cash));
        $this->assertSame('200.00', Ledger::baseBalance(Accounts::expense($main, AccountCategory::byKey('cash_shortage'))));

        $this->post('/cash-counts', ['account_id' => $cash->id, 'counted' => '9850'])->assertSessionHasNoErrors();
        $this->assertSame('9850.00', Ledger::balance($cash));
        $this->assertSame('-50.00', Ledger::baseBalance(Accounts::expense($main, AccountCategory::byKey('cash_excess'))));

        // cash that agrees with the books leaves no entry
        $entries = \App\Models\JournalEntry::count();
        $this->post('/cash-counts', ['account_id' => $cash->id, 'counted' => '9850'])->assertSessionHasNoErrors();
        $this->assertSame($entries, \App\Models\JournalEntry::count());
        $this->get('/cash-counts')->assertOk()->assertSee('CC-000001')->assertSee('CC-000003');
        $this->booksBalance();
    }

    public function test_a_journal_entry_is_written_by_hand_and_must_balance(): void
    {
        $this->business();
        $this->owner();
        $cash = $this->cash();
        $opening = Accounts::system(Branch::main(), 'opening');

        $this->get('/journal/entries')->assertOk();
        $this->post('/journal/entries', ['date' => today_iso(), 'description' => 'Cash found in the safe', 'lines' => [
            ['account_id' => $cash->id, 'debit' => '500'], ['account_id' => $opening->id, 'credit' => '500'], ['account_id' => '', 'debit' => '', 'credit' => ''],
        ]])->assertSessionHasNoErrors()->assertRedirect('/journal/entries');
        $entry = \App\Models\JournalEntry::where('type', 'manual')->firstOrFail();
        $this->assertSame('500.00', Ledger::balance($cash));
        $this->get('/journal/entries')->assertOk()->assertSee($entry->number)->assertSee('Cash found in the safe');

        // debits and credits that are not equal, or a single line, are refused
        $this->from('/journal/entries')->post('/journal/entries', ['date' => today_iso(), 'description' => 'Wrong', 'lines' => [['account_id' => $cash->id, 'debit' => '500'], ['account_id' => $opening->id, 'credit' => '300']]])->assertSessionHas('danger');
        $this->from('/journal/entries')->post('/journal/entries', ['date' => today_iso(), 'description' => 'Wrong', 'lines' => [['account_id' => $cash->id, 'debit' => '500']]])->assertSessionHas('danger');
        $this->assertSame(1, \App\Models\JournalEntry::where('type', 'manual')->count());

        $this->delete('/journal/entries/'.$entry->id, ['reason' => 'Not needed'])->assertSessionHasNoErrors();
        $this->assertSame('0.00', Ledger::balance($cash));
        $this->get('/journal?cancelled=1')->assertOk();
        $this->booksBalance();
    }

    public function test_the_owner_backs_up_and_restores_the_business(): void
    {
        $tenant = $this->business();
        $this->owner();
        $this->party('customers', 'Before');

        $this->get('/backups')->assertOk();
        $this->post('/backups')->assertSessionHasNoErrors();
        $backup = \App\Models\Central\Backup::where('tenant_id', $tenant->id)->firstOrFail();
        $this->get('/backups')->assertOk()->assertSee('Restore');
        $this->get('/backups/'.$backup->id.'/download')->assertOk();

        $this->party('customers', 'After');
        // the password of the signed-in user is asked before anything is replaced
        $this->post('/backups/'.$backup->id.'/restore', ['password' => 'not-the-password'])->assertSessionHasErrors('password');
        $this->assertSame(2, Party::where('is_system', false)->count());

        $this->post('/backups/'.$backup->id.'/restore', ['password' => 'password123'])->assertSessionHasNoErrors()->assertRedirect('/backups');
        $this->enter($tenant);
        $this->assertSame(['Before'], Party::where('is_system', false)->orderBy('id')->pluck('name')->all());
        // what the business had before the restore was saved as a second copy
        $this->assertSame(2, \App\Models\Central\Backup::where('tenant_id', $tenant->id)->count());
        // a copy of another business is not found
        $this->get('/backups/999/download')->assertNotFound();
    }

    public function test_the_export_center_makes_one_workbook_with_a_sheet_for_every_module(): void
    {
        $this->business();
        $this->owner();
        $this->rates(['USD' => '70']);
        $this->openingCash('AFN', '50000');
        $cash = $this->cash();
        $ahmad = $this->party('customers', 'Ahmad');
        Vouchers::moneyIn($ahmad, $cash, today_iso(), '5000');
        Vouchers::exchange($cash, $this->cash('USD'), today_iso(), '7100', '100');
        Vouchers::income(AccountCategory::byKey('trade_income'), $cash, today_iso(), '900');

        $this->get('/export')->assertOk()->assertSee('Download the workbook');
        $this->get('/export/download?from='.today_iso().'&to='.today_iso())->assertSessionHasErrors('modules');

        $modules = array_keys(\App\Services\ExportCenter::MODULES);
        $response = $this->get('/export/download?'.http_build_query(['from' => now()->startOfYear()->toDateString(), 'to' => today_iso(), 'modules' => $modules]));
        $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // the file is a workbook: a summary and one sheet for every module, with the papers in them
        $file = tempnam(sys_get_temp_dir(), 'export');
        file_put_contents($file, $response->getContent());
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($file) === true);
        $workbook = $zip->getFromName('xl/workbook.xml');
        $this->assertSame(count($modules) + 1, substr_count($workbook, '<sheet '));
        foreach (['Summary', 'Money In', 'Exchanges', 'Roznamcha', 'Balance Sheet'] as $sheet) {
            $this->assertStringContainsString('name="'.$sheet.'"', $workbook);
        }
        $this->assertNotFalse($zip->getFromName('xl/worksheets/sheet'.(count($modules) + 1).'.xml'));
        $this->assertStringContainsString('MI-000001', $zip->getFromName('xl/worksheets/sheet3.xml'));
        $this->assertStringContainsString('Ahmad', $zip->getFromName('xl/worksheets/sheet2.xml'));
        $zip->close();
        @unlink($file);

        // only what was ticked
        $one = $this->get('/export/download?'.http_build_query(['from' => today_iso(), 'to' => today_iso(), 'modules' => ['exchanges']]))->assertOk();
        file_put_contents($file, $one->getContent());
        $zip->open($file);
        $this->assertSame(2, substr_count($zip->getFromName('xl/workbook.xml'), '<sheet '));
        $zip->close();
        @unlink($file);
    }

    public function test_opening_balances_come_in_from_an_excel_file_all_or_nothing(): void
    {
        $this->business();
        $this->owner();
        $this->rates(['USD' => '70']);
        $sheet = fn (array $rows) => \Illuminate\Http\UploadedFile::fake()->createWithContent('opening.xlsx', \App\Support\Xlsx::build(array_merge(
            [['bold' => true, 'cells' => \App\Services\OpeningBalances::COLUMNS]], array_map(fn (array $cells) => ['cells' => $cells], $rows))));

        $this->get('/opening-balances')->assertOk();
        $this->get('/opening-balances/template')->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // one wrong row: nothing of the file is taken
        $this->from('/opening-balances')->post('/opening-balances', ['file' => $sheet([['Customers', 'Ahmad Trading', '', 'AFN', ['n' => '100'], 'we owe'], ['Nobody', 'X', '', 'AFN', ['n' => '5'], '']])])->assertSessionHas('danger');
        $this->assertSame(0, Party::where('is_system', false)->count());

        $this->post('/opening-balances', ['file' => $sheet([
            ['Customers', 'Ahmad Trading', '0700123456', 'AFN', ['n' => '150000'], 'we owe'],
            ['Suppliers', 'Karim Ltd', '', 'USD', ['n' => '500'], 'owes us'],
            ['Bank', 'AIB current account', '', 'AFN', ['n' => '80000'], ''],
            ['Cash', 'Cash', '', 'AFN', ['n' => '20000'], ''],
        ])])->assertSessionHasNoErrors()->assertRedirect('/opening-balances');

        $this->assertSame(3, Party::where('is_system', false)->count());
        $this->assertSame('150000.00', $this->owed(Party::where('name', 'Ahmad Trading')->firstOrFail(), 'AFN'));
        $this->assertSame('-500.00', $this->owed(Party::where('name', 'Karim Ltd')->firstOrFail(), 'USD'));
        $this->assertSame('20000.00', Ledger::balance($this->cash()));
        $this->assertSame('0700123456', Party::where('name', 'Ahmad Trading')->value('phone'));
        $this->get('/opening-balances')->assertOk()->assertSee('Ahmad Trading');
        $this->booksBalance();

        // a CSV file works too
        $csv = \Illuminate\Http\UploadedFile::fake()->createWithContent('more.csv', "Category,Name,Phone,Currency,Balance,Side\nCustomers,Nazir,,AFN,2500,owes us\n");
        $this->post('/opening-balances', ['file' => $csv])->assertSessionHasNoErrors();
        $this->assertSame('-2500.00', $this->owed(Party::where('name', 'Nazir')->firstOrFail(), 'AFN'));
    }

    public function test_the_first_setup_prepares_the_system_for_the_kind_of_business(): void
    {
        $this->business();
        $this->owner();
        $usd = $this->currency('USD');

        $this->get('/')->assertOk()->assertSee('Three choices and the system is set up');
        $this->get('/setup')->assertOk()->assertSee('Trade and import');
        $this->post('/setup', ['business_type' => 'trade', 'currencies' => [$usd->id], 'calendar' => 'gregorian', 'simple_mode' => 0])->assertSessionHasNoErrors()->assertRedirect('/');

        $this->assertSame('party', AccountGroup::byKey('partners')->type);
        $this->assertTrue(AccountCategory::where('nature', 'expense')->where('name', 'Customs')->exists());
        $this->assertSame(['AFN', 'USD'], Currency::where('is_active', true)->orderBy('sort')->pluck('code')->all());
        $this->assertSame(['trade', 'gregorian', '1'], [setting('business_type'), setting('calendar'), setting('setup_done')]);
        $this->get('/')->assertOk()->assertDontSee('Three choices and the system is set up')->assertSee('Partners');
        $this->get('/accounts/partners')->assertOk();

        // done twice, nothing is made twice
        $this->post('/setup', ['business_type' => 'trade', 'currencies' => [$usd->id], 'calendar' => 'shamsi'])->assertSessionHasNoErrors();
        $this->assertSame(1, AccountGroup::where('key', 'partners')->count());
        $this->assertSame(1, AccountCategory::where('name', 'Customs')->count());
    }

    public function test_notes_remind_and_files_are_kept_with_accounts_and_papers(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->business();
        $this->owner();
        $ahmad = $this->party('customers', 'Ahmad');

        $this->get('/accounts/customers/'.$ahmad->id.'/notes')->assertOk();
        $this->post('/accounts/customers/'.$ahmad->id.'/notes', ['note' => 'Said he will pay on Thursday', 'remind_on' => today_iso()])->assertSessionHasNoErrors();
        $this->post('/accounts/customers/'.$ahmad->id.'/notes', ['note' => 'Prefers USD'])->assertSessionHasNoErrors();
        $note = \App\Models\PartyNote::whereNotNull('remind_on')->firstOrFail();
        $this->get('/')->assertOk()->assertSee('1 reminders are due.');
        $this->get('/reminders')->assertOk()->assertSee('Said he will pay on Thursday')->assertSee('Ahmad');
        $this->post('/notes/'.$note->id.'/done')->assertSessionHasNoErrors();
        $this->assertNotNull($note->fresh()->done_at);
        $this->get('/')->assertOk()->assertDontSee('reminders are due.');

        // a file with the account
        $this->get('/files/party/'.$ahmad->id)->assertOk();
        $this->post('/files/party/'.$ahmad->id, ['file' => \Illuminate\Http\UploadedFile::fake()->create('tazkira.pdf', 120, 'application/pdf')])->assertSessionHasNoErrors();
        $file = \App\Models\Attachment::firstOrFail();
        $this->assertSame(['party', 'tazkira.pdf'], [$file->subject_type, $file->name]);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($file->path);
        $this->get('/files/party/'.$ahmad->id.'/'.$file->id)->assertOk();
        // only what may be opened in a browser is taken
        $this->post('/files/party/'.$ahmad->id, ['file' => \Illuminate\Http\UploadedFile::fake()->create('run.exe', 10)])->assertSessionHasErrors('file');

        // and with a paper; a file of one record is not found under another
        $voucher = Vouchers::moneyIn($ahmad, $this->cash(), today_iso(), '100');
        $this->get('/files/voucher/'.$voucher->id)->assertOk()->assertSee($voucher->number);
        $this->get('/files/voucher/'.$voucher->id.'/'.$file->id)->assertNotFound();
        $this->delete('/files/party/'.$ahmad->id.'/'.$file->id)->assertSessionHasNoErrors();
        \Illuminate\Support\Facades\Storage::disk('local')->assertMissing($file->path);
        $this->assertSame(0, \App\Models\Attachment::count());
    }

    public function test_a_recurring_expense_is_recorded_once_a_month_by_itself(): void
    {
        $this->business();
        $owner = $this->owner();
        $this->openingCash('AFN', '5000');
        $cash = $this->cash();

        $this->get('/recurring-expenses')->assertOk();
        $this->post('/recurring-expenses', ['account_category_id' => AccountCategory::byKey('expense_rent')->id, 'paid_account_id' => $cash->id, 'amount' => '1000', 'day' => 1, 'description' => 'Shop rent', 'is_active' => 1])->assertSessionHasNoErrors();
        // its day has not come yet this month
        $this->post('/recurring-expenses', ['account_category_id' => AccountCategory::byKey('expense_telephone')->id, 'paid_account_id' => $cash->id, 'amount' => '300', 'day' => 28, 'is_active' => 1])->assertSessionHasNoErrors();

        $this->post('/recurring-expenses/run')->assertSessionHasNoErrors();
        $this->assertSame(1, \App\Models\Expense::count());
        $this->assertSame('4000.00', Ledger::balance($cash));
        // the same month again: nothing more
        $this->assertSame(0, \App\Services\OpeningBalances::runRecurring());
        $this->assertSame(1, \App\Models\Expense::count());
        // next month it is due again
        $this->travelTo(now()->addMonth());
        $this->assertSame(1, \App\Services\OpeningBalances::runRecurring());
        $this->assertSame('3000.00', Ledger::balance($cash));

        // one that cannot be paid is left, and the owner is told
        $this->post('/recurring-expenses', ['account_category_id' => AccountCategory::byKey('expense_staff')->id, 'paid_account_id' => $cash->id, 'amount' => '90000', 'day' => 1, 'description' => 'Salaries', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertSame(0, \App\Services\OpeningBalances::runRecurring());
        $this->assertSame('3000.00', Ledger::balance($cash));
        $this->assertTrue(\App\Models\Notice::where('user_id', $owner->id)->where('body', 'like', 'Salaries%')->exists());
        $this->booksBalance();
    }

    public function test_a_role_can_be_given_one_account_category_alone(): void
    {
        $this->business();
        $user = $this->userWith(['group-customers.view', 'group-customers.manage']);
        $ahmad = $this->party('customers', 'Ahmad');

        $this->get('/accounts/customers')->assertOk()->assertSee('Ahmad');
        $this->post('/accounts/customers', ['name' => 'Karim', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->get('/accounts/customers/'.$ahmad->id.'/statement')->assertOk();
        $this->get('/accounts/customers/'.$ahmad->id.'/notes')->assertOk();
        $this->get('/accounts/suppliers')->assertForbidden();
        $this->get('/accounts/cash')->assertForbidden();
        $this->get('/account-categories')->assertForbidden();
        $labels = array_column(collect(Menu::for($user))->firstWhere('label', 'Accounts')['children'], 'label');
        $this->assertSame(['Customers'], $labels);

        // the permissions page lists every category, the ones the business added too
        $this->owner();
        $this->post('/account-categories', ['name' => 'Agents', 'type' => 'party', 'icon' => 'users', 'sort' => 70, 'is_active' => 1])->assertSessionHasNoErrors();
        $this->get('/roles/create')->assertOk()->assertSee('group-agents.view', false);
        $this->assertContains('group-agents.manage', \App\Support\Permissions::all());
    }

    public function test_the_plan_of_a_business_limits_its_accounts_and_is_set_in_the_panel(): void
    {
        $tenant = $this->business();
        $this->central();
        $this->systemAdmin();
        $this->put('/admin/tenants/'.$tenant->id, ['name' => 'Acme business', 'plan' => 'Basic', 'max_users' => 3, 'max_accounts' => 2])->assertSessionHasNoErrors();
        $tenant->refresh();
        $this->assertSame(['Basic', 3, 2], [$tenant->plan, (int) $tenant->max_users, (int) $tenant->max_accounts]);

        $this->enter($tenant);
        $this->owner();
        // the cash box is the first account; one more fits, the next does not
        $this->post('/accounts/customers', ['name' => 'Ahmad', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->from('/accounts/customers/create')->post('/accounts/customers', ['name' => 'Karim', 'is_active' => 1])->assertSessionHas('danger');
        $this->assertSame(2, Party::count());
    }

    public function test_whatsapp_messages_go_out_and_never_stop_the_work(): void
    {
        \Illuminate\Support\Facades\Http::fake(['wa.example.test/*' => \Illuminate\Support\Facades\Http::response(['ok' => true])]);
        $this->business();
        $this->owner();
        $ahmad = Party::create(['account_group_id' => AccountGroup::byKey('customers')->id, 'name' => 'Ahmad', 'phone' => '0700 123 456'])->fresh();
        $cash = $this->cash();

        $this->get('/whatsapp')->assertOk();
        // nothing is sent while the messages are off
        Vouchers::moneyIn($ahmad, $cash, today_iso(), '100');
        \Illuminate\Support\Facades\Http::assertNothingSent();

        $this->put('/whatsapp', ['whatsapp_enabled' => 1, 'whatsapp_url' => 'https://wa.example.test/send', 'whatsapp_token' => 'secret-key', 'whatsapp_phone_field' => 'number', 'whatsapp_message_field' => 'message', 'whatsapp_country' => '93'])
            ->assertSessionHasNoErrors()->assertRedirect('/whatsapp');
        $this->post('/money-in', ['date' => today_iso(), 'party_id' => $ahmad->id, 'account_id' => $cash->id, 'amount' => '5000', 'due_date' => now()->addDay()->toDateString()])->assertSessionHasNoErrors();
        \Illuminate\Support\Facades\Http::assertSent(fn ($request) => $request->url() === 'https://wa.example.test/send' && $request['number'] === '93700123456'
            && str_contains($request['message'], '5,000.00') && str_contains($request['message'], 'Ahmad') && $request->hasHeader('Authorization', 'Bearer secret-key'));

        // the day before the payback date the party is reminded, once
        $this->assertSame(1, \App\Services\Whatsapp::remindDue());
        $this->assertSame(0, \App\Services\Whatsapp::remindDue());
        $this->post('/whatsapp/test', ['phone' => '0700000000'])->assertSessionHasNoErrors();
        // an empty key box keeps the key
        $this->put('/whatsapp', ['whatsapp_enabled' => 1, 'whatsapp_url' => 'https://wa.example.test/send', 'whatsapp_phone_field' => 'number', 'whatsapp_message_field' => 'message', 'whatsapp_country' => '93'])->assertSessionHasNoErrors();
        $this->assertSame('secret-key', setting('whatsapp_token'));

        // a sender that fails does not stop the paper
        $this->put('/whatsapp', ['whatsapp_enabled' => 1, 'whatsapp_url' => 'https://down.example.test/send', 'whatsapp_phone_field' => 'number', 'whatsapp_message_field' => 'message', 'whatsapp_country' => '93'])->assertSessionHasNoErrors();
        \Illuminate\Support\Facades\Http::fake(['down.example.test/*' => \Illuminate\Support\Facades\Http::response('no', 500)]);
        $this->post('/money-out', ['date' => today_iso(), 'party_id' => $ahmad->id, 'account_id' => $cash->id, 'amount' => '1000'])->assertSessionHasNoErrors();
        $this->assertSame(1, Voucher::where('kind', 'money_out')->count());
    }

    public function test_a_receipt_can_be_checked_by_anyone_who_holds_it(): void
    {
        $this->business();
        $this->owner();
        $ahmad = $this->party('customers', 'Ahmad');
        $voucher = Vouchers::moneyIn($ahmad, $this->cash(), today_iso(), '750');
        $code = \App\Http\Controllers\VoucherController::code($voucher);

        $this->get('/money-in/'.$voucher->id.'/receipt')->assertOk()->assertSee('/check/'.$voucher->number.'/'.$code)->assertSee('<svg', false);
        $this->get('/audit?action=signed in')->assertOk();

        auth()->logout();
        $this->get('/check/'.$voucher->number.'/'.$code)->assertOk()->assertSee('real and unchanged')->assertSee('750.00')->assertSee('Ahmad');
        $this->get('/check/'.$voucher->number.'/000000000000')->assertOk()->assertSee('Do not accept it')->assertDontSee('750.00');
        $this->get('/check/MI-999999/'.$code)->assertOk()->assertSee('Do not accept it');

        Vouchers::cancel($voucher, 'Mistake');
        $this->get('/check/'.$voucher->number.'/'.$code)->assertOk()->assertSee('was cancelled');
    }

    public function test_backdating_is_limited_and_a_repeated_phone_is_warned_about(): void
    {
        $this->business();
        $this->owner();
        $this->put('/settings', ['company_name' => 'Acme business', 'backdate_days' => 3])->assertSessionHasNoErrors();
        $ahmad = $this->party('customers', 'Ahmad');
        $cash = $this->cash();
        $old = now()->subDays(10)->toDateString();

        // the owner may date further back; a cashier may not
        $this->post('/money-in', ['date' => $old, 'party_id' => $ahmad->id, 'account_id' => $cash->id, 'amount' => '100'])->assertSessionHasNoErrors();
        $this->userWith(['transactions.view', 'transactions.manage']);
        $this->from('/money-in/create')->post('/money-in', ['date' => $old, 'party_id' => $ahmad->id, 'account_id' => $cash->id, 'amount' => '100'])->assertSessionHas('danger');
        $this->post('/money-in', ['date' => now()->subDays(2)->toDateString(), 'party_id' => $ahmad->id, 'account_id' => $cash->id, 'amount' => '100'])->assertSessionHasNoErrors();
        $this->assertSame(2, Voucher::count());

        $this->owner();
        $this->post('/accounts/customers', ['name' => 'Karim', 'phone' => '0700555666', 'is_active' => 1])->assertSessionHasNoErrors()->assertSessionMissing('warning');
        $this->post('/accounts/suppliers', ['name' => 'Karim Ltd', 'phone' => '0700555666', 'is_active' => 1])->assertSessionHasNoErrors()->assertSessionHas('warning');
        $this->assertSame(2, Party::where('phone', '0700555666')->count());
    }

    public function test_two_step_sign_in_asks_for_the_code_of_the_phone(): void
    {
        $this->business();
        $owner = $this->owner();
        $secret = fn () => \Illuminate\Support\Facades\Crypt::decryptString($owner->fresh()->two_factor_secret);

        $this->get('/two-step')->assertOk()->assertSee('Set it up');
        $this->post('/two-step')->assertRedirect('/two-step');
        $this->get('/two-step')->assertOk()->assertSee('<svg', false)->assertSee(substr($secret(), 0, 4));
        // nothing is asked at sign-in until a code has confirmed the phone
        $this->assertNull($owner->fresh()->two_factor_confirmed_at);
        $this->post('/two-step/confirm', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post('/two-step/confirm', ['code' => \App\Support\Totp::code($secret())])->assertSessionHasNoErrors();
        $this->assertNotNull($owner->fresh()->two_factor_confirmed_at);

        auth()->logout();
        $this->post('/login', ['email' => 'owner@acme.test', 'password' => 'password123'])->assertRedirect('/two-step/code');
        $this->assertGuest();
        $this->get('/two-step/code')->assertOk()->assertSee('six-digit code');
        $this->post('/two-step/code', ['code' => '123'])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->post('/two-step/code', ['code' => \App\Support\Totp::code($secret())])->assertRedirect('/');
        $this->assertAuthenticatedAs($owner);

        // off again, with the password
        $this->delete('/two-step', ['password' => 'wrong-password'])->assertSessionHasErrors('password');
        $this->delete('/two-step', ['password' => 'password123'])->assertSessionHasNoErrors();
        $this->assertNull($owner->fresh()->two_factor_secret);
        auth()->logout();
        $this->post('/login', ['email' => 'owner@acme.test', 'password' => 'password123'])->assertRedirect('/');
        // the page of the code is nothing without a password just before it
        auth()->logout();
        $this->get('/two-step/code')->assertRedirect('/login');
    }

    public function test_a_downloaded_backup_comes_back_only_when_the_system_made_it_and_old_copies_are_thinned(): void
    {
        $tenant = $this->business();
        $this->owner();
        $this->post('/backups')->assertSessionHasNoErrors();
        $backup = \App\Models\Central\Backup::where('tenant_id', $tenant->id)->firstOrFail();
        $bytes = file_get_contents($backup->path());

        $this->from('/backups')->post('/backups/upload', ['file' => \Illuminate\Http\UploadedFile::fake()->createWithContent(basename($backup->file), $bytes.'x')])->assertSessionHas('danger');
        $this->assertSame(1, \App\Models\Central\Backup::where('tenant_id', $tenant->id)->count());
        $this->post('/backups/upload', ['file' => \Illuminate\Http\UploadedFile::fake()->createWithContent(basename($backup->file), $bytes)])->assertSessionHasNoErrors();
        $this->assertSame(2, \App\Models\Central\Backup::where('tenant_id', $tenant->id)->count());

        // every copy of the last 7 days, then one a week, then one a month, and nothing older than about a year
        \App\Models\Central\Backup::where('tenant_id', $tenant->id)->delete();
        foreach ([1, 3, 8, 9, 40, 41, 500] as $age) {
            \App\Models\Central\Backup::create(['tenant_id' => $tenant->id, 'file' => 'acme/none-'.$age.'.sql.gz', 'size' => 1, 'kind' => 'auto', 'created_at' => now()->subDays($age)]);
        }
        $this->assertSame(3, \App\Services\Backups::thin());
        $left = \App\Models\Central\Backup::where('tenant_id', $tenant->id)->orderByDesc('created_at')->get()->map(fn ($b) => (int) round($b->created_at->diffInDays(now())))->all();
        $this->assertSame([1, 3, 8, 40], $left);
    }

    public function test_bank_accounts_credit_limits_and_unusual_rates_are_watched(): void
    {
        $this->business();
        $this->owner();
        $this->rates(['USD' => '70']);
        $this->openingCash('AFN', '5000');
        $cash = $this->cash();
        $bank = Accounts::party($this->party('bank', 'AIB'), $this->currency('AFN'));

        // a bank account does not go below zero, unless the business allows an overdraft
        $this->from('/transfers/create')->post('/transfers', ['date' => today_iso(), 'account_id' => $bank->id, 'other_account_id' => $cash->id, 'amount' => '100'])->assertSessionHas('danger');
        $this->put('/settings', ['company_name' => 'Acme business', 'bank_overdraft' => 1])->assertSessionHasNoErrors();
        $this->post('/transfers', ['date' => today_iso(), 'account_id' => $bank->id, 'other_account_id' => $cash->id, 'amount' => '100'])->assertSessionHasNoErrors();
        $this->assertSame('-100.00', Ledger::balance($bank));

        // a customer may not come to owe more than the credit limit
        $this->post('/accounts/customers', ['name' => 'Nazir', 'credit_limit' => '1000', 'is_active' => 1])->assertSessionHasNoErrors();
        $nazir = Party::where('name', 'Nazir')->firstOrFail();
        $this->post('/money-out', ['date' => today_iso(), 'party_id' => $nazir->id, 'account_id' => $cash->id, 'amount' => '800'])->assertSessionHasNoErrors();
        $this->from('/money-out/create')->post('/money-out', ['date' => today_iso(), 'party_id' => $nazir->id, 'account_id' => $cash->id, 'amount' => '300'])->assertSessionHas('danger');
        $this->assertSame('-800.00', $this->owed($nazir, 'AFN'));

        // an exchange far from the rates of the business is saved, and pointed out
        $this->post('/exchanges', ['date' => today_iso(), 'account_id' => $cash->id, 'amount' => '710', 'other_account_id' => $this->cash('USD')->id, 'other_amount' => '10'])->assertSessionHasNoErrors()->assertSessionMissing('warning');
        $this->post('/exchanges', ['date' => today_iso(), 'account_id' => $cash->id, 'amount' => '1000', 'other_account_id' => $this->cash('USD')->id, 'other_amount' => '100'])->assertSessionHasNoErrors()->assertSessionHas('warning');
        $this->booksBalance();
    }

    public function test_a_user_sees_where_he_is_signed_in_and_signs_another_browser_out(): void
    {
        $this->business();
        $owner = $this->owner();

        $this->get('/')->assertOk();
        $this->assertSame(1, \App\Models\UserSession::where('user_id', $owner->id)->count());
        // a second browser of the same user
        $other = \App\Models\UserSession::create(['user_id' => $owner->id, 'token' => \App\Models\UserSession::token('another-browser'), 'ip' => '10.0.0.9', 'agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/130.0', 'last_seen_at' => now()]);
        $this->get('/sessions')->assertOk()->assertSee('This browser')->assertSee('Chrome · Windows')->assertSee('10.0.0.9');
        $this->delete('/sessions/'.$other->id)->assertSessionHasNoErrors();
        $this->assertNull(\App\Models\UserSession::find($other->id));

        // the browser one is in is signed out with "Sign out", not from the list
        $mine = \App\Models\UserSession::where('user_id', $owner->id)->firstOrFail();
        $this->delete('/sessions/'.$mine->id)->assertStatus(422);

        // somebody else's session is not his to end; a user who manages users may end it
        $cashier = $this->userWith(['transactions.view']);
        $this->delete('/sessions/'.$mine->id)->assertForbidden();
        $this->get('/sessions')->assertOk()->assertViewHas('sessions', fn ($list) => $list->count() === 1 && (int) $list->first()->user_id === (int) $cashier->id);

        // a session that was taken off the list ends at its next request
        $again = $this->owner();
        $this->get('/')->assertOk();
        \App\Models\UserSession::where('user_id', $again->id)->delete();
        $this->get('/')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_an_exchange_without_an_amount_received_uses_the_usual_rate(): void
    {
        $this->business();
        $this->owner();
        $this->rates(['USD' => '70']);
        $this->openingCash('AFN', '100000');
        $afn = $this->cash('AFN');
        $usd = $this->cash('USD');
        $buy = fn (array $more) => $this->post('/exchanges', ['date' => today_iso(), 'account_id' => $afn->id, 'other_account_id' => $usd->id] + $more)->assertSessionHasNoErrors();
        $last = fn () => Voucher::where('kind', 'exchange')->orderByDesc('id')->firstOrFail();

        // no exchange yet: the rate of the business (1 AFN = 1/70 USD)
        $buy(['amount' => '7000']);
        $this->assertSame('100.00', $last()->other_amount);
        // a rate typed by hand
        $buy(['amount' => '7100', 'rate' => '0.014']);
        $this->assertSame('99.40', $last()->other_amount);
        // both amounts typed: the rate follows from them
        $buy(['amount' => '7200', 'other_amount' => '100']);
        $this->assertSame('100.00', $last()->other_amount);
        // nothing typed now: the average of the exchanges before it
        $usual = \App\Http\Controllers\ExchangeController::usualRate($afn, $usd);
        $this->assertSame(\App\Support\Num::round(\App\Support\Num::div(\App\Support\Num::sum(Voucher::where('kind', 'exchange')->pluck('rate')), 3), 6), $usual);
        $buy(['amount' => '1000']);
        $this->assertSame(\App\Support\Num::money(\App\Support\Num::mul('1000', $usual)), $last()->other_amount);
        $this->booksBalance();
    }

    public function test_a_user_sees_only_what_the_role_allows(): void
    {
        $this->business();
        $this->userWith(['transactions.view']);

        $this->get('/money-in')->assertOk();
        $this->get('/money-in/create')->assertForbidden();
        $this->get('/accounts/customers')->assertForbidden();
        $this->get('/reports/balance-sheet')->assertForbidden();
        $this->get('/account-categories')->assertForbidden();
    }
}
