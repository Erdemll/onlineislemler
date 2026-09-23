<?php

use App\Services\Contracts\EncryptedContractDocumentStorage;
use Illuminate\Support\Facades\Storage;

it('stores contract documents encrypted and returns the original bytes', function () {
    Storage::fake('local');
    $storage = app(EncryptedContractDocumentStorage::class);
    $bytes = '%PDF-1.4 secure contract';

    $storage->put('contracts/test.pdf', $bytes);

    expect(Storage::disk('local')->get('contracts/test.pdf'))
        ->not->toStartWith('%PDF-')
        ->and($storage->get('contracts/test.pdf'))->toBe($bytes);
});

it('encrypts a legacy document in place idempotently', function () {
    Storage::fake('local');
    $storage = app(EncryptedContractDocumentStorage::class);
    $bytes = '%PDF-1.4 legacy contract';
    Storage::disk('local')->put('contracts/legacy.pdf', $bytes);

    $firstRun = $storage->encryptExisting('contracts/legacy.pdf', hash('sha256', $bytes));
    $secondRun = $storage->encryptExisting('contracts/legacy.pdf', hash('sha256', $bytes));

    expect($firstRun)->toBeTrue()
        ->and($secondRun)->toBeFalse()
        ->and($storage->get('contracts/legacy.pdf'))->toBe($bytes);
});

it('accepts a bare base64 encoded 32 byte document key', function () {
    Storage::fake('local');
    config()->set('services.contract_documents.key', base64_encode(str_repeat('b', 32)));
    $storage = app(EncryptedContractDocumentStorage::class);

    $storage->put('contracts/bare-base64.pdf', '%PDF-1.4 test');

    expect($storage->get('contracts/bare-base64.pdf'))->toBe('%PDF-1.4 test');
});
