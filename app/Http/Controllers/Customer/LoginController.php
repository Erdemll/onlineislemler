<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\LoginCustomerRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function store(
        LoginCustomerRequest $request
    ): RedirectResponse {
        $credentials = [
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'is_active' => true,
        ];

        if (! Auth::guard('customer')->attempt(
            $credentials,
            $request->boolean('remember')
        )) {
            return back()
                ->withErrors([
                    'email' => 'E-posta adresi veya şifre hatalı.',
                ])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        $customer = Auth::guard(
            'customer'
        )->user();

        $request->session()->put(
            'customer_session_version',
            $customer->session_version
        );

        if (! $customer->email_verified_at || $customer->cari_plus_current_account_id === null) {
            return redirect()
                ->route(
                    'customer.email.verify'
                );
        }

        return redirect()->intended(
            route('customer.dashboard')
        );
    }

    public function destroy(): RedirectResponse
    {
        Auth::guard('customer')->logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()
            ->route('customer.login');
    }
}
