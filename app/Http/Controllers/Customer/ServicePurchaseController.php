<?php

namespace App\Http\Controllers\Customer;

use App\Exceptions\ContractSigningException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\PurchaseServiceRequest;
use App\Models\Service;
use App\Services\Contracts\CreateServiceOrder;
use Illuminate\Http\RedirectResponse;

class ServicePurchaseController extends Controller
{
    public function __invoke(
        PurchaseServiceRequest $request,
        Service $service,
        CreateServiceOrder $createOrder,
    ): RedirectResponse {
        abort_unless(
            $service->is_active && $service->cari_plus_product_id !== null,
            404,
        );

        try {
            $order = $createOrder->create($request->user('customer'), $service);
        } catch (ContractSigningException $exception) {
            return redirect()
                ->route('customer.services.index')
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('customer.service-orders.contract.show', $order);
    }
}
