<?php

namespace App\Http\Controllers\Customer;

use App\Exceptions\CariPlusException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\PurchaseServiceRequest;
use App\Models\Service;
use App\Services\Billing\CreateServiceInvoice;
use Illuminate\Http\RedirectResponse;

class ServicePurchaseController extends Controller
{
    public function __invoke(
        PurchaseServiceRequest $request,
        Service $service,
        CreateServiceInvoice $createInvoice,
    ): RedirectResponse {
        abort_unless($service->is_active, 404);

        try {
            $createInvoice->create($request->user('customer'), $service);
        } catch (CariPlusException $exception) {
            return redirect()
                ->route('customer.invoices.index')
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('customer.invoices.index')
            ->with('status', 'Faturanız oluşturuldu. Ödeme adımı yakında burada açılacak.');
    }
}
