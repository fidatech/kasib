<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
        \App\Services\Rates::set($main, $usd, '70');
        $ahmad = \App\Models\Party::create(['account_group_id' => \App\Models\AccountGroup::byKey('customers')->id, 'name' => 'Ahmad Zia'])->fresh();
        \App\Services\Vouchers::moneyIn($ahmad, \App\Services\Accounts::cash($main, $afn), today_iso(), '5000');
        $funder = \App\Models\Party::create(['account_group_id' => \App\Models\AccountGroup::byKey('suppliers')->id, 'name' => 'Funder'])->fresh();
        \App\Services\Vouchers::moneyIn($funder, \App\Services\Accounts::cash($main, $usd), today_iso(), '1000');
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

    public function test_every_paper_on_an_account_sends_its_balance_in_each_currency_and_the_statement_goes_on_whatsapp(): void
    {
        $this->business();
        $this->owner();
        $main = \App\Models\Branch::main();
        $afn = \App\Models\Currency::base();
        $usd = \App\Models\Currency::where('code', 'USD')->firstOrFail();
        \App\Services\Rates::set($main, $usd, '70');
        $cash = fn ($c) => \App\Services\Accounts::cash($main, $c);
        $ahmad = \App\Models\Party::create(['account_group_id' => \App\Models\AccountGroup::byKey('customers')->id, 'name' => 'Ahmad Zia', 'phone' => '0700 111 222'])->fresh();
        $saraf = \App\Models\Party::create(['account_group_id' => \App\Models\AccountGroup::byKey('saraf')->id, 'name' => 'Haji Qasim Saraf', 'phone' => '0799 333 444'])->fresh();

        // without a sender the statement page offers WhatsApp itself, with the message written
        \App\Services\Vouchers::moneyIn($ahmad, $cash($afn), today_iso(), '5000');
        $this->get('/accounts/customers/'.$ahmad->id.'/statement')->assertOk()->assertSee('btn-wa', false)->assertSee('https://wa.me/93700111222?text=', false)->assertSee('Send the balance of every currency');

        // with the sender of the business every paper is told, ending with the balance of every currency
        \App\Models\Setting::updateOrCreate(['key' => 'whatsapp_enabled'], ['value' => '1']);
        \App\Models\Setting::updateOrCreate(['key' => 'whatsapp_url'], ['value' => 'https://wa.example.test/send']);
        app()->forgetInstance('erp.settings');
        Http::fake(['wa.example.test/*' => Http::response(['ok' => true])]);
        \App\Services\Vouchers::moneyIn($ahmad, $cash($usd), today_iso(), '300');
        Http::assertSent(fn ($r) => $r['number'] === '93700111222' && str_contains($r['message'], 'Received from Ahmad Zia: 300.00 USD')
            && str_contains($r['message'], '• AFN 5,000.00 we owe you') && str_contains($r['message'], '• USD 300.00 we owe you'));
        // a transfer to the saraf tells the saraf
        \App\Services\Vouchers::transfer($cash($afn), \App\Services\Accounts::party($saraf, $afn, $main), today_iso(), '2000');
        Http::assertSent(fn ($r) => $r['number'] === '93799333444' && str_contains($r['message'], 'Transfer') && str_contains($r['message'], '• AFN 2,000.00 you owe us'));
        // an invoice with its payment is one message, not two
        $this->business(['plan' => 'pro', 'slug' => 'beta', 'name' => 'Beta', 'owner_email' => 'owner@beta.test']);
        $this->owner();
        \App\Models\Setting::updateOrCreate(['key' => 'whatsapp_enabled'], ['value' => '1']);
        \App\Models\Setting::updateOrCreate(['key' => 'whatsapp_url'], ['value' => 'https://wa.example.test/send']);
        app()->forgetInstance('erp.settings');
        $karim = \App\Models\Party::create(['account_group_id' => \App\Models\AccountGroup::byKey('customers')->id, 'name' => 'Karim', 'phone' => '0788 555 666'])->fresh();
        $rice = \App\Models\Item::create(['name' => 'Rice', 'unit' => 'bag', 'sale_price' => '100', 'cost_price' => '80', 'track_stock' => false]);
        Http::fake(['wa.example.test/*' => Http::response(['ok' => true])]);
        \App\Services\Trade::record(\App\Models\Invoice::SALE, $karim, \App\Models\Currency::base(), today_iso(), [['item_id' => $rice->id, 'qty' => '3', 'price' => '100']], 0, '100', \App\Services\Accounts::cash(\App\Models\Branch::main(), \App\Models\Currency::base()));
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r['number'] === '93788555666' && str_contains($r['message'], 'Sale') && str_contains($r['message'], '300.00') && str_contains($r['message'], '• AFN 200.00 you owe us'));

        // the statement and the balance alone are sent from the statement page
        $this->enter(\App\Models\Central\Tenant::where('slug', 'acme')->firstOrFail());
        $this->owner();
        Http::fake(['wa.example.test/*' => Http::response(['ok' => true])]);
        $this->get('/accounts/customers/'.$ahmad->id.'/statement?currency='.$afn->id)->assertOk()->assertSee('Send this statement');
        $this->post('/accounts/customers/'.$ahmad->id.'/statement/whatsapp?currency='.$afn->id, ['what' => 'statement'])->assertSessionHas('success');
        Http::assertSent(fn ($r) => str_contains($r['message'], 'Statement of Ahmad Zia · AFN') && str_contains($r['message'], 'Closing balance: 5,000.00 AFN we owe you') && str_contains($r['message'], '• USD 300.00 we owe you'));
        $this->post('/accounts/customers/'.$ahmad->id.'/statement/whatsapp', ['what' => 'balance'])->assertSessionHas('success');
        Http::assertSent(fn ($r) => str_contains($r['message'], 'Balance of Ahmad Zia') && ! str_contains($r['message'], 'Statement'));
        // an account without a phone: the button is there but cannot be used
        $nophone = \App\Models\Party::create(['account_group_id' => \App\Models\AccountGroup::byKey('suppliers')->id, 'name' => 'No Phone'])->fresh();
        $this->get('/accounts/suppliers/'.$nophone->id.'/statement')->assertOk()->assertSee('Add a phone number to the account to send it on WhatsApp.');
        $this->post('/accounts/suppliers/'.$nophone->id.'/statement/whatsapp', ['what' => 'balance'])->assertSessionHas('danger');
    }
}
