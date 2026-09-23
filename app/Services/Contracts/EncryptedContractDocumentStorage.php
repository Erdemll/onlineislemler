<?php

namespace App\Services\Contracts;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class EncryptedContractDocumentStorage
{
    private const Prefix = 'contract-document:v1:';

    public function put(string $path, string $bytes): void
    {
        $encrypted = self::Prefix.$this->encrypter()->encryptString(base64_encode($bytes));

        if (! Storage::disk('local')->put($path, $encrypted)) {
            throw new RuntimeException('Sözleşme belgesi güvenli depolamaya yazılamadı.');
        }
    }

    public function get(string $path): string
    {
        if (! Storage::disk('local')->exists($path)) {
            throw new RuntimeException('Sözleşme belgesi bulunamadı.');
        }

        $stored = Storage::disk('local')->get($path);

        if (! str_starts_with($stored, self::Prefix)) {
            if (config('services.contract_documents.allow_legacy_plaintext', false)
                && str_starts_with($stored, '%PDF-')) {
                return $stored;
            }

            throw new RuntimeException('Sözleşme belgesi şifreli biçimde saklanmıyor.');
        }

        try {
            $decoded = base64_decode(
                $this->encrypter()->decryptString(substr($stored, strlen(self::Prefix))),
                true,
            );
        } catch (DecryptException $exception) {
            throw new RuntimeException('Sözleşme belgesinin şifresi çözülemedi.', previous: $exception);
        }

        if ($decoded === false) {
            throw new RuntimeException('Sözleşme belgesinin şifresi çözülemedi.');
        }

        return $decoded;
    }

    public function delete(string $path): void
    {
        Storage::disk('local')->delete($path);
    }

    public function encryptExisting(string $path, string $expectedHash): bool
    {
        $stored = Storage::disk('local')->get($path);

        if (str_starts_with($stored, self::Prefix)) {
            $bytes = $this->get($path);

            if (! hash_equals($expectedHash, hash('sha256', $bytes))) {
                throw new RuntimeException("Sözleşme belgesi bütünlük kontrolünü geçemedi: {$path}");
            }

            return false;
        }

        if (! hash_equals($expectedHash, hash('sha256', $stored))) {
            throw new RuntimeException("Sözleşme belgesi bütünlük kontrolünü geçemedi: {$path}");
        }

        $this->put($path, $stored);

        return true;
    }

    private function encrypter(): Encrypter
    {
        $configuredKey = config('services.contract_documents.key');

        if ((! is_string($configuredKey) || $configuredKey === '') && ! app()->isProduction()) {
            $configuredKey = config('app.key');
        }

        if (! is_string($configuredKey) || $configuredKey === '') {
            throw new RuntimeException('CONTRACT_DOCUMENT_KEY production ortamında tanımlanmalıdır.');
        }

        $encrypter = new Encrypter($this->decodedKey($configuredKey), 'AES-256-CBC');
        $previousKeys = collect(config('services.contract_documents.previous_keys', []))
            ->filter(fn (mixed $key): bool => is_string($key) && $key !== '')
            ->map(fn (string $key): string => $this->decodedKey($key))
            ->all();

        return $encrypter->previousKeys($previousKeys);
    }

    private function decodedKey(string $key): string
    {
        $key = trim($key);

        if (strlen($key) === 32) {
            return $key;
        }

        $encoded = str_starts_with($key, 'base64:') ? substr($key, 7) : $key;
        $decoded = base64_decode($encoded, true);

        if ($decoded === false || strlen($decoded) !== 32) {
            throw new RuntimeException('CONTRACT_DOCUMENT_KEY AES-256 için 32 byte veya 32 byte çözülen Base64 bir değer olmalıdır.');
        }

        return $decoded;
    }
}
