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
