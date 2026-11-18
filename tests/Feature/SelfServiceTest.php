<?php

namespace Tests\Feature;

use App\Models\Central\CentralSetting;
use App\Models\Central\Invoice;
use App\Models\Central\Payment;
use App\Models\Central\PaymentRequest;
use App\Models\Central\Signup;
use App\Models\Central\Tenant;
use App\Services\PaymentRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** What a business does without the administrator: the free trial, paying from inside the system, the help page. */
class SelfServiceTest extends TestCase
{
    use RefreshDatabase;

    private function picture(): UploadedFile
    {
        return UploadedFile::fake()->create('payment.jpg', 60, 'image/jpeg');
    }

    public function test_with_the_free_trial_on_a_business_opens_as_soon_as_it_registers(): void
    {
        config(['tenancy.base_domain' => 'hesabbook.test']);
        $this->central();
        $form = ['name' => 'Kabul Exchange', 'slug' => 'kabul', 'owner_name' => 'Karim Khan', 'owner_email' => 'karim@kabul.test', 'owner_phone' => '0700123456',
            'password' => 'a-good-password', 'password_confirmation' => 'a-good-password'];
        $this->get('/register')->assertOk()->assertDontSee('Free for');

        CentralSetting::put('trial_enabled', '1');
        CentralSetting::put('trial_days', '10');
        $this->get('/register')->assertOk()->assertSee('Free for 10 days');
        $this->get('/')->assertOk()->assertSee('Free for 10 days');

        $this->post('/register', $form)->assertSessionHasNoErrors();
        $signup = Signup::sole();
        $tenant = Tenant::sole();
        $this->assertSame(['approved', $tenant->id, null], [$signup->status, (int) $signup->tenant_id, $signup->password]);
        $this->assertSame(['kabul', 'trial', now()->addDays(10)->toDateString()], [$tenant->slug, $tenant->plan, $tenant->licence_expires_on->toDateString()]);
        $this->get('/register/'.$signup->token)->assertOk()->assertSee('kabul.hesabbook.test');

        // the owner signs in with the password he chose
        $this->enter($tenant);
        $this->post('/login', ['email' => 'karim@kabul.test', 'password' => 'a-good-password'])->assertRedirect(route('dashboard'));
        $this->get('/help')->assertOk()->assertSee('Receive money from a customer')->assertSee('Show me around the screen');
        $this->get('/')->assertOk()->assertSee('ERP_TOUR', false);
    }

    public function test_a_business_pays_through_hesabpay_and_the_licence_moves_on_when_it_is_approved(): void
    {
        Storage::fake('local');
        Mail::fake();
        $tenant = $this->business(['licence_expires_on' => now()->addDays(5)->toDateString()]);
        $this->owner();
        CentralSetting::put('hesabpay_number', '0799123456');
        CentralSetting::put('help_videos', "Money in and out | https://videos.example.test/1\nnot a video line");

        $this->get('/service/licence')->assertOk()->assertSee('Pay the subscription through HesabPay')->assertSee('0799123456')->assertSee('500.00');
        $this->get('/help')->assertOk()->assertSee('Money in and out')->assertDontSee('not a video line');
        $this->post('/service/pay', ['amount' => '500', 'reference' => 'HP-778899'])->assertSessionHasErrors('picture');
        $this->post('/service/pay', ['amount' => '500', 'reference' => 'HP-778899', 'picture' => $this->picture()])->assertSessionHasNoErrors()->assertSessionHas('success');
        // the same transaction is not sent twice
        $this->post('/service/pay', ['amount' => '500', 'reference' => 'HP-778899', 'picture' => $this->picture()]);
        $request = PaymentRequest::sole();
        $this->assertSame(['pending', '500.00', 'Owner One'], [$request->status, (string) $request->amount, $request->sender]);
        $this->get('/service/licence')->assertOk()->assertSee('HP-778899');

        // the administrator checks it and approves: invoice, payment, and a year more
        $this->central();
        $this->get('/admin/payment-requests')->assertRedirect('/admin/login');
        $this->systemAdmin();
        $this->get('/admin/payment-requests')->assertOk()->assertSee('HP-778899')->assertSee('Acme business');
        $this->get('/admin/payment-requests/'.$request->id.'/picture')->assertOk();
        $this->post('/admin/payment-requests/'.$request->id.'/approve', ['amount' => '500'])->assertSessionHas('success');
        $this->assertSame('approved', $request->fresh()->status);
        $this->assertSame(['paid', '500.00'], [Invoice::sole()->status, (string) Invoice::sole()->total]);
        $this->assertSame(['hesabpay', 'HP-778899'], [Payment::sole()->method, Payment::sole()->reference]);
        $this->assertSame(now()->addDays(5)->addYear()->toDateString(), $tenant->fresh()->licence_expires_on->toDateString());
        $this->post('/admin/payment-requests/'.$request->id.'/approve', ['amount' => '500']);
        $this->assertSame(1, Payment::count());

        // a second one is rejected, with a reason from the list
        $second = PaymentRequests::submit($tenant, '100', 'HP-000002', null, 'Owner One');
        $this->post('/admin/payment-requests/'.$second->id.'/reject', ['reason' => 'because'])->assertSessionHasErrors('reason');
        $this->post('/admin/payment-requests/'.$second->id.'/reject', ['reason' => 'not_received'])->assertSessionHas('success');
        $this->assertSame(['rejected', 'not_received'], [$second->fresh()->status, $second->fresh()->reason]);
        $this->get('/admin/payment-requests?status=rejected')->assertOk()->assertSee('The money did not arrive');
    }

    public function test_a_locked_business_sends_its_payment_from_the_locked_page_and_opens_again(): void
    {
        Storage::fake('local');
        Mail::fake();
        $tenant = $this->business();
        $tenant->update(['status' => Tenant::SUSPENDED, 'locked_reason' => 'licence', 'licence_expires_on' => now()->subDays(40)->toDateString()]);

        $this->get('/login')->assertStatus(503)->assertSee('Pay the subscription through HesabPay')->assertSee('500.00');
        $this->post('/paid', ['amount' => '', 'reference' => ''])->assertStatus(503);
        $this->assertSame(0, PaymentRequest::count());
        $this->post('/paid', ['amount' => '500', 'reference' => 'HP-LOCKED-1', 'sender' => 'Karim', 'picture' => $this->picture()])->assertOk()->assertSee('We have it');
        $request = PaymentRequest::sole();
        $this->assertSame(['HP-LOCKED-1', 'Karim'], [$request->reference, $request->sender]);

        $this->central();
        $admin = $this->systemAdmin();
        PaymentRequests::approve($request, '500', $admin->id);
        $this->assertSame(Tenant::ACTIVE, $tenant->fresh()->status);
        $this->assertSame(now()->addYear()->toDateString(), $tenant->fresh()->licence_expires_on->toDateString());

        // a business that was suspended by hand gets no payment form
        $tenant->update(['status' => Tenant::SUSPENDED, 'locked_reason' => null]);
        $this->enter($tenant->fresh());
        $this->get('/login')->assertStatus(503)->assertDontSee('Pay the subscription through HesabPay');
    }
}
