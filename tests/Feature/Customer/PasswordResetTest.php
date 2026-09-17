<?php

namespace Tests\Feature\Customer;

use App\Contracts\VerificationCodeSender;
use App\Exceptions\MailDeliveryException;
use App\Mail\VerificationCodeMail;
use App\Models\Customer;
use App\Models\OtpVerification;
use App\Services\Auth\PasswordResetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_password_reset_request_form_uses_email(): void
    {
        $response = $this->get(route('customer.password.request'));

        $response
            ->assertOk()
            ->assertSee('name="email"', false)
            ->assertDontSee('name="phone"', false);
    }

    public function test_password_reset_code_form_is_available_after_request(): void
    {
        $response = $this
            ->withSession([
                'password_reset_email' => 'erdem@example.com',
            ])
            ->get(route('customer.password.otp.form'));

        $response
            ->assertOk()
            ->assertSee('name="code"', false);
    }

    public function test_existing_customer_can_request_password_reset_code(): void
    {
        $customer = Customer::factory()->create([
            'email' => 'erdem@example.com',
        ]);

        $response = $this->post(
            route('customer.password.send'),
            [
                'email' => 'ERDEM@example.com',
            ]
        );

        $response->assertRedirect(
            route('customer.password.otp.form')
        );

        $response->assertSessionHas(
            'status',
            'Bilgileriniz sistemde kayıtlıysa doğrulama kodu e-posta adresinize gönderilmiştir.'
        );

        Mail::assertSent(
            VerificationCodeMail::class,
            fn (VerificationCodeMail $mail): bool => $mail->hasTo($customer->email)
                && $mail->purpose === 'password_reset',
        );

        $this->assertDatabaseHas(
            'otp_verifications',
            [
                'customer_id' => $customer->id,
                'purpose' => 'password_reset',
            ]
        );
    }

    public function test_non_existing_email_gets_same_response_without_mail(): void
    {
        $response = $this->post(
            route('customer.password.send'),
            [
                'email' => 'bulunamadi@example.com',
            ]
        );

        $response->assertRedirect(
            route('customer.password.otp.form')
        );

        $response->assertSessionHas(
            'status',
            'Bilgileriniz sistemde kayıtlıysa doğrulama kodu e-posta adresinize gönderilmiştir.'
        );

        Mail::assertNothingSent();

        $this->assertDatabaseCount(
            'otp_verifications',
            0
        );
    }

    public function test_inactive_customer_does_not_receive_reset_mail(): void
    {
        Customer::factory()
            ->inactive()
            ->create([
                'email' => 'erdem@example.com',
            ]);

        $this->post(
            route('customer.password.send'),
            [
                'email' => 'erdem@example.com',
            ]
        );

        Mail::assertNothingSent();

        $this->assertDatabaseCount(
            'otp_verifications',
            0
        );
    }

    public function test_mail_failure_does_not_reveal_that_password_reset_account_exists(): void
    {
        Customer::factory()->create([
            'email' => 'erdem@example.com',
        ]);
        $this->app->instance(VerificationCodeSender::class, new class implements VerificationCodeSender
        {
            public function send(string $destination, string $code, string $purpose): void
            {
                throw new MailDeliveryException;
            }
        });

        $response = $this->post(
            route('customer.password.send'),
            ['email' => 'erdem@example.com']
        );

        $response
            ->assertRedirect(route('customer.password.otp.form'))
            ->assertSessionHas(
                'status',
                'Bilgileriniz sistemde kayıtlıysa doğrulama kodu e-posta adresinize gönderilmiştir.'
            );
        $this->assertDatabaseCount('otp_verifications', 0);
    }

    public function test_password_reset_code_is_not_stored_as_plain_text(): void
    {
        $customer = Customer::factory()->create([
            'email' => 'erdem@example.com',
        ]);

        app(PasswordResetService::class)->sendOtp($customer);

        $code = $this->getLastSentCode();

        $verification = OtpVerification::where(
            'customer_id',
            $customer->id
        )
            ->where(
                'purpose',
                'password_reset'
            )
            ->firstOrFail();

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

    public function test_customer_can_verify_valid_password_reset_code(): void
    {
        $customer = Customer::factory()->create([
            'email' => 'erdem@example.com',
        ]);

        app(PasswordResetService::class)->sendOtp($customer);

        $code = $this->getLastSentCode();

        $response = $this
            ->withSession([
                'password_reset_email' => $customer->email,
            ])
            ->post(
                route('customer.password.otp.verify'),
                [
                    'code' => $code,
                ]
            );

        $response->assertRedirect(
            route('customer.password.reset')
        );

        $response->assertSessionHas(
            'password_reset_customer_id',
            $customer->id
        );

        $response->assertSessionHas(
            'password_reset_authorized_at'
        );

        $verification = OtpVerification::where(
            'customer_id',
            $customer->id
        )
            ->where(
                'purpose',
                'password_reset'
            )
            ->firstOrFail();

        $this->assertNotNull(
            $verification->verified_at
        );
    }

    public function test_wrong_password_reset_code_is_rejected(): void
    {
        $customer = Customer::factory()->create([
            'email' => 'erdem@example.com',
        ]);

        app(PasswordResetService::class)->sendOtp($customer);

        $response = $this
            ->withSession([
                'password_reset_email' => $customer->email,
            ])
            ->post(
                route('customer.password.otp.verify'),
                [
                    'code' => '000000',
                ]
            );

        $response->assertSessionHasErrors(
            'code'
        );

        $verification = OtpVerification::first();

        $this->assertSame(
            1,
            $verification->attempts
        );
    }

    public function test_expired_password_reset_code_is_rejected(): void
    {
        $customer = Customer::factory()->create([
            'email' => 'erdem@example.com',
        ]);

        OtpVerification::create([
            'customer_id' => $customer->id,
            'purpose' => 'password_reset',
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this
            ->withSession([
                'password_reset_email' => $customer->email,
            ])
            ->post(
                route('customer.password.otp.verify'),
                [
                    'code' => '123456',
                ]
            );

        $response->assertSessionHasErrors(
            'code'
        );

        $response->assertSessionMissing(
            'password_reset_customer_id'
        );
    }

    public function test_password_reset_code_is_blocked_after_five_failed_attempts(): void
    {
        $customer = Customer::factory()->create([
            'email' => 'erdem@example.com',
        ]);

        OtpVerification::create([
            'customer_id' => $customer->id,
            'purpose' => 'password_reset',
            'code_hash' => Hash::make('123456'),
            'attempts' => 5,
            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this
            ->withSession([
                'password_reset_email' => $customer->email,
            ])
            ->post(
                route('customer.password.otp.verify'),
                [
                    'code' => '123456',
                ]
            );

        $response->assertSessionHasErrors(
            'code'
        );

        $response->assertSessionMissing(
            'password_reset_customer_id'
        );
    }

    public function test_password_reset_code_cannot_be_used_twice(): void
    {
        $customer = Customer::factory()->create([
            'email' => 'erdem@example.com',
        ]);

        app(PasswordResetService::class)->sendOtp($customer);

        $code = $this->getLastSentCode();

        $this
            ->withSession([
                'password_reset_email' => $customer->email,
            ])
            ->post(
                route('customer.password.otp.verify'),
                [
                    'code' => $code,
                ]
            )
            ->assertRedirect(
                route('customer.password.reset')
            );

        $response = $this
            ->withSession([
                'password_reset_email' => $customer->email,
            ])
            ->post(
                route('customer.password.otp.verify'),
                [
                    'code' => $code,
                ]
            );

        $response->assertSessionHasErrors(
            'code'
        );
    }

    public function test_new_password_reset_code_invalidates_old_code(): void
    {
        $customer = Customer::factory()->create([
            'email' => 'erdem@example.com',
        ]);

        $service = app(PasswordResetService::class);

        $service->sendOtp($customer);

        $firstVerification = OtpVerification::first();

        $firstHash = $firstVerification->code_hash;

        $service->sendOtp($customer);

        $this->assertDatabaseCount(
            'otp_verifications',
            1
        );

        $currentVerification = OtpVerification::first();

        $this->assertNotSame(
            $firstHash,
            $currentVerification->code_hash
        );
    }

    public function test_reset_form_requires_valid_reset_authorization(): void
    {
        $response = $this->get(
            route('customer.password.reset')
        );

        $response->assertRedirect(
            route('customer.password.request')
        );
    }

    public function test_reset_authorization_expires_after_ten_minutes(): void
    {
        $customer = Customer::factory()->create();

        $response = $this
            ->withSession([
                'password_reset_customer_id' => $customer->id,

                'password_reset_authorized_at' => now()
                    ->subMinutes(11)
                    ->timestamp,
            ])
            ->get(
                route('customer.password.reset')
            );

        $response->assertRedirect(
            route('customer.password.request')
        );
    }

    public function test_customer_can_reset_password_after_valid_code_authorization(): void
    {
        $customer = Customer::factory()->create([
            'password' => Hash::make(
                'OldPassword123!'
            ),
        ]);

        $oldSessionVersion =
            $customer->session_version;

        $response = $this
            ->withSession([
                'password_reset_customer_id' => $customer->id,

                'password_reset_authorized_at' => now()->timestamp,
            ])
            ->post(
                route('customer.password.update'),
                [
                    'password' => 'NewVerySecurePassword123!',

                    'password_confirmation' => 'NewVerySecurePassword123!',
                ]
            );

        $customer->refresh();

        $response->assertRedirect(
            route('customer.login')
        );

        $this->assertTrue(
            Hash::check(
                'NewVerySecurePassword123!',
                $customer->password
            )
        );

        $this->assertFalse(
            Hash::check(
                'OldPassword123!',
                $customer->password
            )
        );

        $this->assertNotNull(
            $customer->password_changed_at
        );

        $this->assertSame(
            $oldSessionVersion + 1,
            $customer->session_version
        );

        $this->assertNull(
            $customer->remember_token
        );

        $this->assertDatabaseMissing(
            'otp_verifications',
            [
                'customer_id' => $customer->id,
                'purpose' => 'password_reset',
            ]
        );
    }

    public function test_old_password_no_longer_allows_login_after_reset(): void
    {
        $customer = Customer::factory()->create([
            'email' => 'erdem@example.com',

            'password' => Hash::make(
                'OldPassword123!'
            ),
        ]);

        $this
            ->withSession([
                'password_reset_customer_id' => $customer->id,

                'password_reset_authorized_at' => now()->timestamp,
            ])
            ->post(
                route('customer.password.update'),
                [
                    'password' => 'NewVerySecurePassword123!',

                    'password_confirmation' => 'NewVerySecurePassword123!',
                ]
            );

        $this->post(
            route('customer.login.store'),
            [
                'email' => 'erdem@example.com',
                'password' => 'OldPassword123!',
            ]
        );

        $this->assertGuest(
            'customer'
        );
    }

    public function test_new_password_allows_login_after_reset(): void
    {
        $customer = Customer::factory()
            ->ready()
            ->create([
                'email' => 'erdem@example.com',

                'password' => Hash::make(
                    'OldPassword123!'
                ),
            ]);

        $this
            ->withSession([
                'password_reset_customer_id' => $customer->id,

                'password_reset_authorized_at' => now()->timestamp,
            ])
            ->post(
                route('customer.password.update'),
                [
                    'password' => 'NewVerySecurePassword123!',

                    'password_confirmation' => 'NewVerySecurePassword123!',
                ]
            );

        $response = $this->post(
            route('customer.login.store'),
            [
                'email' => 'erdem@example.com',
                'password' => 'NewVerySecurePassword123!',
            ]
        );

        $this->assertAuthenticatedAs(
            $customer->fresh(),
            'customer'
        );

        $response->assertRedirect(
            route('customer.dashboard')
        );
    }

    private function getLastSentCode(): string
    {
        $code = null;

        Mail::assertSent(
            VerificationCodeMail::class,
            function (VerificationCodeMail $mail) use (&$code): bool {
                $code = $mail->code;

                return true;
            }
        );

        $this->assertNotNull($code);

        if ($code === null) {
            throw new \LogicException('Password reset verification code was not sent.');
        }

        return $code;
    }
}
