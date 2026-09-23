<?php

namespace App\Http\Controllers\Customer;

use App\Exceptions\ContractSigningException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StartContractSigningRequest;
use App\Models\ServiceOrder;
use App\Services\Contracts\StartContractSigning;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class ContractSigningChallengeController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        StartContractSigningRequest $request,
        ServiceOrder $serviceOrder,
        StartContractSigning $startSigning,
    ): RedirectResponse {
        abort_unless($serviceOrder->customer_id === $request->user('customer')->id, 404);
        $sessionIdentifier = Str::random(64);
        $request->session()->put('contract_signing_session_identifier', $sessionIdentifier);

        try {
            $challenge = $startSigning->start(
                customer: $request->user('customer'),
                order: $serviceOrder,
                signatureData: $request->validated('signature_data'),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
                sessionIdentifier: $sessionIdentifier,
            );
        } catch (ContractSigningException $exception) {
            report($exception);

            return back()->withInput($request->safe()->except('signature_data'))->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('customer.service-orders.contract.show', [
                'serviceOrder' => $serviceOrder,
                'challenge' => $challenge->uuid,
            ])
            ->with('status', 'Altı haneli onay kodu kayıtlı e-posta adresinize gönderildi.');
    }
}
