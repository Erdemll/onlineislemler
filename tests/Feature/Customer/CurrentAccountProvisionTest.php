<?php

use App\Contracts\CariPlusGateway;
use App\Exceptions\CariPlusException;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeCariPlusGateway;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->gateway = new FakeCariPlusGateway;
    app()->instance(CariPlusGateway::class, $this->gateway);
});

it('keeps a verified customer on the setup screen when Cari Plus is unavailable', function () {
    $customer = Customer::factory()->emailVerified()->create();
    $this->gateway->currentAccountCreateException = new CariPlusException('Servis geçici olarak kapalı.');

    $this->actingAsCustomer($customer)
        ->post(route('customer.current-account.provision'))
        ->assertRedirect()
        ->assertSessionHasErrors('provision');

    expect($customer->fresh()->email_verified_at)->not->toBeNull()
        ->and($customer->fresh()->cari_plus_current_account_id)->toBeNull();

    $this->actingAsCustomer($customer->fresh())
        ->get(route('customer.email.verify'))
        ->assertOk()
        ->assertSee('Müşteri hesabınızı oluşturalım');
});

it('allows retrying current account provisioning', function () {
    $customer = Customer::factory()->emailVerified()->create();

    $this->actingAsCustomer($customer)
        ->post(route('customer.current-account.provision'))
        ->assertRedirect(route('customer.dashboard'));

    expect($customer->fresh()->cari_plus_current_account_id)->toBe(701)
        ->and($customer->fresh()->cari_plus_current_account_code)->toBe('MUS000701');
});

it('blocks online operations until the current account is ready', function () {
    $customer = Customer::factory()->emailVerified()->create();

    $this->actingAsCustomer($customer)
        ->get(route('customer.dashboard'))
        ->assertRedirect(route('customer.email.verify'));
});
