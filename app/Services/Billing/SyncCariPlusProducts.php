<?php

namespace App\Services\Billing;

use App\Contracts\CariPlusGateway;
use App\Exceptions\CariPlusException;
use App\Models\InvoiceItem;
use App\Models\Service;
use Carbon\CarbonImmutable;
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

        $seenSkus = [];

        foreach ($products as $product) {
            $sku = $product['sku'] ?? null;

            if (! is_string($sku) || $sku === '') {
                continue;
            }

            if (isset($seenSkus[$sku]) && $seenSkus[$sku] !== ($product['id'] ?? null)) {
                throw new CariPlusException('Cari Plus ürün kataloğunda tekrarlanan SKU bulundu.');
            }

            $seenSkus[$sku] = $product['id'] ?? null;
        }

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

            $totalPages = $result['meta']['total_pages'] ?? 1;

            if (! is_int($totalPages) || $totalPages < 1 || $totalPages > 1000) {
                throw new CariPlusException('Cari Plus ürün sayfalama bilgisi geçersiz.');
            }

            $page++;
        } while ($page <= $totalPages);

        return $products;
    }

    /** @param array<string, mixed> $product */
    private function upsert(array $product): Service
    {
        $productId = $product['id'] ?? null;
        $name = $product['name'] ?? null;

        if (! is_int($productId) || $productId <= 0 || ! is_string($name) || trim($name) === '') {
            throw new CariPlusException('Cari Plus ürün kataloğu beklenmeyen bir kayıt döndürdü.');
        }

        foreach (['is_archived', 'is_active', 'is_web_visible', 'price_includes_tax'] as $field) {
            if (! is_bool($product[$field] ?? null)) {
                throw new CariPlusException("Cari Plus ürünündeki {$field} alanı geçersiz.");
            }
        }

        $salePrice = $this->decimal($product, 'sale_price', 9999999999.99);
        $taxRate = $this->decimal($product, 'tax_rate', 100);
        $currency = $product['sale_currency'] ?? null;

        if ($currency !== 'TRY') {
            throw new CariPlusException('Cari Plus ürünü desteklenmeyen bir para birimi döndürdü.');
        }

        $updatedAt = $product['updated_at'] ?? null;

        if ($updatedAt !== null) {
            try {
                $updatedAt = CarbonImmutable::parse($updatedAt);
            } catch (\Throwable) {
                throw new CariPlusException('Cari Plus ürün güncelleme tarihi geçersiz.');
            }
        }

        $sku = is_string($product['sku'] ?? null) && $product['sku'] !== ''
            ? $product['sku']
            : null;

        $category = $product['category'] ?? null;
        $categoryId = is_array($category) ? ($category['id'] ?? null) : null;
        $categoryName = is_array($category) ? ($category['name'] ?? null) : null;

        if (! is_int($categoryId) || $categoryId <= 0) {
            $categoryId = null;
        }

        if (! is_string($categoryName) || trim($categoryName) === '') {
            $categoryName = null;
        }

        $service = Service::query()
            ->where('cari_plus_product_id', $productId)
            ->first();

        if ($service === null && $sku !== null) {
            $service = Service::query()
                ->where('cari_plus_sku', $sku)
                ->whereNull('cari_plus_product_id')
                ->first();
        }

        $service ??= new Service;

        $isArchived = $product['is_archived'];
        $isPurchasable = ! $isArchived
            && $product['is_active']
            && $product['is_web_visible'];

        $service->fill([
            'name' => $name,
            'description' => is_string($product['description'] ?? null)
                ? $product['description']
                : $name,
            'price' => $salePrice,
            'tax_rate' => $taxRate,
            'currency' => $currency,
            'category_id' => $categoryId,
            'category_name' => $categoryName,
            'price_includes_tax' => $product['price_includes_tax'],
            'cari_plus_product_id' => $productId,
            'cari_plus_sku' => $sku,
            'cari_plus_updated_at' => $updatedAt,
            'synced_at' => now(),
            'is_active' => $isPurchasable,
        ])->save();

        return $service;
    }

    /** @param array<string, mixed> $product */
    private function decimal(array $product, string $field, float $maximum): float
    {
        $value = $product[$field] ?? null;

        if (! is_int($value) && ! is_float($value) && ! is_string($value)) {
            throw new CariPlusException("Cari Plus ürünündeki {$field} alanı geçersiz.");
        }

        if (! is_numeric($value)) {
            throw new CariPlusException("Cari Plus ürünündeki {$field} alanı geçersiz.");
        }

        $number = (float) $value;

        if (! is_finite($number) || $number < 0 || $number > $maximum) {
            throw new CariPlusException("Cari Plus ürünündeki {$field} alanı geçersiz.");
        }

        return round($number, 2);
    }
}
