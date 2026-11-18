<?php

namespace Tests\Feature;

use App\Models\Central\Tenant;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Demo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** The demo: a business anybody may open, with its address, user and password always shown. */
class DemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_demo_is_shown_on_the_website_and_anybody_signs_in_to_it(): void
    {
        $this->central();
        // while there is no demo the website says nothing about one
        $this->get('/')->assertOk()->assertDontSee('Try the demo');

        $this->artisan('demo:refresh')->assertSuccessful();
        $demo = Tenant::where('slug', 'demo')->sole();
        $this->assertSame(['pro', true, null], [$demo->plan, $demo->isActive(), $demo->licence_expires_on]);

        // its address, user and password are on the home page and beside the registration form
        $this->central();
        $this->get('/')->assertOk()->assertSee('Try the demo')->assertSee($demo->domain)->assertSee('demo@kasib.af')->assertSee('demo1234');
        $this->get('/register')->assertOk()->assertSee('Open the demo')->assertSee('demo1234');
        // nobody registers the address of the demo for himself
        $this->getJson('/register/check?slug=demo')->assertJsonPath('free', false);

        // on its own sign-in page they are written and already in the boxes
        $this->enter($demo);
        $this->get('/login')->assertOk()->assertSee('This is the demo.')->assertSee('value="demo@kasib.af"', false)->assertSee('value="demo1234"', false);
        $this->post('/login', ['email' => 'demo@kasib.af', 'password' => 'demo1234'])->assertRedirect();
        $this->assertAuthenticated();
        $this->get('/')->assertOk()->assertSee('Everything recorded here is erased at midnight (12:00 AM).');

        // it holds a month of example papers
        // it holds two months of a food wholesaler: partners, a bank, customers, suppliers, stock, sales, expenses
        $this->assertSame(12, Party::where('is_system', false)->whereHas('group', fn ($q) => $q->where('type', 'party'))->count());
        $this->assertGreaterThan(80, Invoice::where('kind', 'sale')->count());
        $this->assertGreaterThan(8, Invoice::where('kind', 'purchase')->count());
        $this->assertGreaterThan(20, Voucher::count());
        $this->assertGreaterThan(8, \App\Models\Expense::count());
        $this->assertSame([14, 2, 0], [\App\Models\Item::count(), \App\Models\Shareholder::count(), \App\Models\Alert::count()]);
        $papers = Invoice::count();
        $this->get(route('trade.index', ['kind' => 'sales']))->assertOk();
        $this->get(route('accounts.index', ['group' => 'customers']))->assertOk()->assertSee('Popal Restaurant');
        $this->get('/reports/profit-and-loss')->assertOk();
        $this->get('/reports/stock')->assertOk()->assertSee('Rice Sela 25 kg');
        // the guide of the screen starts by itself for a visitor who has not seen it, on any page
        $this->get('/reports/stock')->assertSee('auto\u0022:true', false);
        // the licence of the demo never ends
        $demo->forceFill(['licence_expires_on' => now()->subYear()->toDateString()])->save();
        \App\Services\Licence::check();
        $this->assertTrue($demo->fresh()->isActive());

        // what would lock the next visitor out is switched off
        $this->put(route('password.update'), ['current_password' => 'demo1234', 'password' => 'another-password', 'password_confirmation' => 'another-password'])->assertSessionHas('warning');
        $this->assertTrue(Hash::check('demo1234', User::first()->password));

        // the night after, what a visitor recorded is gone
        Party::create(['account_group_id' => \App\Models\AccountGroup::byKey('customers')->id, 'name' => 'A visitor was here']);
        auth()->logout();
        $this->central();
        Demo::refresh();
        $this->enter($demo->fresh());
        $this->assertSame([0, $papers], [Party::where('name', 'A visitor was here')->count(), Invoice::count()]);
        $this->assertNull($demo->fresh()->licence_expires_on);
    }

    public function test_an_ordinary_business_is_not_a_demo(): void
    {
        $this->business();
        $this->get('/login')->assertOk()->assertDontSee('This is the demo.');
        $this->owner();
        $this->get('/')->assertOk()->assertDontSee('erased at midnight')->assertSee('auto\u0022:true', false);
        $this->get('/reports/profit-and-loss')->assertOk()->assertSee('auto\u0022:false', false);
    }
}
