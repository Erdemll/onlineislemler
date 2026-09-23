<?php

use App\Exceptions\ToslaException;
use App\Services\Tosla\ToslaClient;
use App\Services\Tosla\ToslaHash;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('services.akode.base_url', 'https://prepentegrasyon.tosla.com/api/Payment/');
    config()->set('services.akode.client_id', 'client');
    config()->set('services.akode.api_user', 'user');
    config()->set('services.akode.api_pass', 'secret');
});

it('accepts an inquiry that identifies the order and amount explicitly', function () {
    Http::preventStrayRequests();
    Http::fake([
        'prepentegrasyon.tosla.com/api/Payment/inquiry' => Http::response([
            'Code' => 0,
            'OrderId' => 'OI123',
            'TransactionId' => '123',
            'RequestStatus' => 1,
            'BankResponseCode' => '00',
            'Amount' => 120000,
            'Currency' => 949,
        ]),
    ]);

    $result = (new ToslaClient(new ToslaHash))->inquiry('OI123');

    expect($result['order_id'])->toBe('OI123');
    expect($result['amount'])->toBe(120000);
    expect($result['currency'])->toBe(949);
    Http::assertSent(fn ($request): bool => $request->url() === 'https://prepentegrasyon.tosla.com/api/Payment/inquiry'
        && $request['OrderId'] === 'OI123');
});

it('rejects inquiry responses with missing or malformed financial fields', function (array $changes) {
    Http::preventStrayRequests();
    Http::fake([
        'prepentegrasyon.tosla.com/api/Payment/inquiry' => Http::response([
            'Code' => 0,
            'TransactionId' => '123',
            'RequestStatus' => 1,
            'BankResponseCode' => '00',
            'Amount' => 120000,
            'Currency' => 949,
            ...$changes,
        ]),
    ]);

    expect(fn () => (new ToslaClient(new ToslaHash))->inquiry('OI123'))
        ->toThrow(ToslaException::class);
})->with([
    'missing order' => [[]],
    'missing currency' => [['Currency' => null]],
    'fractional amount' => [['Amount' => 120000.5]],
    'string status' => [['RequestStatus' => '1']],
]);
