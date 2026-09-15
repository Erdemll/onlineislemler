<?php

namespace Tests\Feature\Customer;

use App\Contracts\SmsSender;
use App\Models\Customer;
use App\Models\OtpVerification;
use App\Services\Auth\PasswordResetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Fakes\FakeSmsSender;
use Tests\TestCase;

class PasswordResetTest extends TestCase
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

    public function test_existing_customer_can_request_password_reset_otp(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '+905551112233',
        ]);

        $response = $this->post(
            route('customer.password.send'),
            [
                'phone' => '0555 111 22 33',
            ]
        );

        $response->assertRedirect(
            route('customer.password.otp.form')
        );

        $response->assertSessionHas(
            'status',
            'Bilgileriniz sistemde kayıtlıysa doğrulama kodu telefonunuza gönderilmiştir.'
        );

        $this->assertCount(
            1,
            $this->sms->sent
        );

        $this->assertSame(
            '+905551112233',
            $this->sms->sent[0]['phone']
        );

        $this->assertDatabaseHas(
            'otp_verifications',
            [
                'customer_id' => $customer->id,
                'purpose' => 'password_reset',
            ]
        );
    }


    public function test_non_existing_phone_gets_same_response_without_sms(): void
    {
        $response = $this->post(
            route('customer.password.send'),
            [
                'phone' => '0555 999 88 77',
            ]
        );

        $response->assertRedirect(
            route('customer.password.otp.form')
        );

        $response->assertSessionHas(
            'status',
            'Bilgileriniz sistemde kayıtlıysa doğrulama kodu telefonunuza gönderilmiştir.'
        );

        $this->assertCount(
            0,
            $this->sms->sent
        );

        $this->assertDatabaseCount(
            'otp_verifications',
            0
        );
    }


    public function test_inactive_customer_does_not_receive_reset_otp(): void
    {
        Customer::factory()
            ->inactive()
            ->create([
                'phone' => '+905551112233',
            ]);

        $this->post(
            route('customer.password.send'),
            [
                'phone' => '05551112233',
            ]
        );

        $this->assertCount(
            0,
            $this->sms->sent
        );

        $this->assertDatabaseCount(
            'otp_verifications',
            0
        );
    }


    public function test_password_reset_otp_is_not_stored_as_plain_text(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '+905551112233',
        ]);

        app(PasswordResetService::class)
            ->sendOtp($customer);

        $code = $this->sms->lastCode();

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


    public function test_customer_can_verify_valid_password_reset_otp(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '+905551112233',
        ]);

        app(PasswordResetService::class)
            ->sendOtp($customer);

        $code = $this->sms->lastCode();

        $response = $this
            ->withSession([
                'password_reset_phone'
                    => $customer->phone,
            ])
            ->post(
                route(
                    'customer.password.otp.verify'
                ),
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


    public function test_wrong_password_reset_otp_is_rejected(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '+905551112233',
        ]);

        app(PasswordResetService::class)
            ->sendOtp($customer);

        $response = $this
            ->withSession([
                'password_reset_phone'
                    => $customer->phone,
            ])
            ->post(
                route(
                    'customer.password.otp.verify'
                ),
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


    public function test_expired_password_reset_otp_is_rejected(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '+905551112233',
        ]);

        OtpVerification::create([
            'customer_id' => $customer->id,
            'purpose' => 'password_reset',
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()
                ->subMinute(),
        ]);

        $response = $this
            ->withSession([
                'password_reset_phone'
                    => $customer->phone,
            ])
            ->post(
                route(
                    'customer.password.otp.verify'
                ),
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


    public function test_password_reset_otp_is_blocked_after_five_failed_attempts(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '+905551112233',
        ]);

        OtpVerification::create([
            'customer_id' => $customer->id,
            'purpose' => 'password_reset',
            'code_hash' => Hash::make('123456'),
            'attempts' => 5,
            'expires_at' => now()
                ->addMinutes(5),
        ]);

        $response = $this
            ->withSession([
                'password_reset_phone'
                    => $customer->phone,
            ])
            ->post(
                route(
                    'customer.password.otp.verify'
                ),
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


    public function test_password_reset_otp_cannot_be_used_twice(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '+905551112233',
        ]);

        app(PasswordResetService::class)
            ->sendOtp($customer);

        $code = $this->sms->lastCode();

        $this
            ->withSession([
                'password_reset_phone'
                    => $customer->phone,
            ])
            ->post(
                route(
                    'customer.password.otp.verify'
                ),
                [
                    'code' => $code,
                ]
            )
            ->assertRedirect(
                route('customer.password.reset')
            );

        /*
         * İlk doğrulamada verified_at doldu.
         * Şimdi aynı OTP'yi yeni bir recovery session ile
         * yeniden kullanmayı deniyoruz.
         */
        $response = $this
            ->withSession([
                'password_reset_phone'
                    => $customer->phone,
            ])
            ->post(
                route(
                    'customer.password.otp.verify'
                ),
                [
                    'code' => $code,
                ]
            );

        $response->assertSessionHasErrors(
            'code'
        );
    }


    public function test_new_password_reset_otp_invalidates_old_otp(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '+905551112233',
        ]);

        $service = app(
            PasswordResetService::class
        );

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
                'password_reset_customer_id'
                    => $customer->id,

                'password_reset_authorized_at'
                    => now()
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


    public function test_customer_can_reset_password_after_valid_otp_authorization(): void
    {
        $customer = Customer::factory()->create([
            'password'
                => Hash::make(
                    'OldPassword123!'
                ),
        ]);

        $oldSessionVersion =
            $customer->session_version;

        $response = $this
            ->withSession([
                'password_reset_customer_id'
                    => $customer->id,

                'password_reset_authorized_at'
                    => now()->timestamp,
            ])
            ->post(
                route(
                    'customer.password.update'
                ),
                [
                    'password'
                        => 'NewVerySecurePassword123!',

                    'password_confirmation'
                        => 'NewVerySecurePassword123!',
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

            'password'
                => Hash::make(
                    'OldPassword123!'
                ),
        ]);

        $this
            ->withSession([
                'password_reset_customer_id'
                    => $customer->id,

                'password_reset_authorized_at'
                    => now()->timestamp,
            ])
            ->post(
                route(
                    'customer.password.update'
                ),
                [
                    'password'
                        => 'NewVerySecurePassword123!',

                    'password_confirmation'
                        => 'NewVerySecurePassword123!',
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
            ->phoneVerified()
            ->create([
                'email' => 'erdem@example.com',

                'password'
                    => Hash::make(
                        'OldPassword123!'
                    ),
            ]);

        $this
            ->withSession([
                'password_reset_customer_id'
                    => $customer->id,

                'password_reset_authorized_at'
                    => now()->timestamp,
            ])
            ->post(
                route(
                    'customer.password.update'
                ),
                [
                    'password'
                        => 'NewVerySecurePassword123!',

                    'password_confirmation'
                        => 'NewVerySecurePassword123!',
                ]
            );

        $response = $this->post(
            route('customer.login.store'),
            [
                'email' => 'erdem@example.com',
                'password'
                    => 'NewVerySecurePassword123!',
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
}