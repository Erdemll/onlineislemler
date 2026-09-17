<?php

use App\Models\ContractSigningChallenge;
use App\Models\ContractVersion;
use App\Models\Customer;
use App\Models\ServiceOrder;
use App\Services\Contracts\GenerateSignedContractPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;

uses(RefreshDatabase::class);

it('appends one evidence page without changing the source PDF', function () {
    Storage::fake('local');
    $customer = Customer::factory()->ready()->create();
    $version = ContractVersion::factory()->create();
    $order = ServiceOrder::factory()
        ->for($customer)
        ->for($version, 'contractVersion')
        ->create();
    $image = imagecreatetruecolor(400, 160);
    imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
    imageline($image, 20, 120, 360, 40, imagecolorallocate($image, 0, 0, 0));
    ob_start();
    imagepng($image);
    $signatureBytes = ob_get_clean();
    imagedestroy($image);
    $signaturePath = 'contracts/signatures/pdf-test.enc';
    Storage::disk('local')->put($signaturePath, Crypt::encryptString(base64_encode($signatureBytes)));
    $challenge = ContractSigningChallenge::factory()
        ->for($order)
        ->for($customer)
        ->create([
            'signature_path' => $signaturePath,
            'signature_hash' => hash('sha256', $signatureBytes),
            'source_document_hash' => $version->source_document_hash,
            'verified_at' => now(),
        ]);

    $result = app(GenerateSignedContractPdf::class)->generate(
        order: $order,
        customer: $customer,
        challenge: $challenge,
        acceptanceUuid: '019cdef0-5a75-7000-8000-000000000001',
        acceptedAt: now(),
    );

    Storage::disk('local')->assertExists($result['document_path']);
    $finalBytes = Storage::disk('local')->get($result['document_path']);
    expect(hash('sha256', $finalBytes))->toBe($result['signed_document_hash']);
    $reader = new Fpdi;
    expect($reader->setSourceFile(Storage::disk('local')->path($result['document_path'])))->toBe(5);
    expect(hash('sha256', Storage::disk('local')->get($version->source_document_path)))
        ->toBe($version->source_document_hash);
});
