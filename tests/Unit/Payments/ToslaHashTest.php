<?php

use App\Services\Tosla\ToslaHash;
use Tests\TestCase;

uses(TestCase::class);

it('generates the request hash as base64 SHA512 of apiPass plus credentials', function () {
    $hash = new ToslaHash('api-pass-123');

    $expected = base64_encode(hash('sha512', 'api-pass-123|client|user|rnd|20260922120000', true));

    expect($hash->requestHash('client', 'user', 'rnd', '20260922120000'))->not->toBe($expected);
});

it('orders request hash parts without separators', function () {
    $hash = new ToslaHash('api-pass-123');

    $expected = base64_encode(hash('sha512', 'api-pass-123clientuserrnd20260922120000', true));

    expect($hash->requestHash('client', 'user', 'rnd', '20260922120000'))->toBe($expected);
});

it('verifies the callback hash with the documented field order', function () {
    config()->set('services.akode.client_id', '1000000061');
    config()->set('services.akode.api_user', 'DGS_Api');
    $hash = new ToslaHash('DGSApi123.123');

    $callback = [
        'ClientId' => '1000000061',
        'ApiUser' => 'DGS_Api',
        'OrderId' => 'P-2',
        'MdStatus' => '1',
        'BankResponseCode' => '00',
        'BankResponseMessage' => 'Onaylandı',
        'RequestStatus' => '1',
        'Hash' => '',
    ];

    $expected = base64_encode(hash('sha512', 'DGSApi123.1231000000061DGS_ApiP-2100Onaylandı1', true));

    expect($hash->callbackHash($callback))->toBe($expected);
});

it('uses the provider-supplied hash parameter order and configured credentials', function () {
    config()->set('services.akode.client_id', '1000000061');
    config()->set('services.akode.api_user', 'DGS_Api');
    $hash = new ToslaHash('DGSApi123.123');
    $callback = [
        'HashParameters' => 'OrderId,ClientId,ApiUser,MdStatus,BankResponseCode,ThreeDSessionId,RequestStatus',
        'OrderId' => 'P-2',
        'MdStatus' => '1',
        'BankResponseCode' => '00',
        'ThreeDSessionId' => 'SESSION-1',
        'RequestStatus' => '1',
    ];
    $callback['Hash'] = base64_encode(hash('sha512', 'DGSApi123.123P-21000000061DGS_Api100SESSION-11', true));

    expect($hash->verifyCallback($callback))->toBeTrue();
});

it('supports an empty bank message when the provider omits that signed field', function () {
    config()->set('services.akode.client_id', '1000000061');
    config()->set('services.akode.api_user', 'DGS_Api');
    $hash = new ToslaHash('DGSApi123.123');
    $callback = [
        'HashParameters' => 'ClientId,ApiUser,OrderId,MdStatus,BankResponseCode,BankResponseMessage,RequestStatus',
        'OrderId' => 'P-2',
        'MdStatus' => '1',
        'BankResponseCode' => '00',
        'RequestStatus' => '1',
        'Hash' => base64_encode(hash('sha512', 'DGSApi123.1231000000061DGS_ApiP-21001', true)),
    ];

    expect($hash->verifyCallback($callback))->toBeTrue();
});

it('rejects incomplete and inconsistent callback hash parameters', function (array $changes) {
    config()->set('services.akode.client_id', '1000000061');
    config()->set('services.akode.api_user', 'DGS_Api');
    $hash = new ToslaHash('DGSApi123.123');
    $callback = [
        'HashParameters' => 'ClientId,ApiUser,OrderId',
        'OrderId' => 'P-2',
        'Hash' => base64_encode(hash('sha512', 'DGSApi123.1231000000061DGS_ApiP-2', true)),
        ...$changes,
    ];

    expect($hash->verifyCallback($callback))->toBeFalse();
})->with([
    'missing signed field' => [['HashParameters' => 'ClientId,ApiUser,OrderId,MissingField']],
    'duplicate signed field' => [['HashParameters' => 'ClientId,ApiUser,OrderId,OrderId']],
    'client mismatch' => [['ClientId' => 'another-client']],
    'unsigned hash' => [['Hash' => '']],
    'invalid signed field' => [['HashParameters' => 'Hash,OrderId']],
]);

it('falls back to the configured api pass when none is injected', function () {
    config()->set('services.akode.api_pass', 'env-pass');

    $hash = new ToslaHash;
    $expected = base64_encode(hash('sha512', 'env-passcuserrndts', true));

    expect($hash->requestHash('c', 'user', 'rnd', 'ts'))->toBe($expected);
});
