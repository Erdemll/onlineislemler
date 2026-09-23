<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    /**
     * Display a listing of customers.
     */
    public function index(): View
    {
        $customers = Customer::query()
            ->orderBy('last_name')
            ->paginate(20);

        return view('admin.customers.index', compact('customers'));
    }

    /**
     * Show the form for editing the specified customer.
     */
    public function edit(Customer $customer): View
    {
        return view('admin.customers.edit', compact('customer'));
    }

    /**
     * Update the customer's credit limit.
     */
    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'credit_limit' => ['required', 'numeric', 'min:0'],
        ]);

        // Kaydı kuruş cinsinden sakla, ondalık hassasiyet kaybını önlemek için
        $customer->credit_limit = (int) round($validated['credit_limit'] * 100);
        $customer->save();

        return redirect()
            ->route('admin.customers.edit', ['customer' => $customer->uuid])
            ->with('status', 'Limiti güncellendi.');
    }
}
