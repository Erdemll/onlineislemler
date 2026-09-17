<?php

namespace App\Services\Contracts;

use App\Enums\ServiceOrderStatus;
use App\Exceptions\ContractSigningException;
use App\Models\ContractVersion;
use App\Models\Customer;
use App\Models\Service;
use App\Models\ServiceOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateServiceOrder
{
    public function __construct(private RecordContractAuditEvent $audit) {}

    public function create(Customer $customer, Service $service): ServiceOrder
    {
        $contractVersion = ContractVersion::query()
            ->whereHas('contract', fn ($query) => $query->where('is_active', true))
            ->whereHas('services', fn ($query) => $query->whereKey($service->id)->where('contract_service.is_required', true))
            ->whereNotNull('published_at')
            ->where('effective_at', '<=', now())
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->first();

        if ($contractVersion === null) {
            throw new ContractSigningException('Bu hizmet için yayımlanmış bir sözleşme bulunmuyor.');
        }

        $purchaseKey = hash('sha256', "{$customer->id}:{$service->id}:{$contractVersion->id}");
        $order = DB::transaction(fn (): ServiceOrder => ServiceOrder::query()->firstOrCreate(
            ['purchase_key' => $purchaseKey],
            [
                'uuid' => (string) Str::uuid(),
                'customer_id' => $customer->id,
                'service_id' => $service->id,
                'contract_version_id' => $contractVersion->id,
                'status' => ServiceOrderStatus::AwaitingContract,
                'service_name_snapshot' => $service->name,
                'service_description_snapshot' => $service->description,
                'cari_plus_product_id_snapshot' => $service->cari_plus_product_id,
                'currency' => $service->currency,
                'unit_price' => $service->grossPrice(),
                'tax_rate' => $service->tax_rate,
                'price_includes_tax' => true,
            ],
        ));

        if ($order->wasRecentlyCreated) {
            $this->audit->record($order, 'service_order_created', [
                'service_id' => $service->id,
                'contract_version_id' => $contractVersion->id,
                'unit_price' => $order->unit_price,
                'currency' => $order->currency,
            ]);
        }

        return $order;
    }
}
