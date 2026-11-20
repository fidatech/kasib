<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Central\Admin;
use App\Models\Central\Tenant;
use App\Models\User;
use App\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentralPanelTest extends TestCase
{
    use RefreshDatabase;

    private function form(array $over = []): array
    {
        return $over + [
            'name' => 'Acme business',
            'slug' => 'acme',
            'max_branches' => 2,
            'owner_name' => 'Owner One',
            'owner_email' => 'owner@acme.test',
            'owner_password' => 'password123',
        ];
    }

    public function test_guests_are_sent_to_the_admin_sign_in_page(): void
    {
        $this->get('/admin/tenants')->assertRedirect(route('admin.login'));
        $this->get('/')->assertOk()->assertSee('Register your business');   // the public website
        $this->get('/admin/login')->assertOk();
    }

    public function test_a_system_administrator_can_sign_in(): void
    {
        Admin::create(['name' => 'System Admin', 'email' => 'admin@fida.test', 'password' => 'password123']);

        $this->post('/admin/login', ['email' => 'admin@fida.test', 'password' => 'wrong-password'])->assertSessionHasErrors('email');
        $this->post('/admin/login', ['email' => 'admin@fida.test', 'password' => 'password123'])->assertRedirect(route('admin.home'));
        $this->get('/admin')->assertOk()->assertSee('Dashboard');
        $this->get('/admin/tenants')->assertOk();
    }

    public function test_opening_a_business_makes_its_database_main_branch_and_owner(): void
    {
        $this->systemAdmin();

        $this->get('/admin/tenants/create')->assertOk();
        $this->post('/admin/tenants', $this->form())->assertRedirect(route('admin.tenants.index'));

        $tenant = Tenant::where('slug', 'acme')->firstOrFail();
        $this->assertSame('acme.kasib.test', $tenant->domain);
        $this->assertSame('hb_acme', $tenant->database);
        $this->assertSame(2, $tenant->max_branches);
        $this->assertFileExists(app(Tenancy::class)->sqliteFile('hb_acme'));
        $this->get('/admin/tenants')->assertOk()->assertSee('Acme business');

        $this->enter($tenant);
        $this->assertSame(1, Branch::where('type', Branch::MAIN)->count());
        $this->assertSame(['owner@acme.test'], User::pluck('email')->all());
        $this->assertSame('Acme business', setting('company_name'));

        $this->post('/login', ['email' => 'owner@acme.test', 'password' => 'password123'])->assertRedirect(route('dashboard'));

        // the owner lands on the dashboard, with the account categories in the menu
        $this->get('/')->assertOk()->assertSee('quickbar-item', false)->assertSee('Cash Accounts')->assertSee('Customers')->assertSee('Daily Cash Count')->assertDontSee('Period Closing');
    }

    public function test_the_short_name_is_checked(): void
    {
        $this->systemAdmin();

        $this->post('/admin/tenants', $this->form(['slug' => 'admin']))->assertSessionHasErrors('slug');
        $this->post('/admin/tenants', $this->form(['slug' => 'Has Spaces']))->assertSessionHasErrors('slug');
        $this->assertSame(0, Tenant::count());

        $this->post('/admin/tenants', $this->form())->assertSessionHasNoErrors();
        $this->post('/admin/tenants', $this->form())->assertSessionHasErrors('slug');
        $this->assertSame(1, Tenant::count());
    }

    public function test_a_suspended_business_cannot_be_opened(): void
    {
        $this->systemAdmin();
        $this->post('/admin/tenants', $this->form());
        $tenant = Tenant::firstOrFail();

        $this->enter($tenant);
        $this->get('/login')->assertOk();

        $this->central();
        $this->post('/admin/tenants/'.$tenant->id.'/status', ['status' => 'suspended'])->assertRedirect();
        $this->assertSame('suspended', $tenant->fresh()->status);
        $this->get('http://acme.kasib.test/login')->assertStatus(503);

        $this->post('/admin/tenants/'.$tenant->id.'/status', ['status' => 'active']);
        $this->enter($tenant->fresh());
        $this->get('/login')->assertOk();
    }

    public function test_the_licence_of_a_business_can_be_changed(): void
    {
        $this->systemAdmin();
        $this->post('/admin/tenants', $this->form());
        $tenant = Tenant::firstOrFail();

        $this->get('/admin/tenants/'.$tenant->id.'/edit')->assertOk();
        $this->put('/admin/tenants/'.$tenant->id, ['name' => 'Acme Exchange', 'max_branches' => 5, 'main_branch_price' => '1200', 'branch_price' => '400'])
            ->assertRedirect(route('admin.tenants.index'));

        $tenant->refresh();
        $this->assertSame('Acme Exchange', $tenant->name);
        $this->assertSame(5, $tenant->max_branches);
        $this->assertSame('acme.kasib.test', $tenant->domain);
        $this->assertSame('400.00', $tenant->branch_price);
    }

    public function test_an_unknown_address_is_not_found(): void
    {
        $this->get('http://nobody.kasib.test/login')->assertNotFound();
    }

    public function test_the_panel_cannot_be_opened_on_the_address_of_a_sarafi(): void
    {
        $this->sarafi();

        $this->get('/admin/login')->assertNotFound();
        $this->get('/admin/tenants')->assertNotFound();
    }

    public function test_two_sarafis_do_not_see_each_other(): void
    {
        $acme = $this->sarafi();
        Branch::create(['name' => 'Herat', 'code' => 'HRT', 'type' => Branch::DEPENDENT]);
        $this->central();

        $beta = $this->sarafi(['name' => 'Beta business', 'slug' => 'beta', 'owner_email' => 'owner@beta.test']);
        $this->assertSame(['owner@beta.test'], User::pluck('email')->all());
        $this->assertSame(1, Branch::count());

        $this->enter($acme);
        $this->assertSame(['owner@acme.test'], User::pluck('email')->all());
        $this->assertSame(2, Branch::count());

        // the owner of one business cannot sign in to the other
        $this->enter($beta);
        $this->post('/login', ['email' => 'owner@acme.test', 'password' => 'password123'])->assertSessionHasErrors('email');
    }

    public function test_commands_create_an_administrator_and_a_sarafi(): void
    {
        $this->artisan('app:create-admin', ['--name' => 'Root', '--email' => 'root@fida.test', '--password' => 'password123'])->assertSuccessful();
        $this->assertSame(1, Admin::where('email', 'root@fida.test')->count());

        $this->artisan('tenants:create', ['slug' => 'gamma', '--name' => 'Gamma business', '--owner-name' => 'Owner', '--owner-email' => 'owner@gamma.test', '--owner-password' => 'password123'])->assertSuccessful();
        $this->assertSame('hb_gamma', Tenant::where('slug', 'gamma')->value('database'));

        $this->artisan('tenants:migrate')->assertSuccessful();
    }

    public function test_on_cpanel_hosting_the_panel_makes_the_database_and_the_address(): void
    {
        config(['tenancy.cpanel' => ['user' => 'prime', 'token' => 'secret-token', 'url' => 'https://127.0.0.1:2083', 'docroot' => 'public_html'], 'tenancy.base_domain' => 'primesarafi.com']);
        \Illuminate\Support\Facades\Http::fake([
            '*/execute/Mysql/delete_database' => \Illuminate\Support\Facades\Http::response(['status' => 0, 'errors' => ['The database does not exist.']]),
            '*' => \Illuminate\Support\Facades\Http::response(['status' => 1, 'errors' => null, 'data' => null]),
        ]);
        $cpanel = app(\App\Tenancy\Cpanel::class);
        $this->assertTrue($cpanel->enabled());

        // the database of a business, with the rights of the MySQL user of the system on it
        $cpanel->createDatabase('prime_s_demo', 'prime_sarafi');
        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => str_ends_with($r->url(), '/execute/Mysql/create_database') && $r['name'] === 'prime_s_demo' && $r->header('Authorization')[0] === 'cpanel prime:secret-token');
        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => str_ends_with($r->url(), '/execute/Mysql/set_privileges_on_database') && $r['user'] === 'prime_sarafi' && $r['database'] === 'prime_s_demo');

        // what the panel refuses is said in its own words
        try {
            $cpanel->dropDatabase('prime_s_gone');
            $this->fail('The refusal of the panel was not noticed.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('The database does not exist.', $e->getMessage());
        }

        // opening a business makes its address under the base domain; a domain of its own is left to its owner
        $tenant = $this->sarafi(['slug' => 'kabul', 'owner_email' => 'owner@kabul.test'], false);
        $this->assertSame('kabul.primesarafi.com', $tenant->domain);
        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => str_ends_with($r->url(), '/execute/SubDomain/addsubdomain') && $r['domain'] === 'kabul' && $r['rootdomain'] === 'primesarafi.com' && $r['dir'] === 'public_html');
        $this->assertFalse($cpanel->addAddress('www.another-sarafi.af'));

        // with a wildcard address and certificate no subdomain is made: the new address works at once
        config(['tenancy.cpanel.wildcard' => true]);
        $asked = count(\Illuminate\Support\Facades\Http::recorded());
        $this->assertTrue($cpanel->addAddress('herat.primesarafi.com'));
        $this->assertCount($asked, \Illuminate\Support\Facades\Http::recorded());

        // without the token nothing is asked of a panel
        config(['tenancy.cpanel.token' => null]);
        $this->assertFalse($cpanel->enabled());
    }

    public function test_a_business_registers_on_the_website_and_is_opened_when_the_administrator_approves(): void
    {
        config(['tenancy.base_domain' => 'kasib.test']);
        $this->central();
        \App\Models\Central\CentralSetting::put('trial_enabled', '0');   // without the free trial a request waits for the administrator

        // the public website: what the system is, the prices and the form; search engines may list it
        $this->get('/')->assertOk()->assertSee('Register your business')->assertSee('$200')->assertSee('$400')->assertSee('Starter')->assertSee('Pro')->assertSee('Already a client?')->assertDontSee('Da Afghanistan Bank')->assertDontSee('noindex');
        $this->get('/register')->assertOk()->assertSee('Send the request');
        // the provinces and their cities come from the database, in the language of the page
        $this->assertSame([34, 405], [\App\Models\Central\Province::count(), \App\Models\Central\City::count()]);
        $this->get('/register')->assertOk()->assertSee('Kandahar')->assertSee('Spin Boldak')->assertSee('select2.min.js')->assertSee('register.js');
        app()->setLocale('ps');
        $this->assertSame(['ننګرهار', 'جلال‌آباد'], [\App\Models\Central\Province::where('name', 'Nangarhar')->first()->label(), \App\Models\Central\City::where('name', 'Jalalabad')->first()->label()]);
        app()->setLocale('en');
        foreach (['vendor/jquery/jquery.min.js', 'vendor/select2/select2.min.js', 'vendor/select2/select2.min.css', 'vendor/select2/i18n/fa.js', 'vendor/select2/i18n/ps.js', 'js/register.js', 'css/register.css'] as $file) {
            $this->assertFileExists(public_path($file));
        }
        // the form asks while the visitor types whether the address is free
        $this->getJson('/register/check?slug=kabul')->assertOk()->assertJsonPath('free', true);
        $this->getJson('/register/check?slug=admin')->assertOk()->assertJsonPath('free', false);
        $this->getJson('/register/check?slug=a+b')->assertOk()->assertJsonPath('free', false);
        $this->get('/robots.txt')->assertOk()->assertSee('Allow: /');

        $form = ['name' => 'Kabul Exchange', 'slug' => 'Kabul', 'licence_no' => 'DAB-55', 'province' => 'Kabul', 'city' => 'Paghman', 'branches' => 2, 'owner_name' => 'Karim Khan',
            'owner_email' => 'karim@kabul.test', 'owner_phone' => '0700123456', 'password' => 'a-good-password', 'password_confirmation' => 'a-good-password', 'message' => 'Call after noon.'];
        // what is refused: a taken or reserved address, a short password, the box only robots fill
        $this->post('/register', ['slug' => 'admin'] + $form)->assertSessionHasErrors('slug');
        $this->post('/register', ['slug' => 'a b'] + $form)->assertSessionHasErrors('slug');
        $this->post('/register', ['province' => 'Atlantis'] + $form)->assertSessionHasErrors('province');
        $this->post('/register', ['password' => 'short', 'password_confirmation' => 'short'] + $form)->assertSessionHasErrors('password');
        $this->post('/register', ['website' => 'http://spam.test'] + $form)->assertRedirect('/');
        $this->assertSame(0, \App\Models\Central\Signup::count());

        // the request waits; nothing is opened by itself
        $response = $this->post('/register', $form)->assertSessionHasNoErrors();
        $signup = \App\Models\Central\Signup::sole();
        $response->assertRedirect('/register/'.$signup->token);
        $this->assertSame(['kabul', 'pending', 2], [$signup->slug, $signup->status, $signup->branches]);
        $this->assertNotSame('a-good-password', $signup->password);
        $this->assertSame(0, \App\Models\Central\Tenant::count());
        $this->get('/register/'.$signup->token)->assertOk()->assertSee('We have your request')->assertSee('kabul.kasib.test');
        $this->get('/register/'.str_repeat('a', 32))->assertNotFound();
        $this->post('/register', ['owner_email' => 'other@kabul.test'] + $form)->assertSessionHasErrors('slug');   // the address is asked for already

        // the administrator sees it, with a count in the menu
        $this->get('/admin/signups')->assertRedirect('/admin/login');
        $admin = \App\Models\Central\Admin::create(['name' => 'System Admin', 'email' => 'admin@fida.test', 'password' => 'password123', 'role' => 'owner']);
        $this->actingAs($admin, 'admin');
        $this->get('/admin/signups')->assertOk()->assertSee('Kabul Exchange')->assertSee('Karim Khan')->assertSee('Call after noon.');
        $this->get('/admin')->assertOk()->assertSee('Registration request')->assertSee('Kabul Exchange');   // the bell of the navbar

        // a second request is rejected, with the reason the applicant reads
        $this->post('/register', ['name' => 'Bad Exchange', 'slug' => 'bad', 'owner_email' => 'bad@x.test'] + $form)->assertSessionHasNoErrors();
        $bad = \App\Models\Central\Signup::where('slug', 'bad')->sole();
        $this->actingAs($admin, 'admin');
        $this->post('/admin/signups/'.$bad->id.'/reject', [])->assertSessionHasErrors('reason');
        $this->post('/admin/signups/'.$bad->id.'/reject', ['reason' => 'No licence of the bank.'])->assertSessionHas('success');
        $this->assertSame(['rejected', null], [$bad->fresh()->status, $bad->fresh()->password]);
        $this->post('/admin/signups/'.$bad->id.'/approve', [])->assertSessionHas('danger');
        $this->assertSame(0, \App\Models\Central\Tenant::count());

        // approval opens the business, and its owner signs in with the password chosen on the website
        $this->post('/admin/signups/'.$signup->id.'/approve', ['slug' => 'kabul', 'licence_expires_on' => '2027-11-13', 'max_branches' => 2])->assertSessionHasNoErrors()->assertSessionHas('success');
        $tenant = \App\Models\Central\Tenant::sole();
        $this->assertSame(['kabul.kasib.test', 'Kabul Exchange', 'DAB-55', 2, '0700123456'], [$tenant->domain, $tenant->name, $tenant->licence_no, $tenant->max_branches, $tenant->owner_phone]);
        $this->assertSame(['approved', $tenant->id, null, $admin->id], [$signup->fresh()->status, $signup->fresh()->tenant_id, $signup->fresh()->password, $signup->fresh()->decided_by]);
        $this->get('/admin/signups?status=approved')->assertOk()->assertSee('kabul.kasib.test');
        $this->get('/admin/signups?status=rejected')->assertOk()->assertSee('No licence of the bank.');
        $this->get('/register/'.$signup->token)->assertOk()->assertSee('Your business is ready');
        $this->get('/register/'.$bad->token)->assertOk()->assertSee('No licence of the bank.');
        $this->get('/open?address=kabul')->assertRedirect('http://kabul.kasib.test/login');
        $this->get('/open?address=nobody')->assertRedirect('/#signin')->assertSessionHas('danger');

        $this->enter($tenant);
        $owner = \App\Models\User::where('email', 'karim@kabul.test')->sole();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('a-good-password', $owner->password));
    }
}
