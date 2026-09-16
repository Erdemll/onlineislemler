<?php

use App\Exceptions\CariPlusException;
use App\Services\CariPlus\CariPlusClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.cari_plus', [
        'base_url' => 'https://api.cariplus.test',
        'client_id' => 'cp_live_test',
        'client_secret' => 'secret-test',
        'connect_timeout' => 3,
        'timeout' => 10,
    ]);

    Cache::forget('cari_plus.access_token');
    Http::preventStrayRequests();
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
    Cache::put('cari_plus.access_token', 'cached-token', now()->addMinutes(10));
    Http::fake([
        'api.cariplus.test/v1/sales-invoices/902/issue' => Http::response([
            'data' => ['id' => 902, 'status' => 'issued'],
        ]),
    ]);

    $result = (new CariPlusClient)->issueSalesInvoice(902, 'portal-test-1-issue');

    expect($result['status'])->toBe('issued');
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer cached-token')
        && $request->hasHeader('Idempotency-Key', 'portal-test-1-issue')
    );
});

it('resolves a current account by its exact code', function () {
    Cache::put('cari_plus.access_token', 'cached-token', now()->addMinutes(10));
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

it('creates an idempotent stockless product for a billable service', function () {
    Cache::put('cari_plus.access_token', 'cached-token', now()->addMinutes(10));
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
    Cache::put('cari_plus.access_token', 'cached-token', now()->addMinutes(10));
    Http::fake([
        'api.cariplus.test/v1/sales-invoices' => Http::response([
            'error' => [
                'code' => 'validation_failed',
                'message' => 'Gönderilen veri geçersiz.',
            ],
        ], 422),
    ]);

    $call = fn () => (new CariPlusClient)->createSalesInvoice([], 'portal-test-2');

    expect($call)->toThrow(CariPlusException::class, 'Gönderilen veri geçersiz.');
});
