<?php

namespace App\Services\Tosla;

use InvalidArgumentException;

class ToslaHash
{
    public function __construct(private readonly ?string $apiPass = null) {}

    public function requestHash(string $clientId, string $apiUser, string $rnd, string $timeSpan): string
    {
        return $this->hash([$this->apiPass(), $clientId, $apiUser, $rnd, $timeSpan]);
    }

    public function callbackHash(array $data): string
    {
        $parameters = $data['HashParameters'] ?? null;
        $keys = $parameters === null
            ? ['ClientId', 'ApiUser', 'OrderId', 'MdStatus', 'BankResponseCode', 'BankResponseMessage', 'RequestStatus']
            : (is_string($parameters) ? explode(',', $parameters) : []);

        if ($keys === [] || count($keys) > 32 || count($keys) !== count(array_unique($keys))) {
            throw new InvalidArgumentException('Geçersiz Aköde hash alanları.');
        }

        $parts = [$this->apiPass()];

        foreach ($keys as $key) {
            if (! preg_match('/^[A-Za-z][A-Za-z0-9]*$/D', $key)
                || in_array($key, ['Hash', 'HashParameters'], true)) {
                throw new InvalidArgumentException('Geçersiz Aköde hash alanı.');
            }

            $value = match ($key) {
                'ClientId' => config('services.akode.client_id'),
                'ApiUser' => config('services.akode.api_user'),
                default => $data[$key] ?? ($key === 'BankResponseMessage' ? '' : null),
            };

            if (! is_string($value) && ! is_int($value)) {
                throw new InvalidArgumentException('Aköde hash alanı eksik veya geçersiz.');
            }

            $parts[] = (string) $value;
        }

        return $this->hash($parts);
    }

    public function verifyCallback(array $data): bool
    {
        $receivedHash = $data['Hash'] ?? null;
        $clientId = (string) config('services.akode.client_id');
        $apiUser = (string) config('services.akode.api_user');

        if (! is_string($receivedHash) || $receivedHash === '' || $clientId === '' || $apiUser === ''
            || $this->apiPass() === ''
            || (isset($data['ClientId']) && $data['ClientId'] !== $clientId)
            || (isset($data['ApiUser']) && $data['ApiUser'] !== $apiUser)) {
            return false;
        }

        try {
            return hash_equals($this->callbackHash($data), $receivedHash);
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    private function apiPass(): string
    {
        if ($this->apiPass !== null && $this->apiPass !== '') {
            return $this->apiPass;
        }

        return (string) config('services.akode.api_pass');
    }

    /** @param list<string> $parts */
    private function hash(array $parts): string
    {
        return base64_encode(hash('sha512', implode('', $parts), true));
    }
}
