<?php

namespace App\Http\Controllers\Customer;

use App\Exceptions\CariPlusException;
use App\Http\Controllers\Controller;
use App\Services\CariPlus\ProvisionCurrentAccount;
use App\Services\Verification\VerificationCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    public function show(): View|RedirectResponse
    {
        $customer = auth(
            'customer'
        )->user();

        if ($customer->email_verified_at && $customer->cari_plus_current_account_id !== null) {
            return redirect()
                ->route(
                    'customer.dashboard'
                );
        }

        return view('customer.auth.verify-email', [
            'provisionPending' => $customer->email_verified_at !== null,
        ]);
    }

    public function verify(
        Request $request,
        VerificationCodeService $verificationService,
        ProvisionCurrentAccount $provision,
    ): RedirectResponse {
        $request->validate([
            'code' => [
                'required',
                'digits:6',
            ],
        ]);

        $customer = auth(
            'customer'
        )->user();

        if ($customer->email_verified_at) {
            return redirect()
                ->route($customer->cari_plus_current_account_id === null
                    ? 'customer.email.verify'
                    : 'customer.dashboard');
        }

        $valid = $verificationService->verify(
            customer: $customer,
            purpose: 'email_verification',
            code: $request->code,
        );

        if (! $valid) {
            return back()->withErrors([
                'code' => 'Doğrulama kodu geçersiz veya süresi dolmuş.',
            ]);
        }

        $customer->forceFill([
            'email_verified_at' => now(),
        ])->save();

        try {
            $provision->handle($customer);
        } catch (CariPlusException $exception) {
            report($exception);

            return redirect()->route('customer.email.verify')->withErrors([
                'provision' => 'E-posta adresiniz doğrulandı ancak müşteri hesabınız şu anda oluşturulamadı. Lütfen tekrar deneyin.',
            ]);
        }

        return redirect()
            ->route(
                'customer.dashboard'
            );
    }

    public function resend(
        VerificationCodeService $verificationService
    ): RedirectResponse {
        $customer = auth(
            'customer'
        )->user();

        if ($customer->email_verified_at) {
            return redirect()
                ->route($customer->cari_plus_current_account_id === null
                    ? 'customer.email.verify'
                    : 'customer.dashboard');
        }

        $verificationService->send(
            customer: $customer,
            purpose: 'email_verification',
            destination: $customer->email,
        );

        return back()->with(
            'status',
            'Yeni doğrulama kodu e-posta adresinize gönderildi.'
        );
    }
}
