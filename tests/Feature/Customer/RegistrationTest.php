<?php

namespace Tests\Feature\Customer;

use App\Contracts\SmsSender;
use App\Models\Customer;
use App\Models\OtpVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Fakes\FakeSmsSender;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private FakeSmsSender $sms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sms = new FakeSmsSender();

        $this->app->instance(
            SmsSender::class,
            $this->sms
        );
    }

    public function test_customer_can_register(): void
    {
        $response = $this->post(
            route('customer.register.store'),
            [
                'first_name' => 'Erdem',
                'last_name' => 'Lale',

                'email' => 'ERDEM@example.com',

                'phone' => '0555 111 22 33',

                'password' => 'VerySecurePassword123!',
                'password_confirmation'
                => 'VerySecurePassword123!',
            ]
        );

        $customer = Customer::first();

        $this->assertNotNull($customer);

        $this->assertSame(
            'erdem@example.com',
            $customer->email
        );

        $this->assertSame(
            '+905551112233',
            $customer->phone
        );

        $this->assertTrue(
            Hash::check(
                'VerySecurePassword123!',
                $customer->password
            )
        );

        $this->assertNotSame(
            'VerySecurePassword123!',
            $customer->password
        );

        $this->assertAuthenticatedAs(
            $customer,
            'customer'
        );

        $response->assertRedirect(
            route('customer.phone.verify')
        );
    }

    public function test_registration_sends_phone_verification_otp(): void
    {
        $this->post(
            route('customer.register.store'),
            [
                'first_name' => 'Erdem',
                'last_name' => 'Lale',

                'email' => 'erdem@example.com',
                'phone' => '05551112233',

                'password' => 'VerySecurePassword123!',
                'password_confirmation'
                => 'VerySecurePassword123!',
            ]
        );

        $this->assertCount(
            1,
            $this->sms->sent
        );

        $this->assertSame(
            '+905551112233',
            $this->sms->sent[0]['phone']
        );

        $this->assertMatchesRegularExpression(
            '/^\d{6}$/',
            $this->sms->sent[0]['code']
        );
    }

    public function test_otp_is_not_stored_as_plain_text(): void
    {
        $this->post(
            route('customer.register.store'),
            [
                'first_name' => 'Erdem',
                'last_name' => 'Lale',
                'email' => 'erdem@example.com',
                'phone' => '05551112233',

                'password' => 'VerySecurePassword123!',
                'password_confirmation'
                => 'VerySecurePassword123!',
            ]
        );

        $code = $this->sms->lastCode();

        $verification = OtpVerification::first();

        $this->assertNotNull($verification);

        $this->assertNotSame(
            $code,
            $verification->code_hash
        );

        $this->assertTrue(
            Hash::check(
                $code,
                $verification->code_hash
            )
        );
    }
}
