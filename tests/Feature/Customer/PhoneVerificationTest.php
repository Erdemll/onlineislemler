<?php

namespace Tests\Feature\Customer;

use App\Contracts\SmsSender;
use App\Models\Customer;
use App\Services\Auth\PhoneVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeSmsSender;
use Tests\TestCase;
use App\Models\OtpVerification;
use Illuminate\Support\Facades\Hash;

class PhoneVerificationTest extends TestCase
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

    public function test_customer_can_verify_phone_with_valid_otp(): void
    {
        $customer = Customer::factory()->create();

        $service = app(
            PhoneVerificationService::class
        );

        $service->send($customer);

        $code = $this->sms->lastCode();

        $response = $this
            ->actingAsCustomer($customer)
            ->post(
                route(
                    'customer.phone.verify.store'
                ),
                [
                    'code' => $code,
                ]
            );

        $customer->refresh();

        $this->assertNotNull(
            $customer->phone_verified_at
        );

        $response->assertRedirect(
            route('customer.dashboard')
        );
    }


    public function test_wrong_otp_does_not_verify_phone(): void
    {
        $customer = Customer::factory()->create();

        app(PhoneVerificationService::class)
            ->send($customer);

        $response = $this
            ->actingAsCustomer($customer)
            ->post(
                route(
                    'customer.phone.verify.store'
                ),
                [
                    'code' => '000000',
                ]
            );

        $customer->refresh();

        $this->assertNull(
            $customer->phone_verified_at
        );

        $response->assertSessionHasErrors(
            'code'
        );
    }

    public function test_expired_otp_cannot_be_used(): void
    {
        $customer = Customer::factory()->create();

        OtpVerification::create([
            'customer_id' => $customer->id,

            'purpose' => 'phone_verification',

            'code_hash' => Hash::make('123456'),

            'attempts' => 0,

            'expires_at' => now()
                ->subMinute(),
        ]);

        $response = $this
            ->actingAsCustomer($customer)
            ->post(
                route(
                    'customer.phone.verify.store'
                ),
                [
                    'code' => '123456',
                ]
            );

        $customer->refresh();

        $this->assertNull(
            $customer->phone_verified_at
        );

        $response->assertSessionHasErrors(
            'code'
        );
    }

    public function test_otp_is_blocked_after_five_failed_attempts(): void
    {
        $customer = Customer::factory()->create();

        $verification = OtpVerification::create([
            'customer_id' => $customer->id,

            'purpose' => 'phone_verification',

            'code_hash' => Hash::make('123456'),

            'attempts' => 5,

            'expires_at' => now()
                ->addMinutes(5),
        ]);

        $response = $this
            ->actingAsCustomer($customer)
            ->post(
                route(
                    'customer.phone.verify.store'
                ),
                [
                    'code' => '123456',
                ]
            );

        $customer->refresh();

        $this->assertNull(
            $customer->phone_verified_at
        );

        $response->assertSessionHasErrors(
            'code'
        );
    }

    public function test_otp_cannot_be_used_twice(): void
    {
        $customer = Customer::factory()->create();

        app(PhoneVerificationService::class)
            ->send($customer);

        $code = $this->sms->lastCode();

        // İlk kullanım
        $this->actingAsCustomer($customer)
            ->post(
                route('customer.phone.verify.store'),
                [
                    'code' => $code,
                ]
            )
            ->assertRedirect(
                route('customer.dashboard')
            );

        // Test amacıyla telefonu tekrar doğrulanmamış hale getiriyoruz.
        // forceFill gerekli çünkü phone_verified_at fillable değil.
        $customer->forceFill([
            'phone_verified_at' => null,
        ])->save();

        // Aynı OTP ikinci kez kullanılmaya çalışılıyor.
        $response = $this
            ->actingAsCustomer($customer->fresh())
            ->post(
                route('customer.phone.verify.store'),
                [
                    'code' => $code,
                ]
            );

        $response->assertSessionHasErrors('code');

        $this->assertNull(
            $customer->fresh()->phone_verified_at
        );
    }

    public function test_requesting_new_otp_invalidates_old_otp(): void
    {
        $customer = Customer::factory()->create();

        $service = app(
            PhoneVerificationService::class
        );

        $service->send($customer);

        $oldCode = $this->sms->lastCode();

        $service->send($customer);

        $newCode = $this->sms->lastCode();

        $this->assertNotSame(
            $oldCode,
            $newCode
        );

        $response = $this
            ->actingAsCustomer($customer)
            ->post(
                route(
                    'customer.phone.verify.store'
                ),
                [
                    'code' => $oldCode,
                ]
            );

        $response->assertSessionHasErrors(
            'code'
        );
    }

    public function test_customer_can_request_new_otp(): void
    {
        $customer = Customer::factory()->create();

        app(PhoneVerificationService::class)
            ->send($customer);

        $this->assertCount(
            1,
            $this->sms->sent
        );

        $this
            ->actingAsCustomer($customer)
            ->post(
                route(
                    'customer.phone.resend'
                )
            )
            ->assertSessionHas(
                'status'
            );

        $this->assertCount(
            2,
            $this->sms->sent
        );

        $this->assertDatabaseCount(
            'otp_verifications',
            1
        );
    }
}
