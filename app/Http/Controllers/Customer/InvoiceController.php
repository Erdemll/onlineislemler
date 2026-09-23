<?php

namespace App\Http\Controllers\Customer;

use App\Exceptions\CariPlusException;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Billing\GetCustomerSalesInvoice;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

    public function show(Request $request, Invoice $invoice, GetCustomerSalesInvoice $salesInvoice): Response
    {
        abort_unless($invoice->customer_id === $request->user('customer')->getKey(), 404);

        $details = null;

        if ($invoice->cari_plus_invoice_id !== null) {
            try {
                $details = $salesInvoice->for($request->user('customer'), $invoice);
            } catch (CariPlusException $exception) {
                report($exception);
            }
        }

        return response()->view('customer.invoices.show', [
            'invoice' => $invoice,
            'details' => $details,
        ])->header('Cache-Control', 'private, no-store');
    }
}
