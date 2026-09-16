<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
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

        if ($customer->email_verified_at) {
            return redirect()
                ->route(
                    'customer.dashboard'
                );
        }

        return view(
            'customer.auth.verify-email'
        );
    }

    public function verify(
        Request $request,
        VerificationCodeService $verificationService
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
                ->route(
                    'customer.dashboard'
                );
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
                ->route(
                    'customer.dashboard'
                );
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
