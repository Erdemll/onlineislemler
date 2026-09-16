<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(): View
    {
        return view('customer.profile.show', [
            'customer' => auth('customer')->user(),
        ]);
    }

    public function update(
        UpdateProfileRequest $request
    ): RedirectResponse {
        $customer = auth('customer')->user();

        $customer->update(
            $request->validated()
        );

        return back()->with(
            'status',
            'Profil bilgileriniz güncellendi.'
        );
    }
}
