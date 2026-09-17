<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\CariPlusGateway;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreContractVersionRequest;
use App\Models\Contract;
use App\Models\Service;
use App\Services\Contracts\PublishContractVersion;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Throwable;

class ContractController extends Controller
{
    public function index(): View
    {
        $contracts = Contract::query()
            ->with(['versions' => fn ($query) => $query
                ->with('services:id,name,cari_plus_product_id')
                ->orderByDesc('effective_at')
                ->orderByDesc('id')])
            ->withCount('versions')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.contracts.index', compact('contracts'));
    }

    public function create(CariPlusGateway $cariPlus): View
    {
        return view('admin.contracts.create', [
            'contracts' => Contract::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'services' => Service::query()
                ->where('is_active', true)
                ->whereNotNull('cari_plus_product_id')
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'name', 'cari_plus_product_id', 'cari_plus_sku', 'price', 'currency']),
            'canSyncProducts' => $cariPlus->isConfigured(),
        ]);
    }

    public function store(
        StoreContractVersionRequest $request,
        PublishContractVersion $publishContractVersion,
    ): RedirectResponse {
        $contract = $request->filled('contract_id')
            ? Contract::query()->findOrFail($request->integer('contract_id'))
            : null;

        try {
            $contractVersion = $publishContractVersion->handle(
                contract: $contract,
                newContractName: $request->validated('new_contract_name'),
                version: $request->validated('version'),
                effectiveAt: CarbonImmutable::parse($request->validated('effective_at')),
                document: $request->file('document'),
                serviceIds: collect($request->validated('service_ids'))
                    ->map(fn ($serviceId): int => (int) $serviceId)
                    ->all(),
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->with('error', 'Sözleşme yayımlanamadı. PDF dosyasını ve sürüm bilgilerini kontrol edip tekrar deneyin.');
        }

        return redirect()
            ->route('admin.contracts.index')
            ->with('status', "{$contractVersion->contract->name} sürüm {$contractVersion->version} yayımlandı.");
    }
}
