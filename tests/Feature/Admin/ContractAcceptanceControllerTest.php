<?php

use App\Models\ContractAcceptance;
use App\Models\ContractAcceptanceEvent;
use App\Models\ContractSigningChallenge;
use App\Models\ContractVersion;
use App\Models\Customer;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->admin = User::factory()->admin()->create();
    $this->customer = Customer::factory()->ready()->create([
        'first_name' => 'Ayşe',
        'last_name' => 'Yılmaz',
        'email' => 'ayse@example.com',
    ]);
    $this->version = ContractVersion::factory()->create();
    $this->order = ServiceOrder::factory()
        ->for($this->customer)
        ->for($this->version, 'contractVersion')
        ->create(['service_name_snapshot' => 'Kamera Sistemi']);
    $this->challenge = ContractSigningChallenge::factory()
        ->for($this->order, 'serviceOrder')
        ->for($this->customer)
        ->create();
    $signedBytes = "%PDF-1.4\nsigned contract\n%%EOF";
    $documentPath = 'contracts/acceptances/signed.pdf';
    Storage::disk('local')->put($documentPath, $signedBytes);

    $this->acceptance = ContractAcceptance::factory()
        ->for($this->version, 'contractVersion')
        ->for($this->order, 'serviceOrder')
        ->for($this->customer)
        ->for($this->challenge, 'signingChallenge')
        ->create([
            'contract_name_snapshot' => 'Kamera Sistemleri Sözleşmesi',
            'signer_name_snapshot' => 'Ayşe Yılmaz',
            'email_snapshot' => 'ayse@example.com',
            'document_path' => $documentPath,
            'signed_document_hash' => hash('sha256', $signedBytes),
        ]);

    ContractAcceptanceEvent::factory()
        ->for($this->order, 'serviceOrder')
        ->for($this->customer)
        ->for($this->acceptance, 'acceptance')
        ->create([
            'event_type' => 'contract_accepted',
            'sequence' => 1,
        ]);
});

test('an administrator can search and inspect signed contract evidence', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.contract-acceptances.index', ['q' => 'Ayşe']))
        ->assertOk()
        ->assertSee('Kamera Sistemleri Sözleşmesi')
        ->assertSee('Ayşe Yılmaz');

    $this->actingAs($this->admin)
        ->get(route('admin.contract-acceptances.show', $this->acceptance))
        ->assertOk()
        ->assertSee($this->acceptance->signed_document_hash)
        ->assertSee('contract_accepted');
});

test('an administrator can download a hash verified signed pdf', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('admin.contract-acceptances.download', $this->acceptance));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect($response->headers->get('Cache-Control'))->toContain('private', 'no-store', 'max-age=0')
        ->and($response->getContent())->toBe("%PDF-1.4\nsigned contract\n%%EOF");
});

test('a modified signed document fails the integrity check', function () {
    Storage::disk('local')->put($this->acceptance->document_path, 'tampered');

    $this->actingAs($this->admin)
        ->get(route('admin.contract-acceptances.download', $this->acceptance))
        ->assertConflict();
});

test('signed contract records are restricted to administrators', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.contract-acceptances.show', $this->acceptance))
        ->assertForbidden();
});
