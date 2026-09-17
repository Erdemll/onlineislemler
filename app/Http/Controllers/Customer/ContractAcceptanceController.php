<?php

namespace App\Http\Controllers\Customer;

use App\Enums\ServiceOrderStatus;
use App\Exceptions\ContractSigningException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\VerifyContractSigningRequest;
use App\Models\ServiceOrder;
use App\Services\Contracts\AcceptContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContractAcceptanceController extends Controller
{
    public function index(Request $request): View
    {
        $orders = ServiceOrder::query()
            ->whereBelongsTo($request->user('customer'))
            ->with(['contractVersion.contract', 'acceptance', 'invoice'])
            ->orderByDesc('created_at')
            ->get();

        return view('customer.contracts.index', compact('orders'));
    }

    public function store(
        VerifyContractSigningRequest $request,
        ServiceOrder $serviceOrder,
        AcceptContract $acceptContract,
    ): RedirectResponse {
        abort_unless($serviceOrder->customer_id === $request->user('customer')->id, 404);

        try {
            $acceptance = $acceptContract->accept(
                customer: $request->user('customer'),
                order: $serviceOrder,
                challengeUuid: $request->validated('challenge_uuid'),
                code: $request->validated('code'),
            );
        } catch (ContractSigningException $exception) {
            return redirect()
                ->route('customer.service-orders.contract.show', [
                    'serviceOrder' => $serviceOrder,
                    'challenge' => $request->validated('challenge_uuid'),
                ])
                ->withErrors(['code' => $exception->getMessage()]);
        }

        if ($serviceOrder->fresh()->status === ServiceOrderStatus::InvoiceFailed) {
            return redirect()
                ->route('customer.contracts.index')
                ->with('error', 'Sözleşmeniz kabul edildi ancak fatura şu anda oluşturulamadı. Faturalar sayfasından tekrar deneyebilirsiniz.');
        }

        return redirect()
            ->route('customer.invoices.index')
            ->with('status', 'Sözleşmeniz kabul edildi ve faturanız oluşturuldu.');
    }
}
