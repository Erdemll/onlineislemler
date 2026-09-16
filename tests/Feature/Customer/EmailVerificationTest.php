<?php

namespace Tests\Feature\Customer;

use App\Mail\VerificationCodeMail;
use App\Models\Customer;
use App\Models\OtpVerification;
use App\Services\Verification\VerificationCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_unverified_customer_can_view_email_verification_form(): void
    {
        $customer = Customer::factory()->create([
            'email_verified_at' => null,
        ]);

        $response = $this
            ->actingAsCustomer($customer)
            ->get(route('customer.email.verify'));

        $response
            ->assertOk()
            ->assertSee('E-posta adresinizi doğrulayın')
            ->assertSee(route('customer.email.verify.store'), false)
            ->assertSee(route('customer.email.resend'), false);
    }

    public function test_customer_can_verify_email_with_valid_code(): void
    {
        $customer = Customer::factory()->create([
            'email_verified_at' => null,
        ]);

        app(VerificationCodeService::class)
            ->send(
                customer: $customer,
                purpose: 'email_verification',
                destination: $customer->email,
            );

        $code = null;

        Mail::assertQueued(
            VerificationCodeMail::class,
            function (VerificationCodeMail $mail) use (&$code) {
                $code = $mail->code;

                return true;
            }
        );

        $response = $this
            ->actingAsCustomer($customer)
            ->post(
                route(
                    'customer.email.verify.store'
                ),
                [
                    'code' => $code,
                ]
            );

        $customer->refresh();

        $this->assertNotNull(
            $customer->email_verified_at
        );

        $response->assertRedirect(
            route('customer.dashboard')
        );
    }

    public function test_wrong_email_verification_code_is_rejected(): void
    {
        $customer = Customer::factory()->create([
            'email_verified_at' => null,
        ]);

        app(VerificationCodeService::class)
            ->send(
                customer: $customer,
                purpose: 'email_verification',
                destination: $customer->email,
            );

        $response = $this
            ->actingAsCustomer($customer)
            ->post(
                route(
                    'customer.email.verify.store'
                ),
                [
                    'code' => '000000',
                ]
            );

        $customer->refresh();

        $this->assertNull(
            $customer->email_verified_at
        );

        $response->assertSessionHasErrors(
            'code'
        );
    }

    public function test_expired_email_verification_code_cannot_be_used(): void
    {
        $customer = Customer::factory()->create([
            'email_verified_at' => null,
        ]);

        OtpVerification::create([
            'customer_id' => $customer->id,

            'purpose' => 'email_verification',

            'code_hash' => Hash::make('123456'),

            'attempts' => 0,

            'expires_at' => now()->subMinute(),
        ]);

        $response = $this
            ->actingAsCustomer($customer)
            ->post(
                route(
                    'customer.email.verify.store'
                ),
                [
                    'code' => '123456',
                ]
            );

        $customer->refresh();

        $this->assertNull(
            $customer->email_verified_at
        );

        $response->assertSessionHasErrors(
            'code'
        );
    }

    public function test_email_verification_code_is_blocked_after_five_failed_attempts(): void
    {
        $customer = Customer::factory()->create([
            'email_verified_at' => null,
        ]);

        OtpVerification::create([
            'customer_id' => $customer->id,

            'purpose' => 'email_verification',

            'code_hash' => Hash::make('123456'),

            'attempts' => 5,

            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this
            ->actingAsCustomer($customer)
            ->post(
                route(
                    'customer.email.verify.store'
                ),
                [
                    'code' => '123456',
                ]
            );

        $customer->refresh();

        $this->assertNull(
            $customer->email_verified_at
        );

        $response->assertSessionHasErrors(
            'code'
        );
    }

    public function test_email_verification_code_cannot_be_used_twice(): void
    {
        $customer = Customer::factory()->create([
            'email_verified_at' => null,
        ]);

        app(VerificationCodeService::class)
            ->send(
                customer: $customer,
                purpose: 'email_verification',
                destination: $customer->email,
            );

        $code = null;

        Mail::assertQueued(
            VerificationCodeMail::class,
            function (VerificationCodeMail $mail) use (&$code) {
                $code = $mail->code;

                return true;
            }
        );

        $this
            ->actingAsCustomer($customer)
            ->post(
                route(
                    'customer.email.verify.store'
                ),
                [
                    'code' => $code,
                ]
            )
            ->assertRedirect(
                route('customer.dashboard')
            );

        /*
         * Yalnızca testi mümkün kılmak için
         * e-posta durumunu geri alıyoruz.
         */
        $customer->forceFill([
            'email_verified_at' => null,
        ])->save();

        $response = $this
            ->actingAsCustomer(
                $customer->fresh()
            )
            ->post(
                route(
                    'customer.email.verify.store'
                ),
                [
                    'code' => $code,
                ]
            );

        $response->assertSessionHasErrors(
            'code'
        );
    }

    public function test_requesting_new_email_verification_code_invalidates_old_code(): void
    {
        $customer = Customer::factory()->create([
            'email_verified_at' => null,
        ]);

        $service = app(
            VerificationCodeService::class
        );

        $service->send(
            customer: $customer,
            purpose: 'email_verification',
            destination: $customer->email,
        );

        $first = OtpVerification::firstOrFail();

        $oldHash = $first->code_hash;

        $service->send(
            customer: $customer,
            purpose: 'email_verification',
            destination: $customer->email,
        );

        $this->assertDatabaseCount(
            'otp_verifications',
            1
        );

        $current = OtpVerification::firstOrFail();

        $this->assertNotSame(
            $oldHash,
            $current->code_hash
        );
    }

    public function test_customer_can_request_new_email_verification_code(): void
    {
        $customer = Customer::factory()->create([
            'email_verified_at' => null,
        ]);

        $response = $this
            ->actingAsCustomer($customer)
            ->post(
                route(
                    'customer.email.resend'
                )
            );

        $response->assertSessionHas(
            'status'
        );

        Mail::assertQueued(
            VerificationCodeMail::class,
            1
        );

        Mail::assertQueued(
            VerificationCodeMail::class,
            function (VerificationCodeMail $mail) use ($customer) {
                return $mail->hasTo(
                    $customer->email
                )
                    && $mail->purpose
                        === 'email_verification';
            }
        );
    }

    public function test_verified_customer_does_not_receive_another_verification_mail(): void
    {
        $customer = Customer::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this
            ->actingAsCustomer($customer)
            ->post(
                route(
                    'customer.email.resend'
                )
            );

        $response->assertRedirect(
            route('customer.dashboard')
        );

        Mail::assertNothingQueued();
    }
}
