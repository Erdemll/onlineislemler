<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\ContractSigningChallenge;
use App\Models\ServiceOrder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceOrderContractController extends Controller
{
    public function show(Request $request, ServiceOrder $serviceOrder): View
    {
        $this->ensureOwnedByCustomer($request, $serviceOrder);
        $serviceOrder->loadMissing('contractVersion.contract', 'acceptance', 'invoice');
        $challenge = null;

        if ($request->filled('challenge')) {
            $challenge = ContractSigningChallenge::query()
                ->where('uuid', $request->string('challenge')->toString())
                ->whereBelongsTo($serviceOrder)
                ->where('customer_id', $request->user('customer')->id)
                ->whereNull('consumed_at')
                ->first();
        }

        return view('customer.contracts.sign', compact('serviceOrder', 'challenge'));
    }

    private function ensureOwnedByCustomer(Request $request, ServiceOrder $serviceOrder): void
    {
        abort_unless($serviceOrder->customer_id === $request->user('customer')->id, 404);
    }
}
