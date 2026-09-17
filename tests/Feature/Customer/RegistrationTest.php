<?php

namespace Tests\Feature\Customer;

use App\Contracts\VerificationCodeSender;
use App\Exceptions\MailDeliveryException;
use App\Mail\VerificationCodeMail;
use App\Models\Customer;
use App\Models\OtpVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
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
                'account_type' => 'individual',
                'national_id' => '10000000146',
                'province_code' => '33',
                'district' => 'Yenişehir',
                'address_line' => 'Test Mahallesi No: 1',

                'password' => 'VerySecurePassword123!',

                'password_confirmation' => 'VerySecurePassword123!',
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
            route('customer.email.verify')
        );
    }

    public function test_registration_sends_email_verification_code(): void
    {
        $this->post(
            route('customer.register.store'),
            [
                'first_name' => 'Erdem',
                'last_name' => 'Lale',
                'email' => 'erdem@example.com',
                'phone' => '05551112233',
                'account_type' => 'individual',
                'national_id' => '10000000146',
                'province_code' => '33',
                'district' => 'Yenişehir',
                'address_line' => 'Test Mahallesi No: 1',

                'password' => 'VerySecurePassword123!',

                'password_confirmation' => 'VerySecurePassword123!',
            ]
        );

        Mail::assertSent(
            VerificationCodeMail::class,
            function (VerificationCodeMail $mail): bool {
                return $mail->hasTo('erdem@example.com')
                    && $mail->purpose === 'email_verification'
                    && preg_match('/^\d{6}$/', $mail->code) === 1;
            }
        );
    }

    public function test_corporate_customer_can_register_with_billing_fields(): void
    {
        $response = $this->post(route('customer.register.store'), [
            'account_type' => 'corporate',
            'first_name' => 'Ayşe',
            'last_name' => 'Yılmaz',
            'company_title' => 'Örnek Teknoloji AŞ',
            'tax_number' => '1234567890',
            'tax_office' => 'Mersin Vergi Dairesi',
            'email' => 'kurumsal@example.com',
            'phone' => '0324 111 22 33',
            'mobile_phone' => '0555 111 22 33',
            'province_code' => '33',
            'district' => 'Akdeniz',
            'address_line' => 'Liman Mahallesi No: 10',
            'is_public_institution' => '1',
            'spending_unit_tax_number' => '9876543210',
            'spending_unit_title' => 'Bilgi İşlem Birimi',
            'password' => 'VerySecurePassword123!',
            'password_confirmation' => 'VerySecurePassword123!',
        ]);

        $response->assertRedirect(route('customer.email.verify'));
        $customer = Customer::where('email', 'kurumsal@example.com')->firstOrFail();

        expect($customer->account_type->value)->toBe('corporate')
            ->and($customer->tax_number)->toBe('1234567890')
            ->and($customer->province)->toBe('Mersin')
            ->and($customer->is_public_institution)->toBeTrue();

        $raw = DB::table('customers')->where('id', $customer->id)->first();
        expect($raw->tax_number)->not->toBe('1234567890')
            ->and($raw->spending_unit_tax_number)->not->toBe('9876543210')
            ->and($raw->tax_number_hash)->toBe(Customer::identityHash('1234567890'));
    }

    public function test_account_specific_identity_fields_are_required_and_unique(): void
    {
        Customer::factory()->create(['national_id' => '10000000146']);

        $this->post(route('customer.register.store'), [
            'account_type' => 'individual',
            'first_name' => 'Erdem',
            'last_name' => 'Lale',
            'national_id' => '10000000146',
            'email' => 'ikinci@example.com',
            'phone' => '0555 999 88 77',
            'province_code' => '33',
            'district' => 'Yenişehir',
            'address_line' => 'Test adresi',
            'password' => 'VerySecurePassword123!',
            'password_confirmation' => 'VerySecurePassword123!',
        ])->assertSessionHasErrors('national_id');
    }

    public function test_verification_code_is_not_stored_as_plain_text(): void
    {
        $this->post(
            route('customer.register.store'),
            [
                'first_name' => 'Erdem',
                'last_name' => 'Lale',
                'email' => 'erdem@example.com',
                'phone' => '05551112233',
                'account_type' => 'individual',
                'national_id' => '10000000146',
                'province_code' => '33',
                'district' => 'Yenişehir',
                'address_line' => 'Test Mahallesi No: 1',

                'password' => 'VerySecurePassword123!',

                'password_confirmation' => 'VerySecurePassword123!',
            ]
        );

        $verification = OtpVerification::first();

        $this->assertNotNull(
            $verification
        );

        $code = null;

        Mail::assertSent(
            VerificationCodeMail::class,
            function (VerificationCodeMail $mail) use (&$code): bool {
                $code = $mail->code;

                return true;
            }
        );

        $this->assertNotNull($code);
        $this->assertNotSame($code, $verification->code_hash);
        $this->assertTrue(
            Hash::check($code, $verification->code_hash)
        );
    }

    public function test_mail_provider_failure_returns_to_registration_without_creating_customer(): void
    {
        $this->app->instance(VerificationCodeSender::class, new class implements VerificationCodeSender
        {
            public function send(string $destination, string $code, string $purpose): void
            {
                throw new MailDeliveryException;
            }
        });

        $response = $this
            ->from(route('customer.register'))
            ->post(route('customer.register.store'), [
                'first_name' => 'Erdem',
                'last_name' => 'Lale',
                'email' => 'erdem@example.com',
                'phone' => '05551112233',
                'account_type' => 'individual',
                'national_id' => '10000000146',
                'province_code' => '33',
                'district' => 'Yenişehir',
                'address_line' => 'Test Mahallesi No: 1',
                'password' => 'VerySecurePassword123!',
                'password_confirmation' => 'VerySecurePassword123!',
            ]);

        $response
            ->assertRedirect(route('customer.register'))
            ->assertSessionHasErrors([
                'email_delivery' => 'Doğrulama e-postası şu anda gönderilemiyor. Lütfen kısa süre sonra tekrar deneyin.',
            ])
            ->assertSessionHasInput('email', 'erdem@example.com')
            ->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('_old_input.password_confirmation');

        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('otp_verifications', 0);
        $this->assertGuest('customer');
    }
}
