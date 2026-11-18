<?php

namespace Tests\Feature;

use App\Models\Central\CentralSetting;
use App\Models\Central\Commission;
use App\Models\Central\Invoice;
use App\Models\Central\LinkedLogin;
use App\Models\Central\Payment;
use App\Models\Central\Seller;
use App\Models\Central\Tenant;
use App\Services\Commissions;
use App\Support\Num;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Version 2: sellers and referrals, white label, and several businesses under one login. */
class GrowthTest extends TestCase
{
    use RefreshDatabase;

    private function second(string $slug, array $more = []): Tenant
    {
        return $this->business(['name' => ucfirst($slug).' Trading', 'slug' => $slug, 'owner_email' => 'owner@'.$slug.'.test'] + $more);
    }

    private function pay(Tenant $tenant, string $amount): Payment
    {
        $this->post('/admin/invoices', ['tenant_id' => $tenant->id, 'from_date' => '2026-11-13', 'to_date' => '2027-11-12'])->assertSessionHasNoErrors();
        $invoice = Invoice::where('tenant_id', $tenant->id)->orderByDesc('id')->firstOrFail();
        $this->post('/admin/payments', ['tenant_id' => $tenant->id, 'invoice_id' => $invoice->id, 'amount' => $amount, 'method' => 'hesabpay', 'paid_on' => '2026-11-13'])->assertSessionHasNoErrors();

        return Payment::where('tenant_id', $tenant->id)->orderByDesc('id')->firstOrFail();
    }

    public function test_a_seller_earns_his_share_and_a_client_is_rewarded_for_a_referral(): void
    {
        $acme = $this->business();
        $beta = $this->second('beta');
        $gamma = $this->second('gamma');
        $this->central();
        $this->systemAdmin();

        $this->post('/admin/sellers', ['name' => 'Ahmad', 'code' => 'Ahmad', 'first_percent' => '20', 'renewal_percent' => '10'])->assertSessionHasNoErrors();
        $this->post('/admin/sellers', ['name' => 'Twin', 'code' => 'ahmad', 'first_percent' => '5', 'renewal_percent' => '5'])->assertSessionHasErrors('code');
        $this->post('/admin/sellers/reward', ['referral_reward' => '25'])->assertSessionHasNoErrors();
        $seller = Seller::sole();

        // Beta is given to the seller on its own page; Gamma came through the link of Acme
        $this->put('/admin/tenants/'.$beta->id, ['name' => $beta->name, 'seller_code' => 'ahmad'])->assertSessionHasNoErrors();
        Commissions::attach($gamma, 'ACME');
        $this->assertSame([$seller->id, $acme->id], [(int) $beta->fresh()->seller_id, (int) $gamma->fresh()->referred_by]);

        // Beta pays 200 for its first year: 20% to the seller. Gamma pays: 25 to Acme, once.
        $payment = $this->pay($beta, '200');
        $this->assertSame(['seller', '40.00', '20.00'], [Commission::sole()->kind, Num::money(Commission::sole()->amount), Num::money(Commission::sole()->percent)]);
        $this->pay($gamma, '100');
        $this->pay($gamma, '100');
        $referral = Commission::where('kind', 'referral')->sole();
        $this->assertSame([$acme->id, '25.00'], [(int) $referral->referrer_id, Num::money($referral->amount)]);

        $this->get('/admin/sellers')->assertOk()->assertSee('Ahmad')->assertSee('40.00')->assertSee('Acme business')->assertSee('65.00');
        $this->post('/admin/commissions/'.$referral->id.'/paid')->assertSessionHasNoErrors();
        $this->assertNotNull($referral->fresh()->paid_on);
        $this->get('/admin/sellers?show=paid')->assertOk()->assertSee('25.00');

        // the payment of Beta is cancelled: the commission that was not paid out goes with it
        $this->delete('/admin/payments/'.$payment->id)->assertRedirect();
        $this->assertNotNull(Commission::where('kind', 'seller')->sole()->cancelled_at);

        // a visitor who registers through the link of the seller is his client
        config(['tenancy.base_domain' => 'kasib.test']);
        auth('admin')->logout();
        $this->post('/register', ['name' => 'Kabul Exchange', 'slug' => 'kabul', 'owner_name' => 'Karim', 'owner_email' => 'karim@kabul.test', 'owner_phone' => '0700123456',
            'password' => 'a-good-password', 'password_confirmation' => 'a-good-password', 'ref' => 'ahmad'])->assertSessionHasNoErrors();
        $this->assertSame($seller->id, (int) Tenant::where('slug', 'kabul')->firstOrFail()->seller_id);

        // every business sees its own link while there is a reward
        $this->enter($acme);
        $this->owner();
        $this->get('/service/licence')->assertOk()->assertSee('/register?ref=acme');
    }

    public function test_a_white_label_business_shows_its_own_name(): void
    {
        $acme = $this->business();
        $this->get('/login')->assertOk()->assertSee('Fida Technologies');

        $acme->update(['brand_name' => 'Noor Books']);
        $this->get('/login')->assertOk()->assertSee('Noor Books')->assertDontSee('Fida Technologies');
        $this->owner();
        $this->get('/')->assertOk()->assertSee('Noor Books')->assertDontSee('Fida Technologies');
        $this->get('/service/support')->assertOk()->assertDontSee('Fida Technologies');

        $acme->update(['brand_name' => null]);
        $this->get('/')->assertOk()->assertSee('Fida Technologies');
    }

    public function test_a_person_links_his_businesses_and_goes_from_one_to_the_other_without_signing_in_again(): void
    {
        $acme = $this->business(['plan' => 'pro']);
        $beta = $this->second('beta', ['plan' => 'pro']);
        $this->enter($acme);
        $this->owner();

        $this->get('/businesses')->assertOk()->assertSee('No other business is linked yet');
        // proof is asked for: the address, the email and the password of the user there
        $this->post('/businesses', ['address' => 'beta', 'email' => 'owner@beta.test', 'password' => 'not-the-password'])->assertSessionHasErrors('address');
        $this->post('/businesses', ['address' => 'nowhere', 'email' => 'owner@beta.test', 'password' => 'password123'])->assertSessionHasErrors('address');
        $this->post('/businesses', ['address' => 'acme', 'email' => 'owner@acme.test', 'password' => 'password123'])->assertSessionHasErrors('address');
        $this->assertSame(0, LinkedLogin::count());
        $this->post('/businesses', ['address' => 'beta', 'email' => 'owner@beta.test', 'password' => 'password123'])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame(2, LinkedLogin::count());
        $this->get('/businesses')->assertOk()->assertSee('Beta Trading')->assertSee('owner@beta.test');

        // opening it hands over a pass that is good once
        $link = LinkedLogin::where('tenant_id', $beta->id)->sole();
        $location = $this->post('/businesses/'.$link->id.'/open')->assertRedirect()->headers->get('Location');
        $this->assertStringStartsWith('http://'.$beta->domain.'/enter/', $location);
        $token = substr($location, strrpos($location, '/') + 1);

        auth()->logout();
        $this->get('/enter/'.$token)->assertRedirect('/login');              // the pass is for Beta, not for Acme
        $this->enter($beta);
        $location = null;
        $this->get('/enter/'.$token)->assertRedirect('/login');              // and it was used up by that try
        $this->assertGuest();

        $this->enter($acme);
        $this->owner();
        $location = $this->post('/businesses/'.$link->id.'/open')->headers->get('Location');
        auth()->logout();
        $this->enter($beta);
        $this->get('/enter/'.substr($location, strrpos($location, '/') + 1))->assertRedirect(route('dashboard'));
        $this->assertSame('owner@beta.test', auth()->user()->email);
        // from Beta the way back is there too, and the link can be removed
        $this->get('/businesses')->assertOk()->assertSee('Acme business');
        $back = LinkedLogin::where('tenant_id', $acme->id)->sole();
        $this->delete('/businesses/'.$back->id)->assertSessionHas('success');
        $this->assertSame(1, LinkedLogin::count());
    }
}
