<?php

namespace App\Http\Controllers\Customer;

use App\Exceptions\MailDeliveryException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\ForgotPasswordRequest;
use App\Models\Customer;
use App\Models\OtpVerification;
use App\Services\Verification\VerificationCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function showRequestForm(): View
    {
        return view(
            'customer.auth.forgot-password'
        );
    }

    public function sendOtp(
        ForgotPasswordRequest $request,
        VerificationCodeService $verificationService,
    ): RedirectResponse {
        $email = $request->validated('email');

        $customer = Customer::where(
            'email',
            $email
        )
            ->where('is_active', true)
            ->first();

        /*
         * E-posta adresini session'a koyuyoruz.
         * Kullanıcı sonraki OTP ekranında tekrar
         * customer_id gönderemeyecek.
         */
        $request->session()->put(
            'password_reset_email',
            $email
        );

        if ($customer) {
            try {
                $verificationService->send(
                    customer: $customer,
                    purpose: 'password_reset',
                    destination: $customer->email,
                );
            } catch (MailDeliveryException $exception) {
                report($exception);
            }
        }

        /*
         * Customer olsa da olmasa da aynı cevap.
         */
        return redirect()
            ->route(
                'customer.password.otp.form'
            )
            ->with(
                'status',
                'Bilgileriniz sistemde kayıtlıysa doğrulama kodu e-posta adresinize gönderilmiştir.'
            );
    }

    public function showOtpForm(
        Request $request
    ): View|RedirectResponse {
        if (
            ! $request->session()
                ->has('password_reset_email')
        ) {
            return redirect()
                ->route(
                    'customer.password.request'
                );
        }

        return view('customer.password.otp.password-reset-otp');
    }

    public function verifyOtp(
        Request $request,
        VerificationCodeService $verificationService
    ): RedirectResponse {
        $request->validate([
            'code' => [
                'required',
                'digits:6',
            ],
        ]);

        $email = $request->session()->get(
            'password_reset_email'
        );

        if (! $email) {
            return redirect()
                ->route(
                    'customer.password.request'
                );
        }

        $customer = Customer::where(
            'email',
            $email
        )
            ->where('is_active', true)
            ->first();

        /*
         * Yine hesap var/yok bilgisini
         * açık etmiyoruz.
         */
        if (! $customer) {
            return back()->withErrors([
                'code' => 'Doğrulama kodu geçersiz veya süresi dolmuş.',
            ]);
        }

        if (! $verificationService->verify(
            customer: $customer,
            purpose: 'password_reset',
            code: $request->string('code')->toString(),
        )) {
            return back()->withErrors([
                'code' => 'Doğrulama kodu geçersiz veya süresi dolmuş.',
            ]);
        }

        /*
         * OTP doğrulaması kimlik seviyesini yükselttiği
         * için session ID'yi yeniliyoruz.
         */
        $request->session()->regenerate();

        $request->session()->put([
            'password_reset_customer_id' => $customer->id,

            'password_reset_authorized_at' => now()->timestamp,
        ]);

        /*
         * Kurtarma e-posta adresi artık gerekli değil.
         */
        $request->session()->forget(
            'password_reset_email'
        );

        return redirect()
            ->route(
                'customer.password.reset'
            );
    }

    public function showResetForm(
        Request $request
    ): View|RedirectResponse {
        if (
            ! $this->hasValidResetAuthorization(
                $request
            )
        ) {
            return redirect()
                ->route(
                    'customer.password.request'
                );
        }

        return view(
            'customer.auth.reset-password'
        );
    }

    public function reset(
        Request $request
    ): RedirectResponse {
        if (
            ! $this->hasValidResetAuthorization(
                $request
            )
        ) {
            return redirect()
                ->route(
                    'customer.password.request'
                );
        }

        $request->validate([
            'password' => [
                'required',
                'confirmed',
                Password::defaults(),
            ],
        ]);

        $customerId = $request->session()->get(
            'password_reset_customer_id'
        );

        $customer = Customer::whereKey(
            $customerId
        )
            ->where('is_active', true)
            ->firstOrFail();

        $customer->forceFill([
            'password' => Hash::make(
                $request->password
            ),

            'password_changed_at' => now(),

            /*
             * remember-me tokenları geçersiz.
             */
            'remember_token' => null,

            /*
             * Birazdan açıklayacağımız
             * session version.
             */
            'session_version' => $customer->session_version + 1,
        ])->save();

        /*
         * Password reset OTP'lerinin tamamını
         * artık kullanılamaz hale getir.
         */
        OtpVerification::where(
            'customer_id',
            $customer->id
        )
            ->where(
                'purpose',
                'password_reset'
            )
            ->delete();

        /*
         * Recovery session'ını tamamen kapatıyoruz.
         */
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route(
                'customer.login'
            )
            ->with(
                'status',
                'Şifreniz başarıyla değiştirildi. Yeni şifrenizle giriş yapabilirsiniz.'
            );
    }

    private function hasValidResetAuthorization(
        Request $request
    ): bool {
        $customerId = $request->session()->get(
            'password_reset_customer_id'
        );

        $authorizedAt = $request->session()->get(
            'password_reset_authorized_at'
        );

        if (
            ! $customerId
            || ! $authorizedAt
        ) {
            return false;
        }

        /*
         * OTP'yi doğruladıktan sonra kullanıcıya
         * yeni parola seçmesi için 10 dakika.
         */
        return now()->timestamp - $authorizedAt
            <= 600;
    }
}
