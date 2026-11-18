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

    public function test_a_hawala_is_followed_until_it_is_delivered(): void
    {
        $this->business();
        $this->owner();
        $this->openingCash('AFN', '50000');
        $cash = $this->cash();
        $ahmad = $this->party('Ahmad');
        $this->get('/hawalas')->assertOk();
        $this->get('/hawalas/create')->assertOk();

        $form = ['date' => today_iso(), 'account_id' => $cash->id, 'receiver' => 'Yusuf', 'destination' => 'Herat'];
        $this->post('/hawalas', $form + ['number' => 'HW-778', 'amount' => '3000', 'party_id' => $ahmad->id])->assertSessionHasNoErrors();
        $this->post('/hawalas', $form + ['number' => 'HW-779', 'amount' => '500'])->assertSessionHasNoErrors();
        [$first, $second] = \App\Models\Hawala::orderBy('id')->get()->all();
        // sent for Ahmad: the money out is on his account; the other one is only followed
        $this->assertNotNull($first->voucher_id);
        $this->assertNull($second->voucher_id);
        $this->assertSame('-3000.00', Vouchers::balances($ahmad->fresh())[0]['amount']);

        $this->post('/hawalas/'.$second->id.'/deliver')->assertSessionHas('success');
        $this->assertSame('delivered', $second->fresh()->status);
        $this->post('/hawalas/'.$second->id.'/deliver')->assertNotFound();
        $this->post('/hawalas/'.$first->id.'/cancel')->assertSessionHas('success');
        $this->assertSame('cancelled', $first->fresh()->status);
        $this->assertSame([], Vouchers::balances($ahmad->fresh()));

        // never deleted
        $this->delete('/hawalas/'.$first->id);
        $this->assertNotNull(\App\Models\Hawala::find($first->id));
        $this->get('/hawalas?status=delivered')->assertOk()->assertSee('HW-779')->assertDontSee('HW-778');
    }

    public function test_the_lines_of_a_bank_account_are_ticked_against_the_statement(): void
    {
        $this->business();
        $this->owner();
        $this->openingCash('AFN', '10000');
        $cash = $this->cash();
        $ahmad = $this->party('Ahmad');
        Vouchers::moneyIn($ahmad, $cash, today_iso(), '2000');
        Vouchers::moneyOut($ahmad, $cash, today_iso(), '500');
        $lines = \App\Models\JournalLine::where('account_id', $cash->id)->orderBy('id')->pluck('id')->all();
        $other = \App\Models\JournalLine::where('account_id', '!=', $cash->id)->value('id');
        $this->assertCount(3, $lines);

        $this->get('/reconciliation')->assertOk()->assertSee('Choose an account');
        $this->put('/reconciliation', ['account' => $cash->id, 'shown' => array_merge($lines, [$other]), 'cleared' => [$lines[0], $lines[1], $other]])->assertSessionHasNoErrors();
        $this->assertSame(2, \Illuminate\Support\Facades\DB::table('cleared_lines')->count());
        // 11,500 in the books, 12,000 of it ticked, the bank says 12,000
        $this->get('/reconciliation?from=2020-01-01&statement=12000&account='.$cash->id)->assertOk()->assertSee('11,500.00')->assertSee('12,000.00')->assertSee('Statement less ticked');
        $this->get('/reconciliation?from=2020-01-01&show=open&account='.$cash->id)->assertOk();

        $this->put('/reconciliation', ['account' => $cash->id, 'shown' => $lines, 'cleared' => [$lines[0]]])->assertSessionHasNoErrors();
        $this->assertSame([$lines[0]], \Illuminate\Support\Facades\DB::table('cleared_lines')->pluck('journal_line_id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_a_cashier_counts_his_box_and_hands_it_over(): void
    {
        $this->business();
        $ali = $this->userWith(['transactions.view', 'transactions.manage']);
        $owner = $this->owner();
        $this->openingCash('AFN', '10000');
        $main = Accounts::cashBox();
        $box = Party::create(['account_group_id' => AccountGroup::byKey('cash')->id, 'name' => 'Box of Ali'])->fresh();
        $afn = Currency::where('code', 'AFN')->firstOrFail();

        $this->put('/handovers/boxes', ['holder' => [$box->id => $ali->id]])->assertSessionHasNoErrors();
        $this->assertSame($ali->id, (int) $box->fresh()->user_id);
        $boxAccount = Accounts::party($box, $afn);
        Vouchers::transfer($this->cash(), $boxAccount, today_iso(), '3000');

        $this->actingAs($ali);
        $this->get('/handovers')->assertOk()->assertSee('Box of Ali');
        // his own box comes first in his forms
        $this->assertSame($box->id, (int) Accounts::money(Branch::main())->first()->party_id);
        // not the box of somebody else
        $this->post('/handovers', ['from' => $main->id, 'to' => $box->id, 'counted' => [$this->cash()->id => '100']]);
        $this->assertSame(0, \App\Models\Handover::count());

        $this->post('/handovers', ['from' => $box->id, 'to' => $main->id, 'counted' => [$boxAccount->id => '2900'], 'note' => 'End of shift'])->assertSessionHasNoErrors()->assertRedirect('/handovers');
        $handover = \App\Models\Handover::firstOrFail();
        $this->assertSame('-100.00', $handover->lines[0]['difference']);
        $this->assertSame('100.00', \App\Support\Num::money($boxAccount->fresh()->total->balance));
        $this->assertSame(1, Alert::where('kind', 'cash')->count());

        // the one who hands over does not accept his own handover
        $this->post('/handovers/'.$handover->id.'/accept');
        $this->assertNull($handover->fresh()->accepted_at);
        $this->actingAs($owner);
        $this->post('/handovers/'.$handover->id.'/accept')->assertSessionHas('success');
        $this->assertSame($owner->id, (int) $handover->fresh()->accepted_by);
        $this->get('/handovers')->assertOk()->assertSee('End of shift');
    }

    public function test_a_rate_alert_speaks_once_and_the_history_of_rates_is_kept(): void
    {
        $this->business();
        $owner = $this->owner();
        $this->rates(['USD' => '70']);
        $usd = Currency::where('code', 'USD')->firstOrFail();

        $this->post('/rate-alerts', ['currency_id' => $usd->id, 'direction' => 'above', 'rate' => '71', 'note' => 'Sell dollars', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->rates(['USD' => '70.5']);
        $this->assertSame(0, Alert::where('kind', 'rate')->count());
        $this->rates(['USD' => '71.2']);
        $this->assertSame(1, Alert::where('kind', 'rate')->count());
        $this->assertFalse(\App\Models\RateAlert::firstOrFail()->is_active);
        $this->assertTrue(\App\Models\Notice::where('user_id', $owner->id)->where('title', 'like', '%Sell dollars%')->exists());
        $this->rates(['USD' => '72']);
        $this->assertSame(1, Alert::where('kind', 'rate')->count());

        $this->get('/rate-alerts')->assertOk()->assertSee('Sell dollars');
        $this->get('/reports/rate-history')->assertOk()->assertSee('USD')->assertSee('71.2')->assertSee('70.5');
        $this->get('/reports/rate-history?currency='.$usd->id)->assertOk()->assertSee('72');
    }

    public function test_depositor_insights_show_who_holds_the_most(): void
    {
        $this->business();
        $this->owner();
        $cash = $this->cash();
        $ahmad = $this->party('Ahmad');
        Vouchers::moneyIn($ahmad, $cash, today_iso(), '9000');
        Vouchers::moneyIn($this->party('Karim'), $cash, today_iso(), '1000');
        Vouchers::moneyOut($ahmad, $cash, today_iso(), '1000');
        $this->party('Silent');

        $this->get('/reports/depositors')->assertOk()->assertSee('Ahmad')->assertSee('8,000.00')->assertSee('88.9%')->assertSee('11.1%')->assertSee('The five largest hold')->assertDontSee('Silent');
    }

    public function test_a_customer_reads_his_statement_from_a_private_link(): void
    {
        $this->business();
        $owner = $this->owner();
        $ahmad = $this->party('Ahmad');
        Vouchers::moneyIn($ahmad, $this->cash(), today_iso(), '4000');

        $this->get('/accounts/customers/'.$ahmad->id.'/statement')->assertOk()->assertSee('Make a link');
        $this->post('/accounts/customers/'.$ahmad->id.'/link')->assertSessionHas('success');
        $token = $ahmad->fresh()->share_token;
        $this->assertSame(40, strlen($token));
        $this->get('/accounts/customers/'.$ahmad->id.'/statement')->assertOk()->assertSee('/s/'.$token);

        auth()->logout();
        $this->get('/s/'.$token)->assertOk()->assertSee('Ahmad')->assertSee('4,000.00')->assertSee('We owe you');
        $this->get('/s/'.str_repeat('a', 40))->assertNotFound();
        $this->post('/accounts/customers/'.$ahmad->id.'/link')->assertRedirect();
        $this->assertSame($token, $ahmad->fresh()->share_token);

        // a new link ends the old one; switched off, nothing opens
        $this->actingAs($owner);
        $this->post('/accounts/customers/'.$ahmad->id.'/link')->assertSessionHas('success');
        $this->get('/s/'.$token)->assertNotFound();
        $this->delete('/accounts/customers/'.$ahmad->id.'/link')->assertSessionHas('success');
        $this->assertNull($ahmad->fresh()->share_token);
    }

    public function test_voice_notes_and_the_document_vault(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->business();
        $this->owner();
        $ahmad = $this->party('Ahmad');
        $fake = fn (string $name, string $mime) => \Illuminate\Http\UploadedFile::fake()->create($name, 100, $mime);

        $this->post('/files/party/'.$ahmad->id, ['file' => $fake('voice.mp3', 'audio/mpeg')])->assertSessionHasNoErrors();
        $this->post('/files/party/'.$ahmad->id, ['file' => $fake('tool.exe', 'application/x-msdownload')])->assertSessionHasErrors('file');
        $this->get('/files/party/'.$ahmad->id)->assertOk()->assertSee('voice.mp3')->assertSee('<audio', false)->assertSee('Record a voice note');

        $this->post('/documents', ['title' => 'Business licence', 'category' => 'licence', 'expires_on' => now()->addDays(5)->toDateString(), 'file' => $fake('licence.pdf', 'application/pdf')])->assertSessionHasNoErrors();
        $this->post('/documents', ['title' => 'No file', 'category' => 'other'])->assertSessionHasErrors('file');
        $document = \App\Models\Document::firstOrFail();
        $this->get('/documents')->assertOk()->assertSee('Business licence')->assertSee('In 5 days');
        $this->get('/documents?category=tax')->assertOk()->assertDontSee('Business licence');
        $this->get('/documents/'.$document->id)->assertOk()->assertHeader('Content-Type', 'application/pdf');

        // the owner is told once that it is about to expire, and it is on the calendar
        $this->assertSame(1, \App\Services\Schedules::expiring());
        $this->assertSame(0, \App\Services\Schedules::expiring());
        $this->assertSame(1, Alert::where('kind', 'document')->count());
        Vouchers::moneyIn($ahmad, $this->cash(), today_iso(), '900', now()->addDays(3)->toDateString());
        $this->get('/calendar')->assertOk()->assertSee('Business licence')->assertSee('Ahmad');
        $this->get('/calendar?month=1')->assertOk()->assertDontSee('Business licence');
        $this->get('/calendar?month=-1')->assertOk();

        $this->delete('/documents/'.$document->id)->assertSessionHas('success');
        $this->assertSame(0, \App\Models\Document::count());
    }

    public function test_a_scheduled_report_leaves_by_email_on_its_day(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $this->business();
        $this->owner();
        $this->openingCash('AFN', '1000');
        $this->post('/schedules', ['name' => 'Daily papers', 'email' => 'owner@example.com', 'content' => 'papers', 'frequency' => 'daily', 'day' => 1, 'hour' => now()->hour, 'is_active' => 1])->assertSessionHasNoErrors();
        $schedule = \App\Models\ReportSchedule::firstOrFail();

        $this->assertSame(1, \App\Services\Schedules::tick());
        $this->assertSame(0, \App\Services\Schedules::tick());
        $this->assertSame(today_iso(), $schedule->fresh()->last_sent_on->toDateString());

        // every week: the tests run on a Friday, the seventh day
        $schedule->update(['frequency' => 'weekly', 'day' => 1, 'last_sent_on' => null]);
        $this->assertSame(0, \App\Services\Schedules::tick());
        $schedule->update(['day' => 7]);
        $this->assertSame(1, \App\Services\Schedules::tick());
        $this->assertSame([now()->subDays(6)->toDateString(), today_iso()], \App\Services\Schedules::period($schedule->fresh()));

        $this->post('/schedules/'.$schedule->id.'/send')->assertSessionHas('success');
        $this->get('/schedules')->assertOk()->assertSee('Daily papers');
    }

    public function test_the_zakat_report_counts_wealth_less_debts(): void
    {
        $this->business();
        $this->owner();
        $this->openingCash('AFN', '100000');
        $cash = $this->cash();
        Vouchers::moneyIn($this->party('Ahmad'), $cash, today_iso(), '20000');
        Vouchers::moneyOut($this->party('Karim'), $cash, today_iso(), '5000');

        // 115,000 in the box and 5,000 owed to the business, less 20,000 it owes: 100,000
        $this->get('/reports/zakat?nisab=50000')->assertOk()->assertSee('120,000.00')->assertSee('100,000.00')->assertSee('2,500.00')->assertSee('qualified scholar');
        // the nisab is remembered
        $this->get('/reports/zakat')->assertOk()->assertSee('2,500.00');
        $this->get('/reports/zakat?nisab=200000')->assertOk()->assertSee('Below the nisab')->assertDontSee('2,500.00');
    }
}
