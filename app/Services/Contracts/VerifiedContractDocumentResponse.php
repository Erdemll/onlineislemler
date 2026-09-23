<?php

namespace App\Services\Contracts;

use Illuminate\Http\Response;
use RuntimeException;

class VerifiedContractDocumentResponse
{
    public function __construct(private EncryptedContractDocumentStorage $documents) {}

    public function make(
        string $path,
        string $expectedHash,
        string $filename,
        string $disposition = 'attachment',
    ): Response {
        try {
            $bytes = $this->documents->get($path);
        } catch (RuntimeException $exception) {
            abort($exception->getMessage() === 'Sözleşme belgesi bulunamadı.' ? 404 : 409, $exception->getMessage());
        }
        abort_unless(hash_equals($expectedHash, hash('sha256', $bytes)), 409, 'Belge bütünlüğü doğrulanamadı.');

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
