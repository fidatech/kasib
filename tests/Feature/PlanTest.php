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
}
