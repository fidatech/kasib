<?php

namespace Tests\Feature;

use App\Models\AccountGroup;
use App\Models\ApiKey;
use App\Models\AssistantQuestion;
use App\Models\Branch;
use App\Models\Central\CentralSetting;
use App\Models\Currency;
use App\Models\Party;
use App\Models\Voucher;
use App\Services\Accounts;
use App\Services\Vouchers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Version 2: the connection with another system (SarafBook) and the assistant. */
class ConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_another_system_reads_the_accounts_and_records_money_with_a_key(): void
    {
        $this->business(['plan' => 'pro']);
        $this->owner();
        $code = Currency::base()->code;

        $this->get('/connections')->assertOk()->assertSee('No key was made yet')->assertSee('/api/v1');
        $this->post('/connections', ['name' => 'SarafBook', 'can_write' => '1'])->assertSessionHasNoErrors()->assertSessionHas('new_key');
        $key = session('new_key');
        $this->post('/connections', ['name' => 'Reader', 'can_write' => '0'])->assertSessionHasNoErrors();
        $reader = session('new_key');
        $this->assertStringStartsWith('ksb_', $key);
        $this->assertSame(0, ApiKey::where('token', $key)->count());           // only its fingerprint is kept
        $this->get('/connections')->assertOk()->assertSee('SarafBook')->assertSee('Read only');
        auth()->logout();

        // no key, a wrong key: nothing
        $this->getJson('/api/v1/business')->assertStatus(401);
        $this->getJson('/api/v1/business', ['Authorization' => 'Bearer ksb_wrong'])->assertStatus(401);
        $with = ['Authorization' => 'Bearer '.$key];
        $this->getJson('/api/v1/business', $with)->assertOk()->assertJsonPath('name', 'Acme business')->assertJsonPath('base_currency', $code)->assertJsonPath('can_write', true);
        $this->getJson('/api/v1/rates', $with)->assertOk()->assertJsonPath('base_currency', $code);

        // a customer is made; the same phone again answers the same customer
        $id = $this->postJson('/api/v1/accounts', ['name' => 'Ahmad Zia', 'phone' => '0700111222', 'category' => 'customers'], $with)->assertStatus(201)->json('id');
        $this->postJson('/api/v1/accounts', ['name' => 'Ahmad again', 'phone' => '0700111222', 'category' => 'customers'], $with)->assertOk()->assertJsonPath('id', $id)->assertJsonPath('repeated', true);
        $this->postJson('/api/v1/accounts', ['name' => 'A box', 'category' => AccountGroup::where('type', AccountGroup::MONEY)->value('key')], $with)->assertStatus(422);

        // money in, sent twice with the same reference, is one paper
        $paper = ['account' => $id, 'currency' => $code, 'amount' => '500', 'reference' => 'SB-1001', 'note' => 'Deposit'];
        $number = $this->postJson('/api/v1/money-in', $paper, $with)->assertStatus(201)->assertJsonPath('amount', '500.00')->json('number');
        $this->postJson('/api/v1/money-in', $paper, $with)->assertOk()->assertJsonPath('number', $number)->assertJsonPath('repeated', true);
        $this->assertSame(1, Voucher::count());
        $this->postJson('/api/v1/money-out', ['account' => $id, 'currency' => $code, 'amount' => '200', 'reference' => 'SB-1002'], $with)->assertStatus(201);
        $this->postJson('/api/v1/money-in', ['account' => $id, 'currency' => 'XXX', 'amount' => '5'], $with)->assertStatus(422);
        $this->postJson('/api/v1/money-in', ['account' => $id, 'currency' => $code, 'amount' => '-5'], $with)->assertStatus(422);

        // what the business owes the customer is his balance
        $this->getJson('/api/v1/accounts?search=Ahmad', $with)->assertOk()->assertJsonPath('total', 1)->assertJsonPath('accounts.0.balances.'.$code, '300.00');
        $this->getJson('/api/v1/accounts/'.$id.'/statement?currency='.$code, $with)->assertOk()->assertJsonPath('closing', '300.00')->assertJsonCount(2, 'lines')->assertJsonPath('lines.0.balance', '500.00');
        $this->getJson('/api/v1/papers/SB-1001', $with)->assertOk()->assertJsonPath('number', $number)->assertJsonPath('cancelled', false);
        $this->getJson('/api/v1/papers/nothing', $with)->assertStatus(404);

        // a key that only reads does not record; a paper is cancelled by its reference
        $this->postJson('/api/v1/money-in', $paper, ['Authorization' => 'Bearer '.$reader])->assertStatus(403);
        $this->getJson('/api/v1/accounts/'.$id, ['Authorization' => 'Bearer '.$reader])->assertOk()->assertJsonPath('name', 'Ahmad Zia');
        $this->postJson('/api/v1/papers/SB-1002/cancel', ['reason' => 'Sent by mistake'], $with)->assertOk()->assertJsonPath('cancelled', true);
        $this->getJson('/api/v1/accounts/'.$id, $with)->assertOk()->assertJsonPath('balances.'.$code, '500.00');
        // the papers are in the books under the name of who made the key
        $this->assertSame(['SB-1001', (int) ApiKey::first()->user_id], [Voucher::where('number', $number)->value('reference'), (int) Voucher::where('number', $number)->value('created_by')]);

        // switched off, the key is nothing
        $this->owner();
        $this->delete('/connections/'.ApiKey::where('name', 'SarafBook')->value('id'))->assertSessionHas('success');
        auth()->logout();
        $this->getJson('/api/v1/business', $with)->assertStatus(401);
    }

    public function test_the_connection_belongs_to_the_pro_plan(): void
    {
        $this->business();
        $user = $this->owner();
        $this->get('/connections')->assertStatus(403);
        $this->get('/assistant')->assertStatus(403);
        [, $plain] = ApiKey::issue('Early', Branch::main()->id, true, $user->id);
        $this->getJson('/api/v1/business', ['Authorization' => 'Bearer '.$plain])->assertStatus(403);
    }

    public function test_the_owner_asks_the_assistant_about_his_business(): void
    {
        $this->business(['plan' => 'pro']);
        $this->owner();
        $this->get('/assistant')->assertOk()->assertSee('The assistant is not switched on yet.');
        $this->post('/assistant', ['question' => 'Who owes me?'])->assertSessionHas('danger');

        CentralSetting::put('assistant_key', Crypt::encryptString('sk-ant-test'));
        CentralSetting::put('assistant_daily_limit', '2');
        $karim = Party::create(['account_group_id' => AccountGroup::byKey('customers')->id, 'name' => 'Karim Wardak'])->fresh();
        Vouchers::moneyOut($karim, Accounts::cash(Branch::main(), Currency::base()), today_iso(), '750');

        Http::fake(['api.anthropic.com/*' => Http::sequence()
            ->push(['content' => [['type' => 'text', 'text' => 'Karim Wardak owes you 750.00.']], 'usage' => ['input_tokens' => 400, 'output_tokens' => 20]])
            ->push(['error' => ['message' => 'overloaded']], 529)
            ->push(['content' => [['type' => 'text', 'text' => 'Nobody else.']], 'usage' => ['input_tokens' => 420, 'output_tokens' => 5]])]);

        $this->get('/assistant')->assertOk()->assertSee('2 questions left today');
        $this->post('/assistant', ['question' => 'Who owes me the most?'])->assertRedirect('/assistant');
        Http::assertSent(fn ($request) => $request->hasHeader('x-api-key', 'sk-ant-test') && $request['model'] === 'claude-haiku-5-5'
            && str_contains($request['messages'][0]['content'], 'Karim Wardak') && str_contains($request['messages'][0]['content'], '750.00') && str_contains($request['messages'][0]['content'], 'Who owes me the most?'));
        $this->get('/assistant')->assertOk()->assertSee('Karim Wardak owes you 750.00.')->assertSee('1 questions left today');
        $this->assertSame(420, (int) AssistantQuestion::sole()->tokens);

        // the service fails: the owner is told, nothing is kept, the question is not counted
        $this->post('/assistant', ['question' => 'And who else?'])->assertSessionHas('danger');
        $this->assertSame(1, AssistantQuestion::count());
        // the next one carries the talk so far, and after it the day is used up
        $this->post('/assistant', ['question' => 'And who else?'])->assertRedirect('/assistant');
        Http::assertSent(fn ($request) => count($request['messages']) === 3 && $request['messages'][1]['content'] === 'Karim Wardak owes you 750.00.');
        $this->post('/assistant', ['question' => 'One more?'])->assertSessionHas('danger');
        $this->assertSame(2, AssistantQuestion::count());

        $this->delete('/assistant')->assertSessionHas('success');
        $this->assertSame(0, AssistantQuestion::count());

        // the administrator of the system writes the key on his settings page, where it is kept encrypted
        $this->central();
        $this->systemAdmin();
        $this->get('/admin/settings')->assertOk()->assertSee('Key of the Claude API')->assertDontSee('sk-ant-test');
    }
}
