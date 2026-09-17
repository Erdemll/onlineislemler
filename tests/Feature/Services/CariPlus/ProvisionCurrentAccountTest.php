<?php

use App\Contracts\CariPlusGateway;
use App\Models\Customer;
use App\Services\CariPlus\ProvisionCurrentAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Fakes\FakeCariPlusGateway;

uses(RefreshDatabase::class);

it('provisions an individual current account with encrypted identity data', function () {
    $gateway = new FakeCariPlusGateway;
    app()->instance(CariPlusGateway::class, $gateway);
    $customer = Customer::factory()->emailVerified()->create([
        'first_name' => 'Erdem',
        'last_name' => 'Lale',
        'national_id' => '10000000146',
        'company_title' => 'Lale Teknoloji',
    ]);

    $id = app(ProvisionCurrentAccount::class)->handle($customer);

    expect($id)->toBe(701)
        ->and($customer->fresh()->cari_plus_current_account_code)->toBe('MUS000701')
        ->and($gateway->createdCurrentAccounts)->toHaveCount(1)
        ->and($gateway->createdCurrentAccounts[0]['idempotency_key'])->toBe('portal-customer-'.$customer->uuid)
        ->and($gateway->createdCurrentAccounts[0]['payload'])->toMatchArray([
            'type' => 'customer',
            'title' => 'Lale Teknoloji',
            'is_individual' => true,
            'tax_number' => '10000000146',
        ])
        ->and($gateway->createdCurrentAccounts[0]['payload']['address']['province_code'])->toBe('33')
        ->and($gateway->createdCurrentAccounts[0]['payload']['address']['country'])->toBe('TR');

    $raw = DB::table('customers')->where('id', $customer->id)->first();
    expect($raw->national_id)->not->toBe('10000000146')
        ->and($raw->national_id_hash)->toBe(Customer::identityHash('10000000146'));
});

it('maps corporate billing and contact fields to Cari Plus', function () {
    $gateway = new FakeCariPlusGateway;
    app()->instance(CariPlusGateway::class, $gateway);
    $customer = Customer::factory()->corporate()->emailVerified()->create([
        'first_name' => 'Ayşe',
        'last_name' => 'Yılmaz',
        'tax_number' => '1234567890',
        'company_title' => 'Örnek AŞ',
        'mobile_phone' => '+905551112233',
    ]);

    app(ProvisionCurrentAccount::class)->handle($customer);

    expect($gateway->createdCurrentAccounts[0]['payload'])->toMatchArray([
        'title' => 'Örnek AŞ',
        'is_individual' => false,
        'tax_number' => '1234567890',
        'tax_office' => 'Mersin Vergi Dairesi',
        'mobile' => '+905551112233',
    ])->and($gateway->createdCurrentAccounts[0]['payload']['contact_person'])->toMatchArray([
        'name' => 'Ayşe Yılmaz',
        'role' => 'Yetkili',
        'phone' => '+905551112233',
    ]);
});

it('does not create a duplicate remote account when already provisioned', function () {
    $gateway = new FakeCariPlusGateway;
    app()->instance(CariPlusGateway::class, $gateway);
    $customer = Customer::factory()->ready()->create();

    $id = app(ProvisionCurrentAccount::class)->handle($customer);

    expect($id)->toBe($customer->cari_plus_current_account_id)
        ->and($gateway->createdCurrentAccounts)->toBeEmpty();
});
