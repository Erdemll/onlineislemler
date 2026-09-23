<?php

use App\Contracts\CariPlusGateway;
use App\Models\Customer;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeCariPlusGateway;

uses(RefreshDatabase::class);

it('groups the catalog by category and marks products above the credit limit as unreachable', function () {
    $customer = Customer::factory()->ready()->withCreditLimit(100_000)->create();
    $this->app->instance(CariPlusGateway::class, new FakeCariPlusGateway);

    $affordable = Service::factory()->create([
        'name' => 'Güvenlik Kamerası',
        'price' => 800,
        'price_includes_tax' => true,
        'category_id' => 7,
        'category_name' => 'Kamera Sistemleri',
        'cari_plus_product_id' => 30101,
    ]);
    $aboveLimit = Service::factory()->create([
        'name' => 'Akıllı Ev Paketi',
        'price' => 1200,
        'price_includes_tax' => true,
        'category_id' => 8,
        'category_name' => 'Akıllı Ev Sistemleri',
        'cari_plus_product_id' => 30102,
    ]);
    Service::factory()->create([
        'name' => 'Montaj Hizmeti',
        'price' => 400,
        'price_includes_tax' => true,
        'category_id' => null,
        'category_name' => null,
        'cari_plus_product_id' => 30103,
    ]);

    $response = $this
        ->actingAsCustomer($customer)
        ->get(route('customer.services.index'));

    $response
        ->assertOk()
        ->assertSeeText('Kamera Sistemleri')
        ->assertSeeText('Akıllı Ev Sistemleri')
        ->assertSeeText('Kategorisiz')
        ->assertSeeText($affordable->name)
        ->assertSeeText($aboveLimit->name)
        ->assertSeeText('Limitiniz yetmemektedir')
        ->assertSeeText('Limit yetersiz');
});

it('shows every product as above-limit when no credit limit is assigned', function () {
    $customer = Customer::factory()->ready()->create();
    $this->app->instance(CariPlusGateway::class, new FakeCariPlusGateway);

    Service::factory()->create([
        'name' => 'Temel Güvenlik Paketi',
        'price' => 500,
        'price_includes_tax' => true,
        'category_name' => 'Güvenlik',
        'cari_plus_product_id' => 30104,
    ]);

    $response = $this
        ->actingAsCustomer($customer)
        ->get(route('customer.services.index'));

    $response
        ->assertOk()
        ->assertSeeText('Temel Güvenlik Paketi')
        ->assertSeeText('Limitiniz yetmemektedir')
        ->assertSeeText('Limit yetersiz');
});
