<?php

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('reindexes customer identities with the current independent key', function () {
    $customer = Customer::factory()->create([
        'national_id' => '10000000146',
    ]);
    $customer->forceFill(['national_id_hash' => str_repeat('a', 64)])->save();

    $this->artisan('customers:reindex-identities')
        ->expectsOutputToContain('1 müşteri kimlik indeksi güncellendi.')
        ->assertSuccessful();

    expect($customer->fresh()->national_id_hash)->toBe(Customer::identityHash('10000000146'));
});
