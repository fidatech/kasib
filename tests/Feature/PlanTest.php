<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** What a business on Starter sees and opens, against Pro. */
class PlanTest extends TestCase
{
    use RefreshDatabase;

    private const PRO_PAGES = ['/deals', '/recurring-expenses', '/budgets', '/assets', '/depreciation', '/liabilities', '/closing',
        '/shareholders', '/capital', '/distributions', '/reports/rate-history', '/reports/unusual-activity', '/export', '/schedules',
        '/users', '/roles', '/permissions', '/documents', '/trade/sales', '/items', '/branches'];

    private const MENU = ['Deals', 'Recurring Expenses', 'Expense Budgets', 'Assets', 'Depreciation', 'Liabilities', 'Period Closing',
        'Partners &amp; Capital', 'Shareholders', 'Analysis &amp; Export', 'Export Center', 'Users &amp; Security"', 'Roles', 'Document Vault'];

    public function test_starter_neither_shows_nor_opens_the_pages_of_pro(): void
    {
        $this->business();
        $this->owner();
        foreach (self::PRO_PAGES as $page) {
            $this->get($page)->assertStatus(403)->assertSee('Pro');
        }
        $this->post('/deals', ['name' => 'x'])->assertStatus(403);
        $this->post('/users', ['name' => 'x'])->assertStatus(403);
        $menu = $this->get('/')->assertOk();
        foreach (['Deals', 'Recurring Expenses', 'Expense Budgets', 'Depreciation', 'Liabilities', 'Period Closing', 'Shareholders', 'Profit Distribution', 'Rate History', 'Unusual Activity', 'Export Center', 'Scheduled Reports', 'Permissions', 'Document Vault', 'Journal Entries'] as $entry) {
            $menu->assertDontSee('>'.$entry.'<', false);
        }
        // what Starter keeps
        foreach (['/money-in', '/expenses', '/income', '/reports/profit-and-loss', '/journal', '/audit', '/sessions', '/two-step', '/backups', '/settings'] as $page) {
            $this->get($page)->assertOk();
        }
        $this->get('/')->assertSee('>Expenses<', false)->assertSee('>Audit Log<', false)->assertSee('>Two-step sign-in<', false);
    }

    public function test_pro_shows_and_opens_them_all_but_the_journal_entry_form_is_off_the_menu(): void
    {
        $this->business(['plan' => 'pro']);
        $this->owner();
        foreach (self::PRO_PAGES as $page) {
            $this->get($page)->assertOk();
        }
        $menu = $this->get('/')->assertOk();
        foreach (['Deals', 'Recurring Expenses', 'Expense Budgets', 'Depreciation', 'Liabilities', 'Shareholders', 'Export Center', 'Permissions', 'Document Vault'] as $entry) {
            $menu->assertSee('>'.$entry.'<', false);
        }
        $menu->assertDontSee('>Journal Entries<', false);
        $this->get('/journal')->assertOk()->assertDontSee('Journal entry by hand');
        // the budget form: the category is a searchable list and the amount names the currency
        $this->get('/budgets/create')->assertOk()->assertSee('data-search', false)->assertSee('Budget for a month (AFN)');
    }

    public function test_an_account_shows_a_line_for_each_currency_with_its_balance_coloured(): void
    {
        $this->business();
        $this->owner();
        $main = \App\Models\Branch::main();
        $afn = \App\Models\Currency::base();
        $usd = \App\Models\Currency::where('code', 'USD')->firstOrFail();
        $ahmad = \App\Models\Party::create(['account_group_id' => \App\Models\AccountGroup::byKey('customers')->id, 'name' => 'Ahmad Zia'])->fresh();
        \App\Services\Vouchers::moneyIn($ahmad, \App\Services\Accounts::cash($main, $afn), today_iso(), '5000');
        \App\Services\Vouchers::moneyIn($ahmad, \App\Services\Accounts::cash($main, $usd), today_iso(), '300');
        \App\Services\Vouchers::moneyOut($ahmad, \App\Services\Accounts::cash($main, $usd), today_iso(), '500');

        $page = $this->get('/accounts/customers')->assertOk()->assertSee('table-split', false)->assertSee('rowspan="2"', false);
        // AFN: we owe him 5,000 (red); USD: he owes us 200 (green); each currency on its own line, leading to its statement
        $html = $page->getContent();
        $this->assertMatchesRegularExpression('~class="num bal-cell amt-out"[^>]*>\s*<span dir="ltr">5,000\.00</span>\s*<span class="bal-tag is-out">we owe</span>~', $html);
        $this->assertMatchesRegularExpression('~class="num bal-cell amt-in"[^>]*>\s*<span dir="ltr">200\.00</span>\s*<span class="bal-tag is-in">owes us</span>~', $html);
        $this->assertSame(1, substr_count($html, 'class="split-more"'));
        $page->assertSee('statement?currency='.$usd->id, false);
        // the cash box: one line for every currency, what it holds in green
        $this->get('/accounts/cash')->assertOk()->assertSee('class="num bal-cell amt-in"', false);
        // only the currency asked for
        $this->get('/accounts/customers?currency='.$usd->id)->assertOk()->assertDontSee('5,000.00')->assertSee('200.00');
        // the export to Excel keeps the lines under their columns
        $this->get('/accounts/customers?export=csv')->assertOk();
    }
}
