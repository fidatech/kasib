<?php

namespace Tests\Feature;

use App\Models\AccountCategory;
use App\Models\AccountGroup;
use App\Models\Alert;
use App\Models\BalanceConfirmation;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Party;
use App\Models\RecurringExpense;
use App\Services\Accounts;
use App\Services\ExpenseService;
use App\Services\Summary;
use App\Services\Vouchers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Version 1.5: control and insight. */
class InsightTest extends TestCase
{
    use RefreshDatabase;

    private function cash(string $code = 'AFN'): \App\Models\Account
    {
        return Accounts::cash(Branch::main(), Currency::where('code', $code)->firstOrFail());
    }

    private function party(string $name, ?string $phone = null, string $group = 'customers'): Party
    {
        return Party::create(['account_group_id' => AccountGroup::byKey($group)->id, 'name' => $name, 'phone' => $phone])->fresh();
    }

    private function whatsappOn(): void
    {
        Http::fake(['wa.example.test/*' => Http::response(['ok' => true])]);
        foreach (['whatsapp_enabled' => '1', 'whatsapp_url' => 'https://wa.example.test/send', 'owner_phone' => '0799 000 111'] as $key => $value) {
            Summary::remember($key, $value);
        }
    }

    private function expenseCategory(): AccountCategory
    {
        return AccountCategory::where('nature', 'expense')->where('key', '!=', 'depreciation')->orderBy('sort')->firstOrFail();
    }

    public function test_the_owner_is_told_about_unusual_papers(): void
    {
        $this->business();
        $this->owner();
        $settings = ['alerts_enabled' => 1, 'alert_large' => '50000', 'alert_backdate' => 2, 'summary_hour' => 20, 'confirm_day' => 0];
        $this->put('/owner', $settings)->assertSessionHasNoErrors()->assertRedirect('/owner');
        $this->get('/owner')->assertOk()->assertSee('Cash and bank');
        $ahmad = $this->party('Ahmad');
        $cash = $this->cash();

        Vouchers::moneyIn($ahmad, $cash, today_iso(), '1000');
        $this->assertSame(0, Alert::count());
        Vouchers::moneyIn($ahmad, $cash, today_iso(), '60000');
        $this->assertSame(1, Alert::where('kind', 'large')->count());
        $old = Vouchers::moneyIn($ahmad, $cash, now()->subDays(5)->toDateString(), '100');
        $this->assertSame(1, Alert::where('kind', 'backdated')->count());
        Vouchers::cancel($old, 'Mistake');
        $this->assertSame(1, Alert::where('kind', 'cancelled')->count());

        // the tests run at ten in the morning: outside working hours from twelve to six
        $this->put('/owner', $settings + ['alert_from' => 12, 'alert_to' => 18])->assertSessionHasNoErrors();
        Vouchers::moneyIn($ahmad, $cash, today_iso(), '10');
        $this->assertSame(1, Alert::where('kind', 'after_hours')->count());
        $this->put('/owner', $settings + ['alert_from' => 18, 'alert_to' => 12])->assertSessionHasErrors('alert_to');

        $this->get('/reports/unusual-activity')->assertOk()->assertSee('Large amount')->assertSee('Mistake')->assertSee('Outside working hours');
        $this->get('/reports/unusual-activity?kind=large')->assertOk()->assertDontSee('Mistake');

        // switched off: nothing more is written
        $this->put('/owner', ['alerts_enabled' => 0] + $settings)->assertSessionHasNoErrors();
        Vouchers::moneyIn($ahmad, $cash, today_iso(), '90000');
        $this->assertSame(1, Alert::where('kind', 'large')->count());
    }

    public function test_the_evening_summary_goes_to_the_owner_once_a_day(): void
    {
        $this->business();
        $this->owner();
        $this->whatsappOn();
        $this->openingCash('AFN', '100000');
        Vouchers::moneyIn($this->party('Ahmad'), $this->cash(), today_iso(), '5000', now()->addDay()->toDateString());

        $text = Summary::evening();
        $this->assertStringContainsString('105,000.00', $text);
        $this->assertStringContainsString('To be paid back tomorrow: 1', $text);

        // not switched on, or not the hour: nothing goes out
        $this->assertFalse(Summary::tick());
        Summary::remember('summary_enabled', '1');
        Summary::remember('summary_hour', (string) ((now()->hour + 1) % 24));
        $this->assertFalse(Summary::tick());
        Summary::remember('summary_hour', (string) now()->hour);
        $this->assertTrue(Summary::tick());
        $this->assertFalse(Summary::tick());
        Http::assertSent(fn ($request) => $request['number'] === '93799000111' && str_contains($request['message'], 'Cash and bank') && str_contains($request['message'], '105,000.00'));
        Http::assertSentCount(1);

        $this->post('/owner/summary')->assertSessionHas('success');
        $this->artisan('books:evening')->assertExitCode(0);
    }

    public function test_customers_are_sent_their_balance_and_the_answer_is_kept(): void
    {
        $this->business();
        $this->owner();
        $this->whatsappOn();
        $cash = $this->cash();
        Vouchers::moneyIn($this->party('Ahmad', '0700 123 456'), $cash, today_iso(), '700');
        Vouchers::moneyIn($this->party('Karim'), $cash, today_iso(), '300');          // no phone
        $this->party('Nobody', '0700 999 999');                                       // no balance

        $this->post('/confirmations/send')->assertSessionHas('success');
        $this->assertSame(1, BalanceConfirmation::count());
        $row = BalanceConfirmation::firstOrFail();
        $this->assertStringContainsString('700.00', $row->balance);
        Http::assertSent(fn ($request) => $request['number'] === '93700123456' && str_contains($request['message'], '700.00') && str_contains($request['message'], 'Ahmad'));
        // once in a month
        $this->post('/confirmations/send')->assertSessionHas('success');
        $this->assertSame(1, BalanceConfirmation::count());

        $this->post('/confirmations/'.$row->id, ['status' => 'confirmed', 'note' => 'By phone'])->assertSessionHasNoErrors();
        $this->assertSame('confirmed', $row->fresh()->status);
        $this->get('/confirmations')->assertOk()->assertSee('Ahmad')->assertSee('By phone');
        $this->get('/confirmations?status=disputed')->assertOk()->assertDontSee('By phone');

        // by itself on the day of the month the owner chose, once
        BalanceConfirmation::query()->delete();
        $this->assertSame(0, Summary::monthly());
        Summary::remember('confirm_day', (string) now()->day);
        $this->assertSame(1, Summary::monthly());
        $this->assertSame(0, Summary::monthly());
    }

    public function test_an_expense_budget_warns_at_eighty_percent_and_when_it_is_used_up(): void
    {
        $this->business();
        $this->owner();
        $this->openingCash('AFN', '100000');
        $category = $this->expenseCategory();
        $cash = $this->cash();

        $this->post('/budgets', ['account_category_id' => $category->id, 'amount' => '10000'])->assertSessionHasNoErrors();
        $this->post('/budgets', ['account_category_id' => $category->id, 'amount' => '500'])->assertSessionHasErrors('account_category_id');
        $spend = fn (string $amount) => ExpenseService::record(Branch::main(), $category, today_iso(), $amount, $cash);

        $spend('5000');
        $this->assertSame(0, Alert::where('kind', 'budget')->count());
        $spend('3500');
        $this->assertSame(1, Alert::where('kind', 'budget')->count());
        $spend('500');
        $this->assertSame(1, Alert::where('kind', 'budget')->count());
        $spend('2000');
        $this->assertSame(2, Alert::where('kind', 'budget')->count());
        $this->get('/budgets')->assertOk()->assertSee('110%')->assertSee('11,000.00');
    }

    public function test_the_cash_forecast_and_the_comparison_of_two_periods(): void
    {
        $this->business();
        $this->owner();
        $this->openingCash('AFN', '10000');
        $ahmad = $this->party('Ahmad');
        $cash = $this->cash();
        Vouchers::moneyIn($ahmad, $cash, today_iso(), '8000', now()->addDays(5)->toDateString());
        Vouchers::moneyIn($ahmad, $cash, today_iso(), '5000', now()->subDays(3)->toDateString());
        Vouchers::moneyOut($ahmad, $cash, today_iso(), '1000');
        $category = $this->expenseCategory();
        RecurringExpense::create(['account_category_id' => $category->id, 'paid_account_id' => $cash->id, 'amount' => '2000', 'day' => 15, 'is_active' => true]);

        // 22,000 in the box; 5,000 overdue, 7,000 of the 12,000 still owed falls due, 2,000 of rent: 8,000 left
        $this->get('/reports/cash-forecast')->assertOk()->assertSee('22,000.00')->assertSee('5,000.00')->assertSee('7,000.00')->assertSee('2,000.00')->assertSee('8,000.00')->assertSee('Enough');
        $this->get('/reports/cash-forecast?days=90')->assertOk()->assertSee('6,000.00');

        ExpenseService::record(Branch::main(), $category, today_iso(), '300', $cash);
        $this->get('/reports/profit-and-loss')->assertOk()->assertSee('300.00');
        $this->get('/reports/profit-and-loss?compare=previous')->assertOk()->assertSee('Change')->assertSee('300.00');
        $this->get('/reports/profit-and-loss?compare=year')->assertOk()->assertSee('Change');
        $this->get('/reports')->assertOk()->assertSee('Cash Forecast');
    }
}
