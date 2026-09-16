<?php

namespace App\Http\Controllers\Customer;

use App\Enums\InvoiceStatus;
use App\Exceptions\CariPlusException;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Billing\CreateServiceInvoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvoiceIssueController extends Controller
{
    public function __invoke(
        Request $request,
        Invoice $invoice,
        CreateServiceInvoice $createInvoice,
    ): RedirectResponse {
        abort_unless(
            $invoice->customer_id === $request->user('customer')->getKey(),
            404,
        );
        abort_unless(
            in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Failed], true),
            409,
        );
        abort_unless(str_starts_with($invoice->idempotency_key, 'portal-'), 409);

        try {
            $createInvoice->retry($invoice);
        } catch (CariPlusException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Fatura Cari Plus’a başarıyla aktarıldı.');
    }
}
