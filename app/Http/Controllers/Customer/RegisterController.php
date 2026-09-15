<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\RegisterCustomerRequest;
use App\Services\Auth\PhoneVerificationService;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    public function store(
        RegisterCustomerRequest $request,
        PhoneVerificationService $verificationService
    ): RedirectResponse {

        $data = $request->validated();

        $customer = DB::transaction(function () use ($data) {
            return Customer::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'],

                'password' => Hash::make(
                    $data['password']
                ),
            ]);
        });

        Auth::guard('customer')->login($customer);

        $request->session()->regenerate();

        $request->session()->put(
            'customer_session_version',
            $customer->session_version
        );

        $verificationService->send($customer);

        return redirect()
            ->route('customer.phone.verify');
    }
}
