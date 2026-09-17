<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\RegisterCustomerRequest;
use App\Models\Customer;
use App\Services\Verification\VerificationCodeService;
use App\Support\TurkeyProvinces;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('customer.auth.register', [
            'provinces' => TurkeyProvinces::all(),
        ]);
    }

    public function store(
        RegisterCustomerRequest $request,
        VerificationCodeService $verificationService,
    ): RedirectResponse {

        $data = $request->validated();

        $customer = DB::transaction(function () use ($data, $verificationService) {
            $customer = Customer::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'mobile_phone' => $data['mobile_phone'] ?? null,
                'account_type' => $data['account_type'],
                'national_id' => $data['national_id'] ?? null,
                'tax_number' => $data['tax_number'] ?? null,
                'tax_office' => $data['tax_office'] ?? null,
                'company_title' => $data['company_title'] ?? null,
                'province_code' => $data['province_code'],
                'province' => TurkeyProvinces::name($data['province_code']),
                'district' => $data['district'],
                'address_line' => $data['address_line'],
                'is_public_institution' => $data['is_public_institution'] ?? false,
                'spending_unit_tax_number' => $data['spending_unit_tax_number'] ?? null,
                'spending_unit_title' => $data['spending_unit_title'] ?? null,
                'password' => Hash::make($data['password']),
            ]);

            $verificationService->send(
                customer: $customer,
                purpose: 'email_verification',
                destination: $customer->email,
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
            ->route('customer.email.verify');
    }
}
