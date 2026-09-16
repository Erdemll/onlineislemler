<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\RegisterCustomerRequest;
use App\Models\Customer;
use App\Services\Verification\SmsVerificationCodeSender;
use App\Services\Verification\VerificationCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    public function store(
        RegisterCustomerRequest $request,
        VerificationCodeService $verificationService,
        SmsVerificationCodeSender $smsSender
    ): RedirectResponse {

        $data = $request->validated();

        $customer = DB::transaction(function () use ($data, $verificationService, $smsSender) {
            $customer = Customer::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],

                'password' => Hash::make(
                    $data['password']
                ),
            ]);

            $verificationService->sendVia(
                sender: $smsSender,
                customer: $customer,
                purpose: 'phone_verification',
                destination: $customer->phone,
            );

            return $customer;
        });

        Auth::guard('customer')->login($customer);

        $request->session()->regenerate();

        $request->session()->put(
            'customer_session_version',
            $customer->session_version
        );

        return redirect()
            ->route('customer.phone.verify');
    }
}
