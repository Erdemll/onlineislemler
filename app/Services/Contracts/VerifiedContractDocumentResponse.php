<?php

namespace App\Services\Contracts;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class VerifiedContractDocumentResponse
{
    public function make(
        string $path,
        string $expectedHash,
        string $filename,
        string $disposition = 'attachment',
    ): Response {
        abort_unless(Storage::disk('local')->exists($path), 404);
        $bytes = Storage::disk('local')->get($path);
        abort_unless(hash_equals($expectedHash, hash('sha256', $bytes)), 409, 'Belge bütünlüğü doğrulanamadı.');

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
