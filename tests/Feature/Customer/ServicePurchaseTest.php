<?php

use App\Contracts\CariPlusGateway;
use App\Enums\InvoiceStatus;
use App\Exceptions\CariPlusException;
use App\Models\Customer;
use App\Models\Service;
use App\Services\Billing\CreateServiceInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeCariPlusGateway;

uses(RefreshDatabase::class);

it('creates and issues a Cari Plus invoice for the selected service', function () {
    $customer = Customer::factory()->ready()->create([
        'cari_plus_current_account_id' => 55,
        'cari_plus_current_account_code' => 'MUS000001',
    ]);
    $service = Service::factory()->create([
        'name' => 'Hızlı İnternet',
        'price' => 899.90,
        'tax_rate' => 20,
        'cari_plus_product_id' => 30112,
        'cari_plus_sku' => 'OI-HIZMET-000001',
    ]);
    $gateway = new FakeCariPlusGateway;
    $this->app->instance(CariPlusGateway::class, $gateway);

    app(CreateServiceInvoice::class)->create($customer, $service);
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
        'cari_plus_product_id' => 30112,
        'name' => 'Hızlı İnternet',
        'line_total' => 899.90,
    ]);
    expect($gateway->created)->toHaveCount(1)
        ->and($gateway->created[0]['payload']['current_account_id'])->toBe(55)
        ->and($gateway->created[0]['payload']['items'][0]['product_id'])->toBe(30112)
        ->and($gateway->created[0]['payload']['items'][0]['unit_price'])->toBe(899.9)
        ->and($gateway->created[0]['payload']['price_includes_tax'])->toBeTrue()
        ->and($gateway->issued)->toHaveCount(1)
        ->and($gateway->resolvedCodes)->toBeEmpty()
        ->and($customer->refresh()->cari_plus_current_account_id)->toBe(55)
        ->and($gateway->createdProducts)->toBeEmpty()
        ->and($service->refresh()->cari_plus_product_id)->toBe(30112)
        ->and($service->cari_plus_sku)->toBe('OI-HIZMET-000001');
});

it('keeps a failed local record when Cari Plus cannot create the draft', function () {
    $customer = Customer::factory()->ready()->create([
        'cari_plus_current_account_id' => 55,
    ]);
    $service = Service::factory()->create([
        'cari_plus_product_id' => 30112,
    ]);
    $gateway = new FakeCariPlusGateway;
    $gateway->createException = new CariPlusException('Cari Plus geçici olarak kullanılamıyor.');
    $this->app->instance(CariPlusGateway::class, $gateway);

    $call = fn () => app(CreateServiceInvoice::class)->create($customer, $service);

    expect($call)->toThrow(CariPlusException::class, 'Cari Plus geçici olarak kullanılamıyor.');
    $this->assertDatabaseHas('invoices', [
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'status' => InvoiceStatus::Failed->value,
    ]);
});

it('keeps the draft retryable when Cari Plus issues a different invoice', function () {
    $customer = Customer::factory()->ready()->create([
        'cari_plus_current_account_id' => 55,
    ]);
    $service = Service::factory()->create([
        'cari_plus_product_id' => 30112,
    ]);
    $gateway = new FakeCariPlusGateway;
    $gateway->issuedInvoiceResponse = [
        'id' => 999,
        'status' => 'issued',
    ];
    $this->app->instance(CariPlusGateway::class, $gateway);

    $call = fn () => app(CreateServiceInvoice::class)->create($customer, $service);

    expect($call)->toThrow(CariPlusException::class, 'Cari Plus beklenmeyen bir fatura kimliği döndürdü.');
    $this->assertDatabaseHas('invoices', [
        'customer_id' => $customer->id,
        'cari_plus_invoice_id' => 902,
        'status' => InvoiceStatus::Draft->value,
    ]);
});

it('does not issue a draft whose amount, currency or state differs from the purchase', function (array $changes) {
    $customer = Customer::factory()->ready()->create(['cari_plus_current_account_id' => 55]);
    $service = Service::factory()->create(['cari_plus_product_id' => 30112, 'price' => 899.90]);
    $gateway = new FakeCariPlusGateway;
    $gateway->salesInvoiceResponse = [...$gateway->salesInvoiceResponse, ...$changes];
    $this->app->instance(CariPlusGateway::class, $gateway);

    expect(fn () => app(CreateServiceInvoice::class)->create($customer, $service))
        ->toThrow(CariPlusException::class, 'Cari Plus faturası sözleşmedeki tutar, para birimi veya durumla eşleşmiyor.');

    expect($gateway->issued)->toBeEmpty();
    $this->assertDatabaseHas('invoices', ['customer_id' => $customer->id, 'status' => InvoiceStatus::Failed->value]);
})->with([
    'wrong total' => [['total' => 100]],
    'wrong currency' => [['currency' => 'USD']],
    'premature status' => [['status' => 'issued']],
]);

it('does not offer payment when the issued invoice no longer matches the draft', function () {
    $customer = Customer::factory()->ready()->create(['cari_plus_current_account_id' => 55]);
    $service = Service::factory()->create(['cari_plus_product_id' => 30112, 'price' => 899.90]);
    $gateway = new FakeCariPlusGateway;
    $gateway->issuedInvoiceResponse = [
        'id' => 902,
        'status' => 'issued',
        'currency' => 'TRY',
        'total' => 999.90,
    ];
    $this->app->instance(CariPlusGateway::class, $gateway);

    expect(fn () => app(CreateServiceInvoice::class)->create($customer, $service))
        ->toThrow(CariPlusException::class, 'Cari Plus faturası sözleşmedeki tutar, para birimi veya durumla eşleşmiyor.');

    $this->assertDatabaseHas('invoices', [
        'customer_id' => $customer->id,
        'cari_plus_invoice_id' => 902,
        'status' => InvoiceStatus::Draft->value,
    ]);
});

it('does not create a local invoice for a service without a Cari Plus product match', function () {
    $customer = Customer::factory()->ready()->create([
        'cari_plus_current_account_id' => 55,
    ]);
    $service = Service::factory()->create();
    $gateway = new FakeCariPlusGateway;
    $this->app->instance(CariPlusGateway::class, $gateway);

    $response = $this
        ->actingAsCustomer($customer)
        ->post(route('customer.services.purchase', $service));

    $response->assertNotFound();
    $this->assertDatabaseCount('invoices', 0);
    expect($gateway->created)->toBeEmpty();
});

it('redirects a customer without a Cari Plus account to account setup', function () {
    $customer = Customer::factory()->emailVerified()->create();
    $service = Service::factory()->create([
        'cari_plus_product_id' => 30112,
    ]);
    $gateway = new FakeCariPlusGateway;
    $this->app->instance(CariPlusGateway::class, $gateway);

    $response = $this
        ->actingAsCustomer($customer)
        ->post(route('customer.services.purchase', $service));

    $response->assertRedirect(route('customer.email.verify'));
    $this->assertDatabaseCount('invoices', 0);
    expect($gateway->created)->toBeEmpty();
});

it('redirects a legacy customer with an unresolved Cari Plus code to account setup', function () {
    $customer = Customer::factory()->emailVerified()->create([
        'cari_plus_current_account_code' => 'BULUNAMADI',
    ]);
    $service = Service::factory()->create([
        'cari_plus_product_id' => 30112,
    ]);
    $gateway = new FakeCariPlusGateway;
    $gateway->currentAccountId = null;
    $this->app->instance(CariPlusGateway::class, $gateway);

    $response = $this
        ->actingAsCustomer($customer)
        ->post(route('customer.services.purchase', $service));

    $response->assertRedirect(route('customer.email.verify'));
    $this->assertDatabaseCount('invoices', 0);
    expect($gateway->created)->toBeEmpty();
});

it('returns 404 for an inactive service', function () {
    $customer = Customer::factory()->emailVerified()->create([
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
