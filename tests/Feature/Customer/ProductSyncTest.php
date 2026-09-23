<?php

use App\Contracts\CariPlusGateway;
use App\Exceptions\CariPlusException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeCariPlusGateway;

uses(RefreshDatabase::class);

it('syncs the Cari Plus catalog into services and backfills invoice item product ids', function () {
    $customer = Customer::factory()->ready()->create();
    $service = Service::factory()->create([
        'name' => 'Eski ad',
        'cari_plus_sku' => 'IP-001',
    ]);
    $localOnlyService = Service::factory()->create([
        'name' => 'Yerel deneme hizmeti',
    ]);
    $invoice = Invoice::factory()->for($customer)->for($service)->create();
    InvoiceItem::factory()->for($invoice)->for($service)->create();
    $gateway = new FakeCariPlusGateway;
    $gateway->remoteProducts = [[
        'id' => 501,
        'sku' => 'IP-001',
        'name' => 'Statik IP',
        'description' => 'Sabit IP hizmeti',
        'category' => ['id' => 7, 'name' => 'Ağ Hizmetleri'],
        'sale_price' => 100,
        'sale_currency' => 'TRY',
        'tax_rate' => 20,
        'price_includes_tax' => false,
        'is_active' => true,
        'is_web_visible' => true,
        'is_archived' => false,
        'updated_at' => '2026-09-17T09:00:00+03:00',
    ]];
    $gateway->archivedProducts = [[
        'id' => 502,
        'sku' => 'ESKI-001',
        'name' => 'Eski Paket',
        'description' => null,
        'sale_price' => 50,
        'sale_currency' => 'TRY',
        'tax_rate' => 20,
        'price_includes_tax' => true,
        'is_active' => true,
        'is_web_visible' => true,
        'is_archived' => true,
        'updated_at' => '2026-09-16T09:00:00+03:00',
    ]];
    $this->app->instance(CariPlusGateway::class, $gateway);

    $response = $this
        ->actingAsCustomer($customer)
        ->post(route('customer.services.sync'));

    $response
        ->assertRedirect()
        ->assertSessionHas('status', 'Cari Plus kataloğundaki 2 ürün güncellendi.');
    $this->assertDatabaseHas('services', [
        'id' => $service->id,
        'cari_plus_product_id' => 501,
        'cari_plus_sku' => 'IP-001',
        'name' => 'Statik IP',
        'price' => 100,
        'currency' => 'TRY',
        'category_id' => 7,
        'category_name' => 'Ağ Hizmetleri',
        'price_includes_tax' => false,
        'is_active' => true,
    ]);
    $this->assertDatabaseHas('services', [
        'cari_plus_product_id' => 502,
        'is_active' => false,
    ]);
    $this->assertDatabaseHas('invoice_items', [
        'invoice_id' => $invoice->id,
        'service_id' => $service->id,
        'cari_plus_product_id' => 501,
    ]);
    expect($gateway->listedProducts)->toBe([
        ['page' => 1, 'archived' => false],
        ['page' => 1, 'archived' => true],
    ]);

    $page = $this
        ->actingAsCustomer($customer)
        ->get(route('customer.services.index'));

    $page
        ->assertSeeText('Statik IP')
        ->assertSeeText('120,00 ₺')
        ->assertDontSeeText('Eski Paket')
        ->assertDontSeeText($localOnlyService->name);
});

it('keeps the cached catalog when Cari Plus cannot be reached', function () {
    $customer = Customer::factory()->ready()->create();
    $service = Service::factory()->create([
        'name' => 'Mevcut Hizmet',
        'cari_plus_product_id' => 501,
        'synced_at' => now()->subHour(),
    ]);
    $gateway = new FakeCariPlusGateway;
    $gateway->productListException = new CariPlusException('Cari Plus servisine şu anda ulaşılamıyor.');
    $this->app->instance(CariPlusGateway::class, $gateway);

    $response = $this
        ->actingAsCustomer($customer)
        ->post(route('customer.services.sync'));

    $response
        ->assertRedirect()
        ->assertSessionHas('error', 'Cari Plus servisine şu anda ulaşılamıyor.');
    $this->assertDatabaseHas('services', [
        'id' => $service->id,
        'name' => 'Mevcut Hizmet',
        'is_active' => true,
    ]);
});

it('returns JSON for the automatic catalog refresh', function () {
    $customer = Customer::factory()->ready()->create();
    $gateway = new FakeCariPlusGateway;
    $this->app->instance(CariPlusGateway::class, $gateway);

    $response = $this
        ->actingAsCustomer($customer)
        ->postJson(route('customer.services.sync'));

    $response
        ->assertOk()
        ->assertJsonPath('count', 0)
        ->assertJsonPath('message', 'Cari Plus kataloğundaki 0 ürün güncellendi.');
});

it('rejects malformed financial product data without changing the cached catalog', function () {
    $customer = Customer::factory()->ready()->create();
    $service = Service::factory()->create([
        'name' => 'Güvenli katalog kaydı',
        'cari_plus_product_id' => 501,
        'price' => 100,
    ]);
    $gateway = new FakeCariPlusGateway;
    $gateway->remoteProducts = [[
        'id' => 501,
        'name' => 'Bozuk fiyat',
        'sale_price' => -50,
        'sale_currency' => 'TRY',
        'tax_rate' => 20,
        'price_includes_tax' => true,
        'is_active' => true,
        'is_web_visible' => true,
        'is_archived' => false,
        'updated_at' => '2026-09-17T09:00:00+03:00',
    ]];
    $this->app->instance(CariPlusGateway::class, $gateway);

    $response = $this
        ->actingAsCustomer($customer)
        ->postJson(route('customer.services.sync'));

    $response->assertServiceUnavailable();
    expect($service->fresh()->name)->toBe('Güvenli katalog kaydı')
        ->and($service->fresh()->price)->toBe('100.00');
});

it('rejects ambiguous remote SKUs without reassigning a linked service', function () {
    $customer = Customer::factory()->ready()->create();
    $service = Service::factory()->create([
        'name' => 'Mevcut ürün',
        'cari_plus_product_id' => 501,
        'cari_plus_sku' => 'ORTAK-SKU',
    ]);
    $gateway = new FakeCariPlusGateway;
    $gateway->remoteProducts = [
        ['id' => 501, 'sku' => 'ORTAK-SKU'],
        ['id' => 502, 'sku' => 'ORTAK-SKU'],
    ];
    $this->app->instance(CariPlusGateway::class, $gateway);

    $response = $this->actingAsCustomer($customer)
        ->postJson(route('customer.services.sync'));

    $response->assertServiceUnavailable();
    expect($service->fresh()->cari_plus_product_id)->toBe(501);
    expect($service->fresh()->name)->toBe('Mevcut ürün');
    $this->assertDatabaseCount('services', 1);
});

it('renders an automatic loader without a manual refresh button', function () {
    $customer = Customer::factory()->ready()->create();
    $this->app->instance(CariPlusGateway::class, new FakeCariPlusGateway);

    $response = $this
        ->actingAsCustomer($customer)
        ->get(route('customer.services.index'));

    $response
        ->assertOk()
        ->assertSee('data-product-sync-form', false)
        ->assertSee('data-product-sync-status', false);
});
