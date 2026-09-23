<?php

namespace App\Http\Controllers\Customer;

use App\Exceptions\ToslaException;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Payments\StartInvoicePayment;
use Illuminate\Http\RedirectResponse;

class InvoicePaymentController extends Controller
{
    public function store(Invoice $invoice, StartInvoicePayment $startPayment): RedirectResponse
    {
        if ($invoice->customer_id !== auth('customer')->id()) {
            abort(404);
        }

        try {
            $result = $startPayment->handle($invoice);
        } catch (ToslaException $exception) {
            report($exception);

            return back()->with('error', $exception->getMessage());
        }

        return redirect()->away($result['redirect_url']);
    }
}
