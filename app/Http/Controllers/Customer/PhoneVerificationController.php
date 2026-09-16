<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\Auth\PhoneVerificationService;
use App\Services\Verification\VerificationCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PhoneVerificationController extends Controller
{
    public function show(): View|RedirectResponse
    {
        $customer = auth('customer')->user();

        if ($customer->phone_verified_at) {
            return redirect()
                ->route('customer.dashboard');
        }

        return view('customer.phone-verify');
    }

    public function verify(
        Request $request,
        VerificationCodeService $verificationService,
    ): RedirectResponse {

        $request->validate([
            'code' => [
                'required',
                'digits:6',
            ],
        ]);

        $customer = auth('customer')->user();

        if ($customer->phone_verified_at) {
            return redirect()
                ->route('customer.dashboard');
        }

        if (! $verificationService->verify(
            customer: $customer,
            purpose: 'phone_verification',
            code: $request->string('code')->toString(),
        )) {
            return back()->withErrors([
                'code' => 'Doğrulama kodu geçersiz veya süresi dolmuş.',
            ]);
        }

        $customer->forceFill([
            'phone_verified_at' => now(),
        ])->save();

        return redirect()
            ->route('customer.dashboard');
    }

    public function resend(
        PhoneVerificationService $verificationService
    ): RedirectResponse {

        $customer = auth('customer')->user();

        if ($customer->phone_verified_at) {
            return redirect()
                ->route('customer.dashboard');
        }

        $verificationService->send(
            $customer
        );

        return back()->with(
            'status',
            'Yeni doğrulama kodu gönderildi.'
        );
    }
}
