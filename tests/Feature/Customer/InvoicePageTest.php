<?php

use App\Contracts\CariPlusGateway;
use App\Enums\InvoiceStatus;
use App\Enums\ServiceOrderStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ServiceOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeCariPlusGateway;

uses(RefreshDatabase::class);

it('shows only the signed in customers invoices', function () {
    $customer = Customer::factory()->ready()->create();
    $otherCustomer = Customer::factory()->ready()->create();
    Invoice::factory()->for($customer)->create(['invoice_number' => 'FTR-BENIM']);
    Invoice::factory()->for($otherCustomer)->create(['invoice_number' => 'FTR-BASKA']);
    $this->app->instance(CariPlusGateway::class, new FakeCariPlusGateway);

    $response = $this
        ->actingAsCustomer($customer)
        ->get(route('customer.invoices.index'));

    $response
        ->assertOk()
        ->assertSeeText('FTR-BENIM')
        ->assertDontSeeText('FTR-BASKA');
});

it('syncs Cari Plus invoices for the matched customer', function () {
    $customer = Customer::factory()->ready()->create([
        'cari_plus_current_account_id' => 55,
    ]);
    $gateway = new FakeCariPlusGateway;
    $gateway->remoteInvoices = [[
        'id' => 901,
        'invoice_number' => 'FTR-2026-0042',
        'status' => 'issued',
        'collection_status' => 'to_collect',
        'currency' => 'TRY',
        'subtotal' => 1000,
        'tax_amount' => 200,
        'total' => 1200,
        'invoice_date' => '2026-09-16',
        'due_date' => '2026-09-30',
    ]];
    $this->app->instance(CariPlusGateway::class, $gateway);

    $response = $this
        ->actingAsCustomer($customer)
        ->post(route('customer.invoices.sync'));

    $response->assertSessionHas('status');
    $this->assertDatabaseHas('invoices', [
        'customer_id' => $customer->id,
        'cari_plus_invoice_id' => 901,
        'invoice_number' => 'FTR-2026-0042',
        'status' => InvoiceStatus::Unpaid->value,
        'total' => 1200,
    ]);
});

it('does not allow a customer to retry another customers invoice', function () {
    $customer = Customer::factory()->ready()->create();
    $otherCustomer = Customer::factory()->ready()->create();
    $invoice = Invoice::factory()->for($otherCustomer)->create([
        'status' => InvoiceStatus::Draft,
    ]);
    $this->app->instance(CariPlusGateway::class, new FakeCariPlusGateway);

    $response = $this
        ->actingAsCustomer($customer)
        ->post(route('customer.invoices.retry', $invoice->uuid));

    $response->assertNotFound();
});

it('does not issue a draft imported from Cari Plus', function () {
    $customer = Customer::factory()->ready()->create([
        'cari_plus_current_account_id' => 55,
    ]);
    $invoice = Invoice::factory()->for($customer)->create([
        'status' => InvoiceStatus::Draft,
        'idempotency_key' => 'sync-imported-draft',
    ]);
    $gateway = new FakeCariPlusGateway;
    $this->app->instance(CariPlusGateway::class, $gateway);

    $response = $this
        ->actingAsCustomer($customer)
        ->post(route('customer.invoices.retry', $invoice->uuid));

    $response->assertConflict();
    expect($gateway->issued)->toBeEmpty();
});

it('advances the invoice and service order when Cari Plus reports payment', function () {
    $customer = Customer::factory()->ready()->create([
        'cari_plus_current_account_id' => 55,
    ]);
    $order = ServiceOrder::factory()->for($customer)->create([
        'status' => ServiceOrderStatus::Invoiced,
    ]);
    $invoice = Invoice::factory()->for($customer)->for($order, 'serviceOrder')->create([
        'cari_plus_invoice_id' => 901,
        'status' => InvoiceStatus::Unpaid,
    ]);
    $gateway = new FakeCariPlusGateway;
    $gateway->remoteInvoices = [[
        'id' => 901,
        'invoice_number' => 'FTR-PAID',
        'status' => 'paid',
        'collection_status' => 'collected',
        'currency' => 'TRY',
        'subtotal' => 100,
        'tax_amount' => 20,
        'total' => 120,
        'invoice_date' => '2026-09-16',
        'due_date' => '2026-09-30',
    ]];
    $this->app->instance(CariPlusGateway::class, $gateway);

    $this->actingAsCustomer($customer)->post(route('customer.invoices.sync'));

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($order->fresh()->status)->toBe(ServiceOrderStatus::Paid);
});
