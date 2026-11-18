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
        $this->assertSame(['pro', true], [$demo->plan, $demo->isActive()]);

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
        $this->get('/')->assertOk()->assertSee('Everything recorded here is erased every night.');

        // it holds a month of example papers
        $this->assertSame([5, 5], [Invoice::count(), Party::where('is_system', false)->whereHas('group', fn ($q) => $q->where('type', 'party'))->count()]);
        $this->assertGreaterThan(5, Voucher::count());
        $this->get(route('trade.index', ['kind' => 'sales']))->assertOk()->assertSee('Ahmad Karimi');

        // what would lock the next visitor out is switched off
        $this->put(route('password.update'), ['current_password' => 'demo1234', 'password' => 'another-password', 'password_confirmation' => 'another-password'])->assertSessionHas('warning');
        $this->assertTrue(Hash::check('demo1234', User::first()->password));

        // the night after, what a visitor recorded is gone
        Party::create(['account_group_id' => \App\Models\AccountGroup::byKey('customers')->id, 'name' => 'A visitor was here']);
        auth()->logout();
        $this->central();
        Demo::refresh();
        $this->enter($demo->fresh());
        $this->assertSame([0, 5], [Party::where('name', 'A visitor was here')->count(), Invoice::count()]);
    }

    public function test_an_ordinary_business_is_not_a_demo(): void
    {
        $this->business();
        $this->get('/login')->assertOk()->assertDontSee('This is the demo.');
        $this->owner();
        $this->get('/')->assertOk()->assertDontSee('erased every night');
    }
}
