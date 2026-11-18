<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\AccountGroup;
use App\Models\Branch;
use App\Models\BranchTransfer;
use App\Models\Currency;
use App\Models\Item;
use App\Models\Party;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Accounts;
use App\Services\ExpenseService;
use App\Services\Vouchers;
use App\Support\Num;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Version 2, the Pro plan: branches, each with books of its own. */
class BranchTest extends TestCase
{
    use RefreshDatabase;

    private function cash(Branch $branch, string $code = 'AFN'): Account
    {
        return Accounts::cash($branch, Currency::where('code', $code)->firstOrFail());
    }

    private function balance(Account $account): string
    {
        return Num::money($account->fresh()->total?->balance ?? 0);
    }

    /** Debits and credits are equal inside the books of every branch. */
    private function everyBranchBalances(): void
    {
        foreach (Branch::withBooks()->get() as $branch) {
            $sum = DB::table('account_balances as b')->join('accounts as a', 'a.id', '=', 'b.account_id')->where('a.branch_id', $branch->id)->sum('b.base_balance');
            $this->assertSame(0.0, round((float) $sum, 2), 'The books of '.$branch->name.' must balance.');
        }
    }

    public function test_every_branch_keeps_its_own_books(): void
    {
        $this->business(['plan' => 'pro']);
        $owner = $this->owner();
        $this->rates(['USD' => '70']);
        $this->openingCash('AFN', '100000');
        $main = Branch::main();
        $ahmad = Party::create(['account_group_id' => AccountGroup::byKey('customers')->id, 'name' => 'Ahmad'])->fresh();

        // a branch is added from the main branch, with its first user; it starts with the rates and a cash box
        $this->get('/branches')->assertOk()->assertSee('Main branch');
        $this->post('/branches', ['name' => 'Herat', 'code' => 'hrt', 'city' => 'Herat', 'is_active' => 1,
            'user_name' => 'Ali', 'user_email' => 'ali@acme.test', 'user_password' => 'a-good-password', 'user_role' => 'Cashier'])->assertSessionHasNoErrors();
        $herat = Branch::where('code', 'HRT')->firstOrFail();
        $ali = User::where('email', 'ali@acme.test')->firstOrFail();
        $this->assertSame([Branch::DEPENDENT, $herat->id], [$herat->type, (int) $ali->branch_id]);
        $this->assertNotNull(\App\Services\Rates::current($herat, Currency::where('code', 'USD')->firstOrFail()));
        $this->delete('/branches/'.$main->id);
        $this->assertNotNull(Branch::find($main->id));

        // in the main branch: Ahmad leaves 5,000
        $this->post('/money-in', ['date' => today_iso(), 'party_id' => $ahmad->id, 'account_id' => $this->cash($main)->id, 'amount' => '5000'])->assertSessionHasNoErrors();
        $this->assertSame('105000.00', $this->balance($this->cash($main)));
        $this->get('/money-in')->assertOk()->assertSee('MI-000001');

        // the owner switches to Herat: other books. Nothing of the main branch is there
        $this->post('/branches/switch', ['branch' => $herat->id])->assertRedirect();
        $this->get('/')->assertOk()->assertSee('Herat');
        $this->get('/money-in')->assertOk()->assertDontSee('MI-000001');
        $this->get('/accounts/customers')->assertOk()->assertSee('Ahmad')->assertDontSee('5,000.00');
        $this->post('/money-in', ['date' => today_iso(), 'party_id' => $ahmad->id, 'account_id' => $this->cash($main)->id, 'amount' => '10']);   // an account of another branch is refused
        $this->post('/money-in', ['date' => today_iso(), 'party_id' => $ahmad->id, 'account_id' => $this->cash($herat)->id, 'amount' => '700'])->assertSessionHasNoErrors();
        $this->assertSame([$herat->id, 2], [(int) Voucher::withoutGlobalScope('branch')->orderByDesc('id')->firstOrFail()->branch_id, Voucher::withoutGlobalScope('branch')->count()]);
        $this->assertSame(['700.00', '105000.00'], [$this->balance($this->cash($herat)), $this->balance($this->cash($main))]);
        $this->assertSame('700.00', Branch::as($herat, fn () => Vouchers::balances($ahmad->fresh())[0]['amount']));
        $this->assertSame('5000.00', Branch::as($main, fn () => Vouchers::balances($ahmad->fresh())[0]['amount']));
        // on WhatsApp and on his private link the customer sees what the whole business owes him
        $this->assertStringContainsString('5,700.00', \App\Services\Whatsapp::balance($ahmad));

        // an expense in Herat is in its profit and loss, not in that of the main branch
        $category = AccountCategory::where('nature', 'expense')->where('key', '!=', 'depreciation')->orderBy('sort')->firstOrFail();
        Branch::as($herat, fn () => ExpenseService::record($herat, $category, today_iso(), '300', $this->cash($herat)));
        $this->get('/reports/profit-and-loss')->assertOk()->assertSee('300.00');
        $this->post('/branches/switch', ['branch' => $main->id])->assertRedirect();
        $this->get('/reports/profit-and-loss')->assertOk()->assertDontSee('300.00');
        $this->get('/reports/profit-and-loss?branch=all')->assertOk()->assertSee('300.00')->assertSee('All branches together');
        $this->get('/reports/balance-sheet?branch=all')->assertOk();
        $this->get('/reports/balances?branch=all')->assertOk()->assertSee('Ahmad');

        // the user of Herat works in Herat only and cannot switch
        $this->actingAs($ali);
        $this->get('/money-in')->assertOk()->assertSee('MI-000002')->assertDontSee('MI-000001');
        $this->post('/branches/switch', ['branch' => $main->id])->assertForbidden();
        $this->get('/branches/create')->assertForbidden();

        $this->everyBranchBalances();
    }

    public function test_money_goes_from_one_branch_to_another_and_stock_is_kept_per_branch(): void
    {
        $this->business(['plan' => 'pro']);
        $owner = $this->owner();
        $this->openingCash('AFN', '100000');
        $main = Branch::main();
        $this->post('/branches', ['name' => 'Herat', 'code' => 'HRT', 'is_active' => 1])->assertSessionHasNoErrors();
        $herat = Branch::where('code', 'HRT')->firstOrFail();

        // 20,000 carried to Herat: out of one cash box, into the other, and each branch knows what it is owed
        $this->get('/branch-transfers/create')->assertOk()->assertSee('Herat');
        $this->post('/branch-transfers', ['date' => today_iso(), 'from_account_id' => $this->cash($main)->id, 'to_account_id' => $this->cash($herat)->id, 'amount' => '20000', 'note' => 'Opening float'])->assertSessionHasNoErrors();
        $transfer = BranchTransfer::sole();
        $this->assertSame(['BT-000001', '80000.00', '20000.00'], [$transfer->number, $this->balance($this->cash($main)), $this->balance($this->cash($herat))]);
        $this->assertSame('20000.00', $this->balance(Accounts::interBranch($main, $herat, Currency::base())));
        $this->assertSame('-20000.00', $this->balance(Accounts::interBranch($herat, $main, Currency::base())));
        $this->get('/branch-transfers')->assertOk()->assertSee('BT-000001')->assertSee('Opening float');
        // between two currencies, or inside one branch, it is refused
        $this->post('/branch-transfers', ['date' => today_iso(), 'from_account_id' => $this->cash($main)->id, 'to_account_id' => $this->cash($herat, 'USD')->id, 'amount' => '10']);
        $this->assertSame(1, BranchTransfer::count());
        $this->everyBranchBalances();

        // stock: bought in the main branch, it is not in Herat
        $supplier = Party::create(['account_group_id' => AccountGroup::byKey('suppliers')->id, 'name' => 'Haji Wholesale'])->fresh();
        $rice = Item::create(['name' => 'Rice', 'unit' => 'kg', 'sale_price' => '150', 'cost_price' => '100']);
        $buy = fn (Branch $branch, string $qty) => $this->post('/trade/purchases', ['date' => today_iso(), 'party_id' => $supplier->id, 'currency_id' => Currency::base()->id, 'lines' => [['item_id' => $rice->id, 'qty' => $qty, 'price' => '100']]]);
        $buy($main, '100')->assertSessionHasNoErrors();
        $this->assertSame('100.000', Num::round(Branch::as($main, fn () => Item::findOrFail($rice->id)->stock), 3));
        $this->assertSame('0.000', Num::round(Branch::as($herat, fn () => Item::findOrFail($rice->id)->stock), 3));

        $this->post('/branches/switch', ['branch' => $herat->id])->assertRedirect();
        $this->get('/reports/stock')->assertOk()->assertDontSee('100 kg');
        $this->get('/trade/purchases')->assertOk()->assertDontSee('PI-000001');
        $walkIn = \App\Services\Trade::walkIn();
        $sell = ['date' => today_iso(), 'party_id' => $walkIn->id, 'currency_id' => Currency::base()->id, 'lines' => [['item_id' => $rice->id, 'qty' => '5', 'price' => '150']], 'paid' => '750', 'account_id' => $this->cash($herat)->id];
        $this->post('/trade/sales', $sell);                                   // nothing to sell in Herat yet
        $this->assertSame(0, \App\Models\Invoice::where('kind', 'sale')->count());
        $buy($herat, '10')->assertSessionHasNoErrors();
        $this->post('/trade/sales', $sell)->assertSessionHasNoErrors();
        $this->assertSame('5.000', Num::round(Branch::as($herat, fn () => Item::findOrFail($rice->id)->stock), 3));
        $this->assertSame('100.000', Num::round(Branch::as($main, fn () => Item::findOrFail($rice->id)->stock), 3));
        $this->assertSame('20750.00', $this->balance($this->cash($herat)));

        // the branch that sent the money takes the transfer back, not the one that received it
        $this->post('/branch-transfers/'.$transfer->id.'/cancel')->assertNotFound();
        $this->post('/branches/switch', ['branch' => $main->id])->assertRedirect();
        $this->post('/branch-transfers/'.$transfer->id.'/cancel')->assertSessionHas('success');
        $this->assertSame(['100000.00', '750.00'], [$this->balance($this->cash($main)), $this->balance($this->cash($herat))]);
        $this->everyBranchBalances();
    }
}
