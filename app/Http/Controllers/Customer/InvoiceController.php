<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $customer = $request->user('customer');
        $invoices = $customer->invoices()
            ->with('items')
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->paginate(12);

        return view('customer.invoices.index', [
            'invoices' => $invoices,
            'canSync' => $customer->cari_plus_current_account_id !== null
                || filled($customer->cari_plus_current_account_code),
        ]);
    }
}
