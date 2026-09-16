<?php

namespace App\Services\CariPlus;

use App\Contracts\CariPlusGateway;
use App\Exceptions\CariPlusException;
use App\Models\Service;

class ResolveProduct
{
    public function __construct(private CariPlusGateway $cariPlus) {}

    public function for(Service $service): int
    {
        if ($service->cari_plus_product_id !== null) {
            return $service->cari_plus_product_id;
        }

        $sku = $service->cari_plus_sku ?? sprintf('OI-HIZMET-%06d', $service->id);
        $productId = $this->cariPlus->findProductIdBySku($sku);

        if ($productId === null) {
            $taxRate = (float) $service->tax_rate;
            $netPrice = round(
                (float) $service->price / (1 + ($taxRate / 100)),
                2,
            );
            $product = $this->cariPlus->createProduct([
                'name' => $service->name,
                'sku' => $sku,
                'description' => $service->description,
                'unit' => 'Adet',
                'sale_price' => $netPrice,
                'sale_currency' => 'TRY',
                'tax_type' => 'KDV',
                'tax_rate' => $taxRate,
                'is_active' => true,
                'is_web_visible' => false,
                'track_stock' => false,
            ], 'portal-product-'.$service->id);

            $productId = $product['id'] ?? null;
        }

        if (! is_int($productId)) {
            throw new CariPlusException('Cari Plus ürün eşleşmesi oluşturulamadı.');
        }

        $service->update([
            'cari_plus_product_id' => $productId,
            'cari_plus_sku' => $sku,
        ]);

        return $productId;
    }
}
