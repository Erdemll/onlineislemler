<?php

use App\Contracts\CariPlusGateway;
use App\Enums\InvoiceStatus;
use App\Exceptions\CariPlusException;
use App\Models\Customer;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeCariPlusGateway;

uses(RefreshDatabase::class);

it('creates and issues a Cari Plus invoice for the selected service', function () {
    $customer = Customer::factory()->phoneVerified()->create([
        'cari_plus_current_account_code' => 'MUS000001',
    ]);
    $service = Service::factory()->create([
        'name' => 'Hızlı İnternet',
        'price' => 899.90,
        'tax_rate' => 20,
    ]);
    $gateway = new FakeCariPlusGateway;
    $this->app->instance(CariPlusGateway::class, $gateway);

    $response = $this
        ->actingAsCustomer($customer)
        ->post(route('customer.services.purchase', $service));

    $response
        ->assertRedirect(route('customer.invoices.index'))
        ->assertSessionHas('status');
    $this->assertDatabaseHas('invoices', [
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'cari_plus_invoice_id' => 902,
        'invoice_number' => 'FTR-2026-0043',
        'status' => InvoiceStatus::Unpaid->value,
        'total' => 899.90,
    ]);
    $this->assertDatabaseHas('invoice_items', [
        'service_id' => $service->id,
        'name' => 'Hızlı İnternet',
        'line_total' => 899.90,
    ]);
    expect($gateway->created)->toHaveCount(1)
        ->and($gateway->created[0]['payload']['current_account_id'])->toBe(55)
        ->and($gateway->created[0]['payload']['items'][0]['product_id'])->toBe(30112)
        ->and($gateway->created[0]['payload']['items'][0]['unit_price'])->toBe(749.92)
        ->and($gateway->created[0]['payload'])->not->toHaveKey('price_includes_tax')
        ->and($gateway->issued)->toHaveCount(1)
        ->and($gateway->resolvedCodes)->toBe(['MUS000001'])
        ->and($customer->refresh()->cari_plus_current_account_id)->toBe(55)
        ->and($gateway->createdProducts)->toHaveCount(1)
        ->and($gateway->createdProducts[0]['payload']['track_stock'])->toBeFalse()
        ->and($service->refresh()->cari_plus_product_id)->toBe(30112)
        ->and($service->cari_plus_sku)->toBe('OI-HIZMET-000001');
});

it('keeps a failed local record when Cari Plus cannot create the draft', function () {
    $customer = Customer::factory()->phoneVerified()->create([
        'cari_plus_current_account_id' => 55,
    ]);
    $service = Service::factory()->create();
    $gateway = new FakeCariPlusGateway;
    $gateway->createException = new CariPlusException('Cari Plus geçici olarak kullanılamıyor.');
    $this->app->instance(CariPlusGateway::class, $gateway);

    $response = $this
        ->actingAsCustomer($customer)
        ->post(route('customer.services.purchase', $service));

    $response
        ->assertRedirect(route('customer.invoices.index'))
        ->assertSessionHas('error', 'Cari Plus geçici olarak kullanılamıyor.');
    $this->assertDatabaseHas('invoices', [
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'status' => InvoiceStatus::Failed->value,
    ]);
});

it('does not create an invoice without a Cari Plus customer match', function () {
    $customer = Customer::factory()->phoneVerified()->create();
    $service = Service::factory()->create();
    $gateway = new FakeCariPlusGateway;
    $this->app->instance(CariPlusGateway::class, $gateway);

    $response = $this
        ->actingAsCustomer($customer)
        ->post(route('customer.services.purchase', $service));

    $response->assertSessionHas('error', 'Cari Plus müşteri eşleşmesi henüz yapılmadı.');
    $this->assertDatabaseCount('invoices', 0);
    expect($gateway->created)->toBeEmpty();
});

it('does not create an invoice when the Cari Plus customer code is unknown', function () {
    $customer = Customer::factory()->phoneVerified()->create([
        'cari_plus_current_account_code' => 'BULUNAMADI',
    ]);
    $service = Service::factory()->create();
    $gateway = new FakeCariPlusGateway;
    $gateway->currentAccountId = null;
    $this->app->instance(CariPlusGateway::class, $gateway);

    $response = $this
        ->actingAsCustomer($customer)
        ->post(route('customer.services.purchase', $service));

    $response->assertSessionHas('error', 'Cari Plus’ta BULUNAMADI kodlu cari hesap bulunamadı.');
    $this->assertDatabaseCount('invoices', 0);
    expect($gateway->created)->toBeEmpty();
});

it('returns 404 for an inactive service', function () {
    $customer = Customer::factory()->phoneVerified()->create([
        'cari_plus_current_account_id' => 55,
    ]);
    $service = Service::factory()->inactive()->create();
    $this->app->instance(CariPlusGateway::class, new FakeCariPlusGateway);

    $response = $this
        ->actingAsCustomer($customer)
        ->post(route('customer.services.purchase', $service));

    $response->assertNotFound();
    $this->assertDatabaseCount('invoices', 0);
});
