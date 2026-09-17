<?php

use App\Contracts\CariPlusGateway;
use App\Enums\ServiceOrderStatus;
use App\Exceptions\CariPlusException;
use App\Mail\ContractSigningCodeMail;
use App\Models\ContractAcceptance;
use App\Models\ContractVersion;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\ServiceOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\FakeCariPlusGateway;

uses(RefreshDatabase::class);

function contractSignatureData(): string
{
    $image = imagecreatetruecolor(400, 160);
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 15, 23, 42);
    imagefill($image, 0, 0, $white);
    imagesetthickness($image, 4);
    imageline($image, 30, 120, 130, 45, $black);
    imageline($image, 130, 45, 210, 125, $black);
    imageline($image, 210, 125, 355, 55, $black);
    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();
    imagedestroy($image);

    return 'data:image/png;base64,'.base64_encode($bytes);
}

function serviceWithRequiredContract(): Service
{
    $service = Service::factory()->create([
        'name' => 'Kamera Sistemleri',
        'cari_plus_product_id' => 141390,
        'price' => 2499.90,
    ]);
    $version = ContractVersion::factory()->create();
    $version->services()->attach($service, ['is_required' => true]);

    return $service;
}

beforeEach(function () {
    Storage::fake('local');
    Mail::fake();
    $this->gateway = new FakeCariPlusGateway;
    app()->instance(CariPlusGateway::class, $this->gateway);
});

it('creates an immutable acceptance PDF before issuing the invoice', function () {
    $customer = Customer::factory()->ready()->create([
        'email' => 'musteri@example.com',
        'phone' => '+905551112233',
    ]);
    $service = serviceWithRequiredContract();

    $purchaseResponse = $this->actingAsCustomer($customer)
        ->post(route('customer.services.purchase', $service));
    $order = ServiceOrder::query()->firstOrFail();
    $purchaseResponse->assertRedirect(route('customer.service-orders.contract.show', $order));

    $this->actingAsCustomer($customer)
        ->get(route('customer.service-orders.contract.show', $order))
        ->assertOk()
        ->assertSee('Sözleşmenin tamamı')
        ->assertSee('PDF’yi tam ekran aç')
        ->assertSee('aria-current="page" data-customer-navigation-active', false)
        ->assertSee('Onayla ve e-posta kodu gönder');

    $challengeResponse = $this->actingAsCustomer($customer)
        ->post(route('customer.service-orders.contract.challenge', $order), [
            'accepted' => '1',
            'signature_data' => contractSignatureData(),
        ]);
    $challengeResponse->assertSessionHasNoErrors();
    $challenge = $order->signingChallenges()->firstOrFail();
    $challengeResponse->assertRedirect(route('customer.service-orders.contract.show', [
        'serviceOrder' => $order,
        'challenge' => $challenge->uuid,
    ]));

    $code = null;
    Mail::assertSent(ContractSigningCodeMail::class, function (ContractSigningCodeMail $mail) use (&$code): bool {
        $code = $mail->code;

        return $mail->hasTo('musteri@example.com');
    });

    $acceptResponse = $this->actingAsCustomer($customer)
        ->post(route('customer.service-orders.contract.accept', $order), [
            'challenge_uuid' => $challenge->uuid,
            'code' => $code,
        ]);
    $acceptance = ContractAcceptance::query()->firstOrFail();
    $invoice = Invoice::query()->firstOrFail();

    $acceptResponse->assertRedirect(route('customer.invoices.index'));
    expect($order->fresh()->status)->toBe(ServiceOrderStatus::Invoiced)
        ->and($acceptance->source_document_hash)->toBe($order->contractVersion->source_document_hash)
        ->and($acceptance->acceptance_method->value)->toBe('email_otp')
        ->and($acceptance->ip_address)->toBe('127.0.0.1')
        ->and($invoice->service_order_id)->toBe($order->id)
        ->and($this->gateway->created)->toHaveCount(1);
    Storage::disk('local')->assertExists($acceptance->document_path);
    expect(hash('sha256', Storage::disk('local')->get($acceptance->document_path)))
        ->toBe($acceptance->signed_document_hash);

    $events = $order->events()->orderBy('sequence')->get();
    expect($events->pluck('event_type')->all())->toBe([
        'service_order_created', 'otp_sent', 'otp_verified', 'contract_accepted', 'invoice_created',
    ])->and($events[1]->previous_hash)->toBe($events[0]->event_hash);

    $this->actingAsCustomer($customer)
        ->post(route('customer.service-orders.contract.accept', $order), [
            'challenge_uuid' => $challenge->uuid,
            'code' => $code,
        ])
        ->assertRedirect(route('customer.invoices.index'));
    $this->assertDatabaseCount('contract_acceptances', 1);
    $this->assertDatabaseCount('invoices', 1);
    expect($order->events()->where('event_type', 'invoice_created')->count())->toBe(1);

    $otherCustomer = Customer::factory()->ready()->create();
    $this->actingAsCustomer($otherCustomer)
        ->get(route('customer.contracts.download', $acceptance))
        ->assertNotFound();
});

it('rejects an invalid OTP without accepting the contract or creating an invoice', function () {
    $customer = Customer::factory()->ready()->create();
    $service = serviceWithRequiredContract();
    $this->actingAsCustomer($customer)->post(route('customer.services.purchase', $service));
    $order = ServiceOrder::query()->firstOrFail();
    $this->actingAsCustomer($customer)->post(route('customer.service-orders.contract.challenge', $order), [
        'accepted' => '1',
        'signature_data' => contractSignatureData(),
    ])->assertSessionHasNoErrors();
    $challenge = $order->signingChallenges()->firstOrFail();

    $response = $this->actingAsCustomer($customer)
        ->post(route('customer.service-orders.contract.accept', $order), [
            'challenge_uuid' => $challenge->uuid,
            'code' => '000000',
        ]);

    $response->assertSessionHasErrors('code');
    expect($challenge->fresh()->attempts)->toBe(1);
    $this->assertDatabaseCount('contract_acceptances', 0);
    $this->assertDatabaseCount('invoices', 0);
    expect($this->gateway->created)->toBeEmpty();
});

it('preserves the signed acceptance when Cari Plus invoice creation fails', function () {
    $customer = Customer::factory()->ready()->create();
    $service = serviceWithRequiredContract();
    $this->gateway->createException = new CariPlusException('Cari Plus geçici olarak kullanılamıyor.');
    $this->actingAsCustomer($customer)->post(route('customer.services.purchase', $service));
    $order = ServiceOrder::query()->firstOrFail();
    $this->actingAsCustomer($customer)->post(route('customer.service-orders.contract.challenge', $order), [
        'accepted' => '1',
        'signature_data' => contractSignatureData(),
    ]);
    $challenge = $order->signingChallenges()->firstOrFail();
    $code = null;
    Mail::assertSent(ContractSigningCodeMail::class, function (ContractSigningCodeMail $mail) use (&$code): bool {
        $code = $mail->code;

        return true;
    });

    $response = $this->actingAsCustomer($customer)
        ->post(route('customer.service-orders.contract.accept', $order), [
            'challenge_uuid' => $challenge->uuid,
            'code' => $code,
        ]);

    $response
        ->assertRedirect(route('customer.contracts.index'))
        ->assertSessionHas('error');
    $acceptance = ContractAcceptance::query()->firstOrFail();
    expect($order->fresh()->status)->toBe(ServiceOrderStatus::InvoiceFailed)
        ->and($order->fresh()->last_error)->toBe('Cari Plus geçici olarak kullanılamıyor.');
    $this->assertDatabaseHas('invoices', [
        'service_order_id' => $order->id,
        'status' => 'failed',
    ]);
    Storage::disk('local')->assertExists($acceptance->document_path);
    expect($order->events()->where('event_type', 'invoice_creation_failed')->exists())->toBeTrue();
});

it('requires corporate signers to confirm their authority', function () {
    $customer = Customer::factory()->corporate()->ready()->create();
    $service = serviceWithRequiredContract();
    $this->actingAsCustomer($customer)->post(route('customer.services.purchase', $service));
    $order = ServiceOrder::query()->firstOrFail();

    $this->actingAsCustomer($customer)
        ->post(route('customer.service-orders.contract.challenge', $order), [
            'accepted' => '1',
            'signature_data' => contractSignatureData(),
        ])
        ->assertSessionHasErrors('authority_confirmed');

    $this->assertDatabaseCount('contract_signing_challenges', 0);
    Mail::assertNothingSent();
});

it('stops signing when the published source PDF has been changed', function () {
    $customer = Customer::factory()->ready()->create();
    $service = serviceWithRequiredContract();
    $this->actingAsCustomer($customer)->post(route('customer.services.purchase', $service));
    $order = ServiceOrder::query()->with('contractVersion')->firstOrFail();
    Storage::disk('local')->put($order->contractVersion->source_document_path, 'changed-pdf-bytes');

    $this->actingAsCustomer($customer)
        ->post(route('customer.service-orders.contract.challenge', $order), [
            'accepted' => '1',
            'signature_data' => contractSignatureData(),
        ])
        ->assertSessionHas('error', 'Sözleşme dosyasının bütünlüğü doğrulanamadı.');

    $this->assertDatabaseCount('contract_signing_challenges', 0);
    Mail::assertNothingSent();
});

it('returns 404 for another customers contract and signed document', function () {
    $customer = Customer::factory()->ready()->create();
    $otherCustomer = Customer::factory()->ready()->create();
    $order = ServiceOrder::factory()->for($customer)->create();

    $this->actingAsCustomer($otherCustomer)
        ->get(route('customer.service-orders.contract.show', $order))
        ->assertNotFound();
});

it('does not start a purchase when the service has no published contract', function () {
    $customer = Customer::factory()->ready()->create();
    $service = Service::factory()->create(['cari_plus_product_id' => 141390]);

    $this->actingAsCustomer($customer)
        ->post(route('customer.services.purchase', $service))
        ->assertRedirect(route('customer.services.index'))
        ->assertSessionHas('error', 'Bu hizmet için yayımlanmış bir sözleşme bulunmuyor.');

    $this->assertDatabaseCount('service_orders', 0);
});
