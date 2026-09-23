<?php

use App\Exceptions\CariPlusException;
use App\Services\CariPlus\CariPlusClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function cacheCariPlusToken(string $token): string
{
    $key = 'cari_plus.access_token.encrypted.'.hash('sha256', implode('|', [
        (string) config('services.cari_plus.base_url'),
        (string) config('services.cari_plus.client_id'),
    ]));

    Cache::put($key, Crypt::encryptString($token), now()->addMinutes(10));

    return $key;
}

beforeEach(function () {
    config()->set('services.cari_plus', [
        'base_url' => 'https://api.cariplus.test',
        'client_id' => 'cp_live_test',
        'client_secret' => 'secret-test',
        'connect_timeout' => 3,
        'timeout' => 10,
    ]);

    Cache::clear();
    Http::preventStrayRequests();
});

it('creates an idempotent current account', function () {
    cacheCariPlusToken('cached-token');
    Http::fake([
        'api.cariplus.test/v1/current-accounts' => Http::response([
            'data' => ['id' => 701, 'code' => 'MUS000701'],
        ], 201),
    ]);

    $account = (new CariPlusClient)->createCurrentAccount([
        'type' => 'customer',
        'title' => 'Erdem Lale',
        'is_individual' => true,
    ], 'portal-customer-uuid');

    expect($account)->toMatchArray(['id' => 701, 'code' => 'MUS000701']);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.cariplus.test/v1/current-accounts'
        && $request->hasHeader('Idempotency-Key', 'portal-customer-uuid')
        && $request['type'] === 'customer'
        && $request['is_individual'] === true
    );
});

it('authenticates and sends an idempotent sales invoice request', function () {
    Http::fake([
        'api.cariplus.test/v1/auth/token' => Http::response([
            'data' => [
                'access_token' => 'access-token',
                'refresh_token' => 'refresh-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ],
        ]),
        'api.cariplus.test/v1/sales-invoices' => Http::response([
            'data' => [
                'id' => 902,
                'invoice_number' => 'FTR-2026-0043',
                'status' => 'draft',
            ],
        ], 201),
    ]);
    $payload = [
        'current_account_id' => 55,
        'invoice_date' => '2026-09-16',
        'items' => [[
            'description' => 'Hızlı İnternet',
            'quantity' => 1,
            'unit_price' => 899.90,
        ]],
    ];

    $result = (new CariPlusClient)->createSalesInvoice($payload, 'portal-test-1');

    expect($result['id'])->toBe(902);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.cariplus.test/v1/auth/token'
        && $request['client_id'] === 'cp_live_test'
        && $request['client_secret'] === 'secret-test'
    );
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.cariplus.test/v1/sales-invoices'
        && $request->hasHeader('Authorization', 'Bearer access-token')
        && $request->hasHeader('Idempotency-Key', 'portal-test-1')
        && $request['current_account_id'] === 55
    );
});

it('uses the cached access token for following requests', function () {
    $cacheKey = cacheCariPlusToken('cached-token');
    Http::fake([
        'api.cariplus.test/v1/sales-invoices/902/issue' => Http::response([
            'data' => ['id' => 902, 'status' => 'issued'],
        ]),
    ]);

    $result = (new CariPlusClient)->issueSalesInvoice(902, 'portal-test-1-issue');

    expect($result['status'])->toBe('issued');
    expect(Cache::get($cacheKey))->not->toBe('cached-token');
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer cached-token')
        && $request->hasHeader('Idempotency-Key', 'portal-test-1-issue')
    );
});

it('reads a single sales invoice with its line items', function () {
    cacheCariPlusToken('cached-token');
    Http::fake([
        'api.cariplus.test/v1/sales-invoices/902' => Http::response([
            'data' => [
                'id' => 902,
                'current_account' => ['id' => 55],
                'items' => [['description' => 'Kurulum hizmeti']],
            ],
        ]),
    ]);

    $invoice = (new CariPlusClient)->getSalesInvoice(902);

    expect($invoice['items'][0]['description'])->toBe('Kurulum hizmeti');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === 'https://api.cariplus.test/v1/sales-invoices/902'
        && $request->hasHeader('Authorization', 'Bearer cached-token'));
});

it('refreshes an expired access token once after a 401 response', function () {
    cacheCariPlusToken('expired-token');
    Http::fake([
        'api.cariplus.test/v1/auth/token' => Http::response([
            'data' => [
                'access_token' => 'fresh-token',
                'expires_in' => 3600,
            ],
        ]),
        'api.cariplus.test/v1/products*' => Http::sequence()
            ->push(['error' => ['code' => 'unauthorized']], 401)
            ->push(['data' => [], 'meta' => ['total_pages' => 1]], 200),
    ]);

    $result = (new CariPlusClient)->listProducts();

    expect($result['data'])->toBe([]);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.cariplus.test/v1/auth/token');
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer fresh-token'));
});

it('resolves a current account by its exact code', function () {
    cacheCariPlusToken('cached-token');
    Http::fake([
        'api.cariplus.test/v1/current-accounts*' => Http::response([
            'data' => [[
                'id' => 501,
                'code' => 'MUS000001',
                'type' => 'customer',
            ]],
            'meta' => ['page' => 1, 'total_pages' => 1],
        ]),
    ]);

    $id = (new CariPlusClient)->findCurrentAccountIdByCode('MUS000001');

    expect($id)->toBe(501);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.cariplus.test/v1/current-accounts?code=MUS000001&per_page=1'
        && $request->hasHeader('Authorization', 'Bearer cached-token')
    );
});

it('lists active and archived product pages with the documented page size', function () {
    cacheCariPlusToken('cached-token');
    Http::fake([
        'api.cariplus.test/v1/products*' => Http::response([
            'data' => [[
                'id' => 501,
                'name' => 'Statik IP',
            ]],
            'meta' => ['page' => 2, 'total_pages' => 3],
        ]),
    ]);

    $result = (new CariPlusClient)->listProducts(2, true);

    expect($result['data'][0]['id'])->toBe(501)
        ->and($result['meta']['total_pages'])->toBe(3);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.cariplus.test/v1/products?page=2&per_page=200&archived=true&sort=created_at'
        && $request->hasHeader('Authorization', 'Bearer cached-token')
    );
});

it('creates an idempotent stockless product for a billable service', function () {
    cacheCariPlusToken('cached-token');
    Http::fake([
        'api.cariplus.test/v1/products' => Http::response([
            'data' => [
                'id' => 30112,
                'sku' => 'OI-HIZMET-000003',
                'name' => 'Statik IP',
            ],
        ], 201),
    ]);

    $product = (new CariPlusClient)->createProduct([
        'name' => 'Statik IP',
        'sku' => 'OI-HIZMET-000003',
        'track_stock' => false,
    ], 'portal-product-3');

    expect($product['id'])->toBe(30112);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.cariplus.test/v1/products'
        && $request->hasHeader('Idempotency-Key', 'portal-product-3')
        && $request['track_stock'] === false
    );
});

it('converts Cari Plus error responses to a domain exception', function () {
    cacheCariPlusToken('cached-token');
    Http::fake([
        'api.cariplus.test/v1/sales-invoices' => Http::response([
            'error' => [
                'code' => 'validation_failed',
                'message' => 'Gönderilen veri geçersiz.',
            ],
        ], 422),
    ]);

    $call = fn () => (new CariPlusClient)->createSalesInvoice([], 'portal-test-2');

    expect($call)->toThrow(CariPlusException::class, 'Cari Plus gönderilen veriyi kabul etmedi.');
});

it('rejects a successful response without a positive resource id', function () {
    cacheCariPlusToken('cached-token');
    Http::fake([
        'api.cariplus.test/v1/sales-invoices' => Http::response([
            'data' => [],
        ], 201),
    ]);

    $call = fn () => (new CariPlusClient)->createSalesInvoice([], 'portal-test-invalid');

    expect($call)->toThrow(CariPlusException::class, 'Cari Plus beklenmeyen bir yanıt döndürdü.');
});

it('rejects a successful list response with an invalid shape', function () {
    cacheCariPlusToken('cached-token');
    Http::fake([
        'api.cariplus.test/v1/products*' => Http::response([
            'data' => 'invalid',
            'meta' => [],
        ]),
    ]);

    $call = fn () => (new CariPlusClient)->listProducts();

    expect($call)->toThrow(CariPlusException::class, 'Cari Plus beklenmeyen bir liste yanıtı döndürdü.');
});
