<?php

use App\Contracts\CariPlusGateway;
use App\Contracts\ToslaGateway;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\ServiceOrderStatus;
use App\Exceptions\ToslaException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentCallback;
use App\Models\ServiceOrder;
use App\Services\Tosla\ToslaHash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeCariPlusGateway;
use Tests\Fakes\FakeToslaGateway;

uses(RefreshDatabase::class);

function validCallbackData(Payment $payment): array
{
    $data = [
        'ClientId' => config('services.akode.client_id'),
        'ApiUser' => config('services.akode.api_user'),
        'OrderId' => $payment->order_id,
        'MdStatus' => '1',
        'BankResponseCode' => '00',
        'BankResponseMessage' => 'Onaylandı',
        'RequestStatus' => '1',
        'ThreeDSessionId' => 'SESS-1',
        'TransactionId' => '2000000000054218',
        'Code' => '0',
        'Message' => 'Başarılı',
    ];

    $data['Hash'] = (new ToslaHash)->callbackHash($data);

    return $data;
}

beforeEach(function (): void {
    config()->set('services.akode.client_id', '1000000061');
    config()->set('services.akode.api_user', 'DGS_Api');
    config()->set('services.akode.api_pass', 'DGSApi123.123');
});

it('rejects a callback with an invalid hash without changing state', function () {
    $customer = Customer::factory()->ready()->create();
    $invoice = Invoice::factory()->for($customer)->create([
        'status' => InvoiceStatus::Unpaid,
        'total' => 1200,
    ]);
    $payment = Payment::factory()->for($customer)->for($invoice)->create([
        'amount_kurus' => 120000,
    ]);
    $this->app->instance(ToslaGateway::class, new FakeToslaGateway);

    $response = $this->post(route('payment.callback.akode'), [
        'OrderId' => $payment->order_id,
        'Hash' => 'gecersiz-hash',
    ]);

    $response->assertStatus(400);
    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid);
    $this->assertDatabaseHas('payment_callbacks', [
        'order_id' => $payment->order_id,
        'hash_valid' => false,
    ]);
});

it('records a failed 3D result without marking the invoice paid', function () {
    $customer = Customer::factory()->ready()->create();
    $invoice = Invoice::factory()->for($customer)->create([
        'status' => InvoiceStatus::Unpaid,
        'total' => 1200,
    ]);
    $payment = Payment::factory()->for($customer)->for($invoice)->create([
        'amount_kurus' => 120000,
    ]);
    $this->app->instance(ToslaGateway::class, new FakeToslaGateway);

    $data = validCallbackData($payment);
    $data['MdStatus'] = '2';
    $data['BankResponseCode'] = 'E01';
    $data['BankResponseMessage'] = 'Doğrulama başarısız';
    $data['Hash'] = (new ToslaHash)->callbackHash($data);

    $response = $this->post(route('payment.callback.akode'), $data);

    $response->assertOk();
    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid);
});

it('marks the payment and invoice paid and records the Cari Plus collection', function () {
    $customer = Customer::factory()->ready()->create();
    $serviceOrder = ServiceOrder::factory()->create([
        'customer_id' => $customer->id,
        'status' => ServiceOrderStatus::Invoiced,
    ]);
    $invoice = Invoice::factory()->for($customer)->create([
        'status' => InvoiceStatus::Unpaid,
        'total' => 1200,
        'service_order_id' => $serviceOrder->id,
        'cari_plus_invoice_id' => 902,
    ]);
    $payment = Payment::factory()->for($customer)->for($invoice)->create([
        'amount_kurus' => 120000,
    ]);
    $this->app->instance(ToslaGateway::class, new FakeToslaGateway);
    $cariPlus = new FakeCariPlusGateway;
    $this->app->instance(CariPlusGateway::class, $cariPlus);
    config()->set('services.cari_plus.collection_account_id', 2);

    $response = $this->post(route('payment.callback.akode'), validCallbackData($payment));

    $response->assertOk();
    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($payment->fresh()->paid_at)->not->toBeNull()
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($serviceOrder->fresh()->status)->toBe(ServiceOrderStatus::Paid);
    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'cari_plus_collection_id' => 4001,
        'cari_plus_collection_synced_at' => $payment->fresh()->cari_plus_collection_synced_at,
    ]);
    expect($cariPlus->createdCollections)->toHaveCount(1)
        ->and($cariPlus->createdCollections[0]['payload']['sales_invoice_id'])->toBe(902)
        ->and($cariPlus->createdCollections[0]['payload']['type'])->toBe('bank_transfer')
        ->and($cariPlus->createdCollections[0]['payload']['amount'])->toBe(1200.0)
        ->and($cariPlus->createdCollections[0]['payload']['company_account_id'])->toBe(2)
        ->and($cariPlus->createdCollections[0]['idempotency_key'])->toBe('tahsilat-'.$payment->uuid);
});

it('is idempotent when the callback is delivered twice', function () {
    $customer = Customer::factory()->ready()->create();
    $invoice = Invoice::factory()->for($customer)->create([
        'status' => InvoiceStatus::Unpaid,
        'total' => 1200,
        'cari_plus_invoice_id' => 902,
    ]);
    $payment = Payment::factory()->for($customer)->for($invoice)->create([
        'amount_kurus' => 120000,
    ]);
    $this->app->instance(ToslaGateway::class, new FakeToslaGateway);
    $cariPlus = new FakeCariPlusGateway;
    $this->app->instance(CariPlusGateway::class, $cariPlus);
    config()->set('services.cari_plus.collection_account_id', 2);

    $this->post(route('payment.callback.akode'), validCallbackData($payment))->assertOk();
    $this->post(route('payment.callback.akode'), validCallbackData($payment))->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($cariPlus->createdCollections)->toHaveCount(1);
    $this->assertDatabaseCount('payments', 1);
});

it('leaves payment pending when the inquiry amount differs from the payment', function () {
    $customer = Customer::factory()->ready()->create();
    $invoice = Invoice::factory()->for($customer)->create([
        'status' => InvoiceStatus::Unpaid,
        'total' => 1200,
    ]);
    $payment = Payment::factory()->for($customer)->for($invoice)->create([
        'amount_kurus' => 120000,
    ]);
    $gateway = new FakeToslaGateway;
    $gateway->inquiryResponse = [
        'order_id' => $payment->order_id,
        'transaction_id' => '2000000000054218',
        'request_status' => 1,
        'bank_response_code' => '00',
        'bank_response_message' => 'Onaylandı',
        'amount' => 125000,
        'currency' => 949,
    ];
    $this->app->instance(ToslaGateway::class, $gateway);

    $response = $this->post(route('payment.callback.akode'), validCallbackData($payment));

    $response->assertOk();
    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid);
});

it('accepts a Tosla callback signed with HashParameters and redirects to invoices', function () {
    $customer = Customer::factory()->ready()->create();
    $invoice = Invoice::factory()->for($customer)->create([
        'status' => InvoiceStatus::Unpaid,
        'total' => 1200,
        'cari_plus_invoice_id' => 902,
    ]);
    $payment = Payment::factory()->for($customer)->for($invoice)->create([
        'amount_kurus' => 120000,
    ]);
    $gateway = new FakeToslaGateway;
    $this->app->instance(ToslaGateway::class, $gateway);
    $cariPlus = new FakeCariPlusGateway;
    $this->app->instance(CariPlusGateway::class, $cariPlus);
    config()->set('services.cari_plus.collection_account_id', 2);
    $callback = [
        'HashParameters' => 'ClientId,ApiUser,OrderId,MdStatus,BankResponseCode,ThreeDSessionId,RequestStatus',
        'OrderId' => $payment->order_id,
        'MdStatus' => '1',
        'BankResponseCode' => '00',
        'ThreeDSessionId' => 'SESS-1',
        'RequestStatus' => '1',
    ];
    $callback['Hash'] = base64_encode(hash(
        'sha512',
        'DGSApi123.1231000000061DGS_Api'.$payment->order_id.'100SESS-11',
        true,
    ));

    $response = $this->post(route('payment.callback.akode'), $callback);

    $response->assertOk()->assertSee(route('customer.invoices.index'));
    expect($gateway->inquired)->toBe([$payment->order_id]);
    expect($cariPlus->createdCollections)->toHaveCount(1);
    $this->assertDatabaseHas('payment_callbacks', ['payment_id' => $payment->id, 'hash_valid' => true]);
    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid);
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

it('returns a safe invoice link when the callback hash is invalid', function () {
    $customer = Customer::factory()->ready()->create();
    $invoice = Invoice::factory()->for($customer)->create(['status' => InvoiceStatus::Unpaid]);
    $payment = Payment::factory()->for($customer)->for($invoice)->create();
    $gateway = new FakeToslaGateway;
    $this->app->instance(ToslaGateway::class, $gateway);

    $response = $this->post(route('payment.callback.akode'), [
        'OrderId' => $payment->order_id,
        'Hash' => 'invalid',
    ]);

    $response->assertStatus(400)->assertSee(route('customer.invoices.index'));
    expect($gateway->inquired)->toBeEmpty();
    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('keeps the payment pending when Tosla inquiry is temporarily unavailable', function () {
    $customer = Customer::factory()->ready()->create();
    $invoice = Invoice::factory()->for($customer)->create(['status' => InvoiceStatus::Unpaid]);
    $payment = Payment::factory()->for($customer)->for($invoice)->create();
    $gateway = new FakeToslaGateway;
    $gateway->inquiryException = new ToslaException('Inquiry unavailable');
    $this->app->instance(ToslaGateway::class, $gateway);

    $response = $this->post(route('payment.callback.akode'), validCallbackData($payment));

    $response->assertServiceUnavailable();
    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid);
});

it('records an oversized invalid callback without a database error or payment change', function () {
    $customer = Customer::factory()->ready()->create();
    $invoice = Invoice::factory()->for($customer)->create(['status' => InvoiceStatus::Unpaid]);
    $payment = Payment::factory()->for($customer)->for($invoice)->create();
    $gateway = new FakeToslaGateway;
    $this->app->instance(ToslaGateway::class, $gateway);

    $response = $this->post(route('payment.callback.akode'), [
        'OrderId' => str_repeat('X', 30),
        'TransactionId' => str_repeat('Y', 50),
        'BankResponseCode' => str_repeat('Z', 30),
        'Hash' => 'invalid',
    ]);

    $response->assertStatus(400);
    $this->assertDatabaseHas('payment_callbacks', [
        'order_id' => str_repeat('X', 20),
        'transaction_id' => str_repeat('Y', 20),
        'bank_response_code' => str_repeat('Z', 10),
        'hash_valid' => false,
    ]);
    expect($gateway->inquired)->toBeEmpty();
    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('rejects an excessively large public callback without writing it to the audit table', function () {
    $response = $this->postJson(route('payment.callback.akode'), [
        'Message' => str_repeat('X', 20000),
    ]);

    $response->assertStatus(413);
    $this->assertDatabaseCount('payment_callbacks', 0);
});

it('does not retain unexpected card numbers in a callback audit record', function () {
    $response = $this->post(route('payment.callback.akode'), [
        'OrderId' => 'UNKNOWN-ORDER',
        'Hash' => 'invalid',
        'CardNo' => '4111111111111111',
        'Cvv' => '123',
    ]);

    $response->assertStatus(400);
    $callback = PaymentCallback::query()->firstOrFail();
    expect($callback->payload)->not->toHaveKey('CardNo');
    expect($callback->payload)->not->toHaveKey('Cvv');
    expect($callback->payload['OrderId'])->toBe('UNKNOWN-ORDER');
});
