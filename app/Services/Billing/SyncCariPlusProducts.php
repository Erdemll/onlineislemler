<?php

namespace App\Services\Billing;

use App\Contracts\CariPlusGateway;
use App\Exceptions\CariPlusException;
use App\Models\InvoiceItem;
use App\Models\Service;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SyncCariPlusProducts
{
    public function __construct(private CariPlusGateway $cariPlus) {}

    public function handle(): int
    {
        try {
            return Cache::lock('cari-plus.products.sync', 300)
                ->block(1, fn (): int => $this->sync());
        } catch (LockTimeoutException $exception) {
            throw new CariPlusException(
                'Cari Plus ürün kataloğu zaten güncelleniyor. Lütfen kısa süre sonra tekrar deneyin.',
                previous: $exception,
            );
        }
    }

    private function sync(): int
    {
        $products = [
            ...$this->fetchProducts(false),
            ...$this->fetchProducts(true),
        ];

        DB::transaction(function () use ($products): void {
            $seenProductIds = [];

            foreach ($products as $product) {
                $service = $this->upsert($product);
                $seenProductIds[$service->cari_plus_product_id] = true;

                InvoiceItem::query()
                    ->where('service_id', $service->id)
                    ->whereNull('cari_plus_product_id')
                    ->update(['cari_plus_product_id' => $service->cari_plus_product_id]);
            }

            Service::query()
                ->whereNotNull('cari_plus_product_id')
                ->get(['id', 'cari_plus_product_id'])
                ->each(function (Service $service) use ($seenProductIds): void {
                    if (! isset($seenProductIds[$service->cari_plus_product_id])) {
                        $service->update(['is_active' => false]);
                    }
                });
        });

        return count($products);
    }

    /** @return list<array<string, mixed>> */
    private function fetchProducts(bool $archived): array
    {
        $products = [];
        $page = 1;

        do {
            $result = $this->cariPlus->listProducts($page, $archived);

            foreach ($result['data'] as $product) {
                $products[] = $product;
            }

            $totalPages = max(1, (int) ($result['meta']['total_pages'] ?? 1));
            $page++;
        } while ($page <= $totalPages);

        return $products;
    }

    /** @param array<string, mixed> $product */
    private function upsert(array $product): Service
    {
        $productId = $product['id'] ?? null;
        $name = $product['name'] ?? null;

        if (! is_int($productId) || ! is_string($name) || $name === '') {
            throw new CariPlusException('Cari Plus ürün kataloğu beklenmeyen bir kayıt döndürdü.');
        }

        $sku = is_string($product['sku'] ?? null) && $product['sku'] !== ''
            ? $product['sku']
            : null;

        $service = Service::query()
            ->where('cari_plus_product_id', $productId)
            ->first();

        if ($service === null && $sku !== null) {
            $service = Service::query()
                ->where('cari_plus_sku', $sku)
                ->first();
        }

        $service ??= new Service;

        $currency = is_string($product['sale_currency'] ?? null)
            ? $product['sale_currency']
            : 'TRY';
        $salePrice = is_numeric($product['sale_price'] ?? null)
            ? (float) $product['sale_price']
            : 0;
        $isArchived = (bool) ($product['is_archived'] ?? false);
        $isPurchasable = ! $isArchived
            && (bool) ($product['is_active'] ?? false)
            && (bool) ($product['is_web_visible'] ?? false)
            && $currency === 'TRY'
            && is_numeric($product['sale_price'] ?? null);

        $service->fill([
            'name' => $name,
            'description' => is_string($product['description'] ?? null)
                ? $product['description']
                : $name,
            'price' => $salePrice,
            'tax_rate' => is_numeric($product['tax_rate'] ?? null)
                ? (float) $product['tax_rate']
                : 0,
            'currency' => $currency,
            'price_includes_tax' => (bool) ($product['price_includes_tax'] ?? false),
            'cari_plus_product_id' => $productId,
            'cari_plus_sku' => $sku,
            'cari_plus_updated_at' => $product['updated_at'] ?? null,
            'synced_at' => now(),
            'is_active' => $isPurchasable,
        ])->save();

        return $service;
    }
}
