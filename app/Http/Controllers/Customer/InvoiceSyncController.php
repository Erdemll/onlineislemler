<?php

namespace App\Http\Controllers\Customer;

use App\Exceptions\CariPlusException;
use App\Http\Controllers\Controller;
use App\Services\Billing\SyncCustomerInvoices;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvoiceSyncController extends Controller
{
    public function __invoke(
        Request $request,
        SyncCustomerInvoices $syncInvoices,
    ): RedirectResponse {
        try {
            $count = $syncInvoices->handle($request->user('customer'));
        } catch (CariPlusException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', "Cari Plus ile eşitleme tamamlandı. {$count} fatura güncellendi.");
    }
}
