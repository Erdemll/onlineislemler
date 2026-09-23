<?php

use App\Contracts\CariPlusGateway;
use App\Enums\InvoiceStatus;
use App\Enums\ServiceOrderStatus;
use App\Exceptions\CariPlusException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ServiceOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeCariPlusGateway;

uses(RefreshDatabase::class);

it('shows only the signed in customers invoices', function () {
    $customer = Customer::factory()->ready()->create();
    $otherCustomer = Customer::factory()->ready()->create();
    $invoice = Invoice::factory()->for($customer)->create(['invoice_number' => 'FTR-BENIM']);
    $otherInvoice = Invoice::factory()->for($otherCustomer)->create(['invoice_number' => 'FTR-BASKA']);
    $this->app->instance(CariPlusGateway::class, new FakeCariPlusGateway);

    $response = $this
        ->actingAsCustomer($customer)
        ->get(route('customer.invoices.index'));

    $response
        ->assertOk()
        ->assertSeeText('FTR-BENIM')
        ->assertSee(route('customer.invoices.show', $invoice->uuid))
        ->assertDontSeeText('FTR-BASKA')
        ->assertDontSee(route('customer.invoices.show', $otherInvoice->uuid));
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

it('does not treat the manual collection flag as proof of payment', function () {
    $customer = Customer::factory()->ready()->create(['cari_plus_current_account_id' => 55]);
    $gateway = new FakeCariPlusGateway;
    $gateway->remoteInvoices = [[
        'id' => 901,
        'invoice_number' => 'FTR-2026-0042',
        'status' => 'issued',
        'collection_status' => 'collected',
        'currency' => 'TRY',
        'subtotal' => 1000,
        'tax_amount' => 200,
        'total' => 1200,
        'invoice_date' => '2026-09-16',
    ]];
    $this->app->instance(CariPlusGateway::class, $gateway);

    $this->actingAsCustomer($customer)->post(route('customer.invoices.sync'))->assertRedirect();

    $this->assertDatabaseHas('invoices', [
        'cari_plus_invoice_id' => 901,
        'status' => InvoiceStatus::Unpaid->value,
    ]);
});

it('shows a customers Cari Plus sales invoice with its line items and paid amount', function () {
    $customer = Customer::factory()->ready()->create(['cari_plus_current_account_id' => 55]);
    $invoice = Invoice::factory()->for($customer)->create([
        'cari_plus_invoice_id' => 901,
        'status' => InvoiceStatus::Paid,
    ]);
    $gateway = new FakeCariPlusGateway;
    $gateway->salesInvoiceDetail = [
        'id' => 901,
        'invoice_number' => 'FTR-2026-0042',
        'status' => 'paid',
        'current_account' => ['id' => 55],
        'title' => 'Kamera hizmeti',
        'currency' => 'TRY',
        'subtotal' => 1000,
        'tax_amount' => 200,
        'total' => 1200,
        'paid_amount' => 1200,
        'items' => [[
            'description' => 'Kamera kurulum hizmeti',
            'quantity' => 1,
            'unit_price' => 1000,
            'tax_rate' => 20,
            'amount' => 1000,
        ]],
    ];
    $this->app->instance(CariPlusGateway::class, $gateway);

    $response = $this->actingAsCustomer($customer)
        ->get(route('customer.invoices.show', $invoice->uuid));

    $response->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertSeeText('FTR-2026-0042')
        ->assertSeeText('Kamera kurulum hizmeti')
        ->assertSeeText('Cari Plus tahsilatı')
        ->assertSeeText('Cari Plus: Ödendi');
    expect($gateway->requestedSalesInvoices)->toBe([901]);
});

it('only offers invoice details for invoices owned by the signed-in customer', function () {
    $customer = Customer::factory()->ready()->create();
    $owner = Customer::factory()->ready()->create();
    $invoice = Invoice::factory()->for($owner)->create(['cari_plus_invoice_id' => 901]);
    $gateway = new FakeCariPlusGateway;
    $this->app->instance(CariPlusGateway::class, $gateway);

    $response = $this->actingAsCustomer($customer)
        ->get(route('customer.invoices.show', $invoice->uuid));

    $response->assertNotFound();
    expect($gateway->requestedSalesInvoices)->toBeEmpty();
});

it('shows only the local invoice if Cari Plus details are unavailable or belong to another account', function (bool $differentAccount) {
    $customer = Customer::factory()->ready()->create(['cari_plus_current_account_id' => 55]);
    $invoice = Invoice::factory()->for($customer)->create([
        'cari_plus_invoice_id' => 901,
        'invoice_number' => 'FTR-YEREL',
    ]);
    $gateway = new FakeCariPlusGateway;

    if ($differentAccount) {
        $gateway->salesInvoiceDetail = [
            'id' => 901,
            'invoice_number' => 'FTR-BASKA-CARI',
            'currency' => 'TRY',
            'status' => 'issued',
            'current_account' => ['id' => 999],
            'items' => [],
        ];
    } else {
        $gateway->salesInvoiceDetailException = new CariPlusException('Cari Plus servisine ulaşılamıyor.');
    }

    $this->app->instance(CariPlusGateway::class, $gateway);

    $response = $this->actingAsCustomer($customer)
        ->get(route('customer.invoices.show', $invoice->uuid));

    $response->assertOk()
        ->assertSeeText('FTR-YEREL')
        ->assertSeeText('Cari Plus fatura ayrıntılarına şu anda ulaşılamıyor.')
        ->assertDontSeeText('FTR-BASKA-CARI');
})->with([
    'different account' => [true],
    'provider unavailable' => [false],
]);
