<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\OtpVerification;
use App\Services\Auth\PhoneVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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

        return view(
            'customer.auth.verify-phone'
        );
    }

    public function verify(
        Request $request
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

        $verification = OtpVerification::where(
            'customer_id',
            $customer->id
        )
            ->where(
                'purpose',
                'phone_verification'
            )
            ->whereNull('verified_at')
            ->latest()
            ->first();

        if (! $verification) {
            return back()->withErrors([
                'code' =>
                'Aktif bir doğrulama kodu bulunamadı.',
            ]);
        }

        if ($verification->expires_at->isPast()) {
            return back()->withErrors([
                'code' =>
                'Doğrulama kodunun süresi dolmuş.',
            ]);
        }

        if ($verification->attempts >= 5) {
            return back()->withErrors([
                'code' =>
                'Çok fazla hatalı deneme yapıldı. Yeni kod isteyin.',
            ]);
        }

        if (! Hash::check(
            $request->code,
            $verification->code_hash
        )) {
            $verification->increment('attempts');

            return back()->withErrors([
                'code' =>
                'Doğrulama kodu hatalı.',
            ]);
        }

        $verification->update([
            'verified_at' => now(),
        ]);

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
