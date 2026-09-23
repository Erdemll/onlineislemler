<?php

use App\Contracts\ToslaGateway;
use App\Enums\InvoiceStatus;
use App\Exceptions\ToslaException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Payments\StartInvoicePayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Fakes\FakeToslaGateway;

uses(RefreshDatabase::class);

it('starts a 3D payment and redirects to the Aköde shared payment page', function () {
    $customer = Customer::factory()->ready()->create();
    $invoice = Invoice::factory()->for($customer)->create([
        'status' => InvoiceStatus::Unpaid,
        'total' => 1200,
    ]);
    $gateway = new FakeToslaGateway;
    $this->app->instance(ToslaGateway::class, $gateway);

    $response = $this
        ->actingAsCustomer($customer)
        ->post(route('customer.invoices.pay', $invoice->uuid));

    $response->assertRedirect($gateway->sharedPaymentUrl('ACBB40D1C34314940B7CCAC126E06E898DCDD6F75E102422B923B37D5D3688F0C'));
    $this->assertDatabaseHas('payments', [
        'invoice_id' => $invoice->id,
        'customer_id' => $customer->id,
        'amount_kurus' => 120000,
        'status' => 'pending',
    ]);
    expect($gateway->started)->toHaveCount(1)
        ->and($gateway->started[0]['amount_kurus'])->toBe(120000)
        ->and($gateway->started[0]['echo'])->toBe($invoice->uuid)
        ->and($gateway->started[0]['callback_url'])->toBe(route('payment.callback.akode'));
});

it('does not start a second payment while one is pending', function () {
    $customer = Customer::factory()->ready()->create();
    $invoice = Invoice::factory()->for($customer)->create([
        'status' => InvoiceStatus::Unpaid,
    ]);
    Payment::factory()->for($customer)->for($invoice)->create();
    $gateway = new FakeToslaGateway;
    $this->app->instance(ToslaGateway::class, $gateway);

    $response = $this
        ->actingAsCustomer($customer)
        ->from(route('customer.invoices.index'))
        ->post(route('customer.invoices.pay', $invoice->uuid));

    $response->assertRedirect(route('customer.invoices.index'))
        ->assertSessionHas('error');
    expect($gateway->started)->toBeEmpty();
});

it('rejects payment for an invoice that is not unpaid', function () {
    $customer = Customer::factory()->ready()->create();
    $invoice = Invoice::factory()->for($customer)->create([
        'status' => InvoiceStatus::Paid,
    ]);
    $gateway = new FakeToslaGateway;
    $this->app->instance(ToslaGateway::class, $gateway);

    $response = $this
        ->actingAsCustomer($customer)
        ->post(route('customer.invoices.pay', $invoice->uuid));

    $response->assertSessionHas('error');
    $this->assertDatabaseCount('payments', 0);
});

it('returns 404 when paying another customers invoice', function () {
    $customer = Customer::factory()->ready()->create();
    $owner = Customer::factory()->ready()->create();
    $invoice = Invoice::factory()->for($owner)->create([
        'status' => InvoiceStatus::Unpaid,
    ]);
    $gateway = new FakeToslaGateway;
    $this->app->instance(ToslaGateway::class, $gateway);

    $response = $this
        ->actingAsCustomer($customer)
        ->post(route('customer.invoices.pay', $invoice->uuid));

    $response->assertNotFound();
    $this->assertDatabaseCount('payments', 0);
});

it('uses the configured public callback URL for the shared payment page', function () {
    $customer = Customer::factory()->ready()->create();
    $invoice = Invoice::factory()->for($customer)->create(['status' => InvoiceStatus::Unpaid]);
    $gateway = new FakeToslaGateway;
    $this->app->instance(ToslaGateway::class, $gateway);
    config()->set('services.akode.callback_url', 'https://example.com/odeme/callback/akode');

    $response = $this->actingAsCustomer($customer)
        ->post(route('customer.invoices.pay', $invoice->uuid));

    $response->assertRedirect($gateway->sharedPaymentUrl($gateway->startResponse['three_d_session_id']));
    expect($gateway->started[0]['callback_url'])->toBe('https://example.com/odeme/callback/akode');
});

it('refuses to start a charge if the configured callback URL is invalid', function () {
    $customer = Customer::factory()->ready()->create();
    $invoice = Invoice::factory()->for($customer)->create(['status' => InvoiceStatus::Unpaid]);
    $gateway = new FakeToslaGateway;
    $this->app->instance(ToslaGateway::class, $gateway);
    config()->set('services.akode.callback_url', 'not-a-url');

    $response = $this->actingAsCustomer($customer)
        ->post(route('customer.invoices.pay', $invoice->uuid));

    $response->assertSessionHas('error');
    expect($gateway->started)->toBeEmpty();
    $this->assertDatabaseCount('payments', 0);
});

it('does not create a second gateway session while the invoice start lock is held', function () {
    $customer = Customer::factory()->ready()->create();
    $invoice = Invoice::factory()->for($customer)->create(['status' => InvoiceStatus::Unpaid]);
    $gateway = new FakeToslaGateway;
    $this->app->instance(ToslaGateway::class, $gateway);
    $lock = Cache::lock('akode.invoice-start.'.$invoice->id, 30);
    expect($lock->get())->toBeTrue();

    try {
        $response = $this->actingAsCustomer($customer)
            ->post(route('customer.invoices.pay', $invoice->uuid));
    } finally {
        $lock->release();
    }

    $response->assertSessionHas('error');
    expect($gateway->started)->toBeEmpty();
    $this->assertDatabaseCount('payments', 0);
});

it('requires a configured public callback before starting a production charge', function () {
    $customer = Customer::factory()->ready()->create();
    $invoice = Invoice::factory()->for($customer)->create(['status' => InvoiceStatus::Unpaid]);
    $gateway = new FakeToslaGateway;
    $this->app->instance(ToslaGateway::class, $gateway);
    $this->app['env'] = 'production';
    config()->set('services.akode.callback_url', null);

    expect(fn () => app(StartInvoicePayment::class)->handle($invoice))
        ->toThrow(ToslaException::class, 'Ödeme dönüş adresi yapılandırılmadı.');
    expect($gateway->started)->toBeEmpty();
    $this->assertDatabaseCount('payments', 0);
});

it('does not start a gateway payment for an invalid invoice amount, currency or remote ID', function (array $changes) {
    $customer = Customer::factory()->ready()->create();
    $invoice = Invoice::factory()->for($customer)->create([
        'status' => InvoiceStatus::Unpaid,
        ...$changes,
    ]);
    $gateway = new FakeToslaGateway;
    $this->app->instance(ToslaGateway::class, $gateway);

    $response = $this->actingAsCustomer($customer)
        ->post(route('customer.invoices.pay', $invoice->uuid));

    $response->assertSessionHas('error');
    expect($gateway->started)->toBeEmpty();
    $this->assertDatabaseCount('payments', 0);
})->with([
    'zero amount' => [['total' => 0]],
    'wrong currency' => [['currency' => 'USD']],
    'missing remote invoice' => [['cari_plus_invoice_id' => null]],
]);
