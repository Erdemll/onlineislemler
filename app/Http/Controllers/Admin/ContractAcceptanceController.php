<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContractAcceptance;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContractAcceptanceController extends Controller
{
    public function index(Request $request): View
    {
        $search = mb_substr(trim((string) $request->query('q')), 0, 100);

        $acceptances = ContractAcceptance::query()
            ->with([
                'customer:id,first_name,last_name,email',
                'serviceOrder:id,uuid,service_name_snapshot',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('contract_name_snapshot', 'like', "%{$search}%")
                        ->orWhere('signer_name_snapshot', 'like', "%{$search}%")
                        ->orWhere('email_snapshot', 'like', "%{$search}%")
                        ->orWhere('uuid', $search);
                });
            })
            ->orderByDesc('accepted_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.contract-acceptances.index', compact('acceptances', 'search'));
    }

    public function show(ContractAcceptance $contractAcceptance): View
    {
        $contractAcceptance->load([
            'contractVersion.contract',
            'customer',
            'serviceOrder.invoice',
            'signingChallenge',
            'events' => fn ($query) => $query->orderBy('sequence'),
        ]);

        return view('admin.contract-acceptances.show', compact('contractAcceptance'));
    }
}
