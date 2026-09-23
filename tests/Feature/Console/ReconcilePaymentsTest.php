<?php

use App\Contracts\CariPlusGateway;
use App\Contracts\ToslaGateway;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeCariPlusGateway;
use Tests\Fakes\FakeToslaGateway;

uses(RefreshDatabase::class);

it('recovers a recently cancelled payment after a verified successful inquiry', function () {
    $customer = Customer::factory()->ready()->create();
    $invoice = Invoice::factory()->for($customer)->create([
        'status' => InvoiceStatus::Unpaid,
        'total' => 1200,
        'cari_plus_invoice_id' => 902,
    ]);
    $payment = Payment::factory()->for($customer)->for($invoice)->create([
        'status' => PaymentStatus::Cancelled,
        'amount_kurus' => 120000,
        'created_at' => now()->subHours(2),
        'updated_at' => now()->subHours(2),
    ]);
    $gateway = new FakeToslaGateway;
    $gateway->inquiryResponse = [
        'order_id' => $payment->order_id,
        'transaction_id' => '2000000000054218',
        'request_status' => 1,
        'bank_response_code' => '00',
        'bank_response_message' => 'Onaylandı',
        'amount' => 120000,
        'currency' => 949,
    ];
    $cariPlus = new FakeCariPlusGateway;
    $this->app->instance(ToslaGateway::class, $gateway);
    $this->app->instance(CariPlusGateway::class, $cariPlus);
    config()->set('services.cari_plus.collection_account_id', 2);

    $this->artisan('payments:reconcile')->assertSuccessful();

    expect($gateway->inquired)->toBe([$payment->order_id]);
    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid);
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
    expect($cariPlus->createdCollections)->toHaveCount(1);
});

it('can manually reconcile an older cancelled order without creating another charge', function () {
    $customer = Customer::factory()->ready()->create();
    $invoice = Invoice::factory()->for($customer)->create([
        'status' => InvoiceStatus::Unpaid,
        'total' => 1200,
        'cari_plus_invoice_id' => 902,
    ]);
    $payment = Payment::factory()->for($customer)->for($invoice)->create([
        'status' => PaymentStatus::Cancelled,
        'amount_kurus' => 120000,
        'created_at' => now()->subDays(5),
        'updated_at' => now()->subDays(5),
    ]);
    $gateway = new FakeToslaGateway;
    $cariPlus = new FakeCariPlusGateway;
    $this->app->instance(ToslaGateway::class, $gateway);
    $this->app->instance(CariPlusGateway::class, $cariPlus);
    config()->set('services.cari_plus.collection_account_id', 2);

    $this->artisan('payments:reconcile')->assertSuccessful();
    expect($gateway->inquired)->toBeEmpty();
    $this->artisan('payments:reconcile', ['--order' => $payment->order_id])->assertSuccessful();

    expect($gateway->inquired)->toBe([$payment->order_id]);
    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid);
    expect($cariPlus->createdCollections)->toHaveCount(1);
});

it('refuses to settle an inquiry that does not match the local charge', function (array $changes) {
    $customer = Customer::factory()->ready()->create();
    $invoice = Invoice::factory()->for($customer)->create(['status' => InvoiceStatus::Unpaid]);
    $payment = Payment::factory()->for($customer)->for($invoice)->create([
        'status' => PaymentStatus::Cancelled,
        'amount_kurus' => 120000,
        'created_at' => now()->subHours(2),
    ]);
    $gateway = new FakeToslaGateway;
    $gateway->inquiryResponse = [
        'order_id' => $payment->order_id,
        'transaction_id' => '2000000000054218',
        'request_status' => 1,
        'bank_response_code' => '00',
        'bank_response_message' => 'Onaylandı',
        'amount' => 120000,
        'currency' => 949,
        ...$changes,
    ];
    $cariPlus = new FakeCariPlusGateway;
    $this->app->instance(ToslaGateway::class, $gateway);
    $this->app->instance(CariPlusGateway::class, $cariPlus);

    $this->artisan('payments:reconcile', ['--order' => $payment->order_id])->assertSuccessful();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Cancelled);
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid);
    expect($cariPlus->createdCollections)->toBeEmpty();
})->with([
    'wrong order' => [['order_id' => 'OTHER-ORDER']],
    'wrong amount' => [['amount' => 120001]],
    'wrong currency' => [['currency' => 840]],
    'declined bank response' => [['bank_response_code' => '05']],
]);

it('does not apply a second successful payment to an already paid invoice', function () {
    $customer = Customer::factory()->ready()->create();
    $invoice = Invoice::factory()->for($customer)->create(['status' => InvoiceStatus::Paid]);
    $payment = Payment::factory()->for($customer)->for($invoice)->create([
        'status' => PaymentStatus::Cancelled,
        'amount_kurus' => 120000,
    ]);
    $gateway = new FakeToslaGateway;
    $cariPlus = new FakeCariPlusGateway;
    $this->app->instance(ToslaGateway::class, $gateway);
    $this->app->instance(CariPlusGateway::class, $cariPlus);

    $this->artisan('payments:reconcile', ['--order' => $payment->order_id])->assertSuccessful();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Cancelled);
    expect($cariPlus->createdCollections)->toBeEmpty();
});

it('retries an unsent Cari Plus collection after account configuration is fixed', function () {
    $customer = Customer::factory()->ready()->create();
    $invoice = Invoice::factory()->for($customer)->create([
        'status' => InvoiceStatus::Paid,
        'total' => 1300,
        'cari_plus_invoice_id' => 902,
    ]);
    $payment = Payment::factory()->paid()->for($customer)->for($invoice)->create([
        'amount_kurus' => 120000,
        'cari_plus_collection_synced_at' => now()->subDay(),
    ]);
    $gateway = new FakeToslaGateway;
    $cariPlus = new FakeCariPlusGateway;
    $this->app->instance(ToslaGateway::class, $gateway);
    $this->app->instance(CariPlusGateway::class, $cariPlus);
    config()->set('services.cari_plus.collection_account_id', 0);

    $this->artisan('payments:reconcile')->assertSuccessful();
    expect($cariPlus->createdCollections)->toBeEmpty();
    expect($payment->fresh()->cari_plus_collection_id)->toBeNull();
    config()->set('services.cari_plus.collection_account_id', 2);
    $this->artisan('payments:reconcile')->assertSuccessful();
    $this->artisan('payments:reconcile')->assertSuccessful();

    expect($gateway->inquired)->toBeEmpty();
    expect($cariPlus->createdCollections)->toHaveCount(1);
    expect($cariPlus->createdCollections[0]['payload']['amount'])->toBe(1200.0);
    expect($cariPlus->createdCollections[0]['idempotency_key'])->toBe('tahsilat-'.$payment->uuid);
    expect($payment->fresh()->cari_plus_collection_id)->toBe(4001);
});

it('retries an unsent collection once the Cari Plus invoice ID is available', function () {
    $customer = Customer::factory()->ready()->create();
    $invoice = Invoice::factory()->for($customer)->create([
        'status' => InvoiceStatus::Paid,
        'total' => 1200,
        'cari_plus_invoice_id' => null,
    ]);
    $payment = Payment::factory()->paid()->for($customer)->for($invoice)->create([
        'amount_kurus' => 120000,
    ]);
    $gateway = new FakeToslaGateway;
    $cariPlus = new FakeCariPlusGateway;
    $this->app->instance(ToslaGateway::class, $gateway);
    $this->app->instance(CariPlusGateway::class, $cariPlus);
    config()->set('services.cari_plus.collection_account_id', 2);

    $this->artisan('payments:reconcile')->assertSuccessful();
    expect($payment->fresh()->cari_plus_collection_synced_at)->toBeNull();
    $invoice->update(['cari_plus_invoice_id' => 902]);
    $this->artisan('payments:reconcile')->assertSuccessful();

    expect($gateway->inquired)->toBeEmpty();
    expect($cariPlus->createdCollections)->toHaveCount(1);
    expect($payment->fresh()->cari_plus_collection_id)->toBe(4001);
});

it('retries a collection after midnight with the original idempotent request date', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-23 23:59:00', 'Europe/Istanbul'));
    $customer = Customer::factory()->ready()->create();
    $invoice = Invoice::factory()->for($customer)->create([
        'status' => InvoiceStatus::Paid,
        'total' => 1200,
        'cari_plus_invoice_id' => 902,
    ]);
    $payment = Payment::factory()->paid()->for($customer)->for($invoice)->create(['amount_kurus' => 120000]);
    $gateway = new FakeToslaGateway;
    $cariPlus = new FakeCariPlusGateway;
    $cariPlus->collectionResponse['amount'] = 1000;
    $this->app->instance(ToslaGateway::class, $gateway);
    $this->app->instance(CariPlusGateway::class, $cariPlus);
    config()->set('services.cari_plus.collection_account_id', 2);

    $this->artisan('payments:reconcile')->assertSuccessful();
    $this->travelTo(CarbonImmutable::parse('2026-09-24 00:10:00', 'Europe/Istanbul'));
    $cariPlus->collectionResponse['amount'] = 1200;
    $this->artisan('payments:reconcile')->assertSuccessful();

    expect($cariPlus->createdCollections)->toHaveCount(2);
    expect($cariPlus->createdCollections[0]['payload'])->toBe($cariPlus->createdCollections[1]['payload']);
    expect($cariPlus->createdCollections[1]['payload']['collection_date'])->toBe('2026-09-23');
    expect($cariPlus->createdCollections[0]['idempotency_key'])->toBe($cariPlus->createdCollections[1]['idempotency_key']);
    expect($payment->fresh()->cari_plus_collection_id)->toBe(4001);
});

it('keeps an inconsistent Cari Plus collection response unsynchronized', function (array $responseChanges) {
    $customer = Customer::factory()->ready()->create();
    $invoice = Invoice::factory()->for($customer)->create([
        'status' => InvoiceStatus::Paid,
        'total' => 1200,
        'cari_plus_invoice_id' => 902,
    ]);
    $payment = Payment::factory()->paid()->for($customer)->for($invoice)->create(['amount_kurus' => 120000]);
    $gateway = new FakeToslaGateway;
    $cariPlus = new FakeCariPlusGateway;
    $cariPlus->collectionResponse = [...$cariPlus->collectionResponse, ...$responseChanges];
    $this->app->instance(ToslaGateway::class, $gateway);
    $this->app->instance(CariPlusGateway::class, $cariPlus);
    config()->set('services.cari_plus.collection_account_id', 2);

    $this->artisan('payments:reconcile')->assertSuccessful();

    expect($cariPlus->createdCollections)->toHaveCount(1);
    expect($payment->fresh()->cari_plus_collection_id)->toBeNull();
    expect($payment->fresh()->cari_plus_collection_synced_at)->toBeNull();
})->with([
    'wrong invoice' => [['sales_invoice_id' => 901]],
    'partially applied amount' => [['amount' => 1199.99]],
    'wrong currency' => [['currency' => 'USD']],
    'wrong type' => [['type' => 'cash']],
    'wrong account' => [['company_account_id' => 1]],
]);

it('only syncs the selected collection when reconciling one order', function () {
    $customer = Customer::factory()->ready()->create();
    $firstInvoice = Invoice::factory()->for($customer)->create([
        'status' => InvoiceStatus::Paid,
        'cari_plus_invoice_id' => 902,
    ]);
    $otherInvoice = Invoice::factory()->for($customer)->create([
        'status' => InvoiceStatus::Paid,
        'cari_plus_invoice_id' => 903,
    ]);
    $firstPayment = Payment::factory()->paid()->for($customer)->for($firstInvoice)->create([
        'amount_kurus' => 120000,
    ]);
    $otherPayment = Payment::factory()->paid()->for($customer)->for($otherInvoice)->create([
        'amount_kurus' => 120000,
    ]);
    $gateway = new FakeToslaGateway;
    $cariPlus = new FakeCariPlusGateway;
    $this->app->instance(ToslaGateway::class, $gateway);
    $this->app->instance(CariPlusGateway::class, $cariPlus);
    config()->set('services.cari_plus.collection_account_id', 2);

    $this->artisan('payments:reconcile', ['--order' => $firstPayment->order_id])->assertSuccessful();

    expect($cariPlus->createdCollections)->toHaveCount(1);
    expect($cariPlus->createdCollections[0]['payload']['sales_invoice_id'])->toBe(902);
    expect($otherPayment->fresh()->cari_plus_collection_id)->toBeNull();
});
