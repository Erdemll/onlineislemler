<?php

namespace Tests\Feature\Customer;

use App\Contracts\SmsSender;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeSmsSender;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private FakeSmsSender $sms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sms = new FakeSmsSender;

        $this->app->instance(SmsSender::class, $this->sms);
    }

    public function test_verified_customer_can_view_profile(): void
    {
        $customer = Customer::factory()->ready()->phoneVerified()->create();

        $response = $this
            ->actingAsCustomer($customer)
            ->get(route('customer.profile'));

        $response
            ->assertOk()
            ->assertSee($customer->first_name)
            ->assertSee($customer->last_name);
    }

    public function test_verified_customer_can_update_profile(): void
    {
        $customer = Customer::factory()->ready()->phoneVerified()->create();

        $response = $this
            ->actingAsCustomer($customer)
            ->patch(route('customer.profile.update'), [
                'first_name' => 'Erdem',
                'last_name' => 'Lale',
            ]);

        $response->assertSessionHas('status');

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'first_name' => 'Erdem',
            'last_name' => 'Lale',
        ]);
    }

    public function test_phone_change_code_is_sent_to_new_phone(): void
    {
        $customer = Customer::factory()->ready()->phoneVerified()->create([
            'email' => 'erdem@example.com',
            'phone' => '+905551112233',
        ]);

        $response = $this
            ->actingAsCustomer($customer)
            ->post(route('customer.phone.change.request'), [
                'phone' => '0555 999 88 77',
                'current_password' => 'TestPassword123!',
            ]);

        $response
            ->assertRedirect(route('customer.phone.change.verify.form'))
            ->assertSessionHas('pending_phone_change', '+905559998877');

        $this->assertCount(1, $this->sms->sent);
        $this->assertSame('+905559998877', $this->sms->sent[0]['phone']);

        $this->assertDatabaseHas('otp_verifications', [
            'customer_id' => $customer->id,
            'purpose' => 'phone_change',
        ]);
    }

    public function test_customer_can_confirm_phone_change_with_sms_code(): void
    {
        $customer = Customer::factory()->ready()->phoneVerified()->create([
            'email' => 'erdem@example.com',
            'phone' => '+905551112233',
            'phone_verified_at' => now(),
        ]);

        $this
            ->actingAsCustomer($customer)
            ->post(route('customer.phone.change.request'), [
                'phone' => '0555 999 88 77',
                'current_password' => 'TestPassword123!',
            ]);

        $code = $this->sms->lastCode();

        $this->assertNotNull($code);

        $response = $this->post(
            route('customer.phone.change.verify'),
            ['code' => $code]
        );

        $response
            ->assertRedirect(route('customer.profile'))
            ->assertSessionHas('status')
            ->assertSessionMissing('pending_phone_change')
            ->assertSessionHas('customer_session_version', 2);

        $customer->refresh();

        $this->assertSame('+905559998877', $customer->phone);
        $this->assertNotNull($customer->phone_verified_at);
        $this->assertSame(2, $customer->session_version);
    }

    public function test_wrong_current_password_does_not_start_phone_change(): void
    {
        $customer = Customer::factory()->ready()->phoneVerified()->create();

        $response = $this
            ->actingAsCustomer($customer)
            ->post(route('customer.phone.change.request'), [
                'phone' => '0555 999 88 77',
                'current_password' => 'wrong-password',
            ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertCount(0, $this->sms->sent);
        $this->assertDatabaseCount('otp_verifications', 0);
    }
}
