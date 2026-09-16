<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\RequestPhoneChangeRequest;
use App\Services\Verification\SmsVerificationCodeSender;
use App\Services\Verification\VerificationCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PhoneChangeController extends Controller
{
    public function request(
        RequestPhoneChangeRequest $request,
        VerificationCodeService $verificationService,
        SmsVerificationCodeSender $smsSender
    ): RedirectResponse {
        $customer = auth('customer')->user();

        $phone = $request->validated('phone');

        $request->session()->put(
            'pending_phone_change',
            $phone
        );

        $verificationService->sendVia(
            sender: $smsSender,
            customer: $customer,
            purpose: 'phone_change',
            destination: $phone,
        );

        return redirect()
            ->route(
                'customer.phone.change.verify.form'
            );
    }

    public function showVerifyForm(
        Request $request
    ): View|RedirectResponse {
        if (
            ! $request->session()
                ->has('pending_phone_change')
        ) {
            return redirect()
                ->route('customer.profile');
        }

        return view(
            'customer.profile.verify-phone-change'
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

        $customer = auth('customer')->user();

        $newPhone = $request->session()->get(
            'pending_phone_change'
        );

        if (! $newPhone) {
            return redirect()
                ->route('customer.profile');
        }

        if (! $verificationService->verify(
            customer: $customer,
            purpose: 'phone_change',
            code: $request->string('code')->toString(),
        )) {
            return back()->withErrors([
                'code' => 'Kod geçersiz veya süresi dolmuş.',
            ]);
        }

        $customer->forceFill([
            'phone' => $newPhone,
            'phone_verified_at' => now(),
            'session_version' => $customer->session_version + 1,
        ])->save();

        $request->session()->put(
            'customer_session_version',
            $customer->session_version
        );

        $request->session()->forget(
            'pending_phone_change'
        );

        return redirect()
            ->route('customer.profile')
            ->with(
                'status',
                'Telefon numaranız başarıyla değiştirildi.'
            );
    }
}
