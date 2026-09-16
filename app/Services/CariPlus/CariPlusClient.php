<?php

namespace App\Services\CariPlus;

use App\Contracts\CariPlusGateway;
use App\Exceptions\CariPlusException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class CariPlusClient implements CariPlusGateway
{
    public function createSalesInvoice(array $payload, string $idempotencyKey): array
    {
        try {
            $response = $this->authorizedRequest()
                ->withHeader('Idempotency-Key', $idempotencyKey)
                ->post('/sales-invoices', $payload);
        } catch (ConnectionException $exception) {
            throw new CariPlusException('Cari Plus servisine şu anda ulaşılamıyor.', previous: $exception);
        }

        return $this->dataFrom($response, 201);
    }

    public function issueSalesInvoice(int $invoiceId, string $idempotencyKey): array
    {
        try {
            $response = $this->authorizedRequest()
                ->withHeader('Idempotency-Key', $idempotencyKey)
                ->post("/sales-invoices/{$invoiceId}/issue", []);
        } catch (ConnectionException $exception) {
            throw new CariPlusException('Cari Plus servisine şu anda ulaşılamıyor.', previous: $exception);
        }

        return $this->dataFrom($response, 200);
    }

    public function listSalesInvoices(int $currentAccountId, int $page = 1): array
    {
        try {
            $response = $this->authorizedRequest()->get('/sales-invoices', [
                'current_account_id' => $currentAccountId,
                'page' => $page,
                'per_page' => 200,
                'sort' => '-invoice_date',
            ]);
        } catch (ConnectionException $exception) {
            throw new CariPlusException('Cari Plus servisine şu anda ulaşılamıyor.', previous: $exception);
        }

        if (! $response->successful()) {
            throw $this->exceptionFrom($response);
        }

        return [
            'data' => $response->json('data', []),
            'meta' => $response->json('meta', []),
        ];
    }

    public function findCurrentAccountIdByCode(string $code): ?int
    {
        try {
            $response = $this->authorizedRequest()->get('/current-accounts', [
                'code' => $code,
                'per_page' => 1,
            ]);
        } catch (ConnectionException $exception) {
            throw new CariPlusException('Cari Plus servisine şu anda ulaşılamıyor.', previous: $exception);
        }

        if (! $response->successful()) {
            throw $this->exceptionFrom($response);
        }

        $account = $response->json('data.0');

        if (! is_array($account) || ($account['code'] ?? null) !== $code) {
            return null;
        }

        $id = $account['id'] ?? null;

        return is_int($id) ? $id : null;
    }

    public function findProductIdBySku(string $sku): ?int
    {
        try {
            $response = $this->authorizedRequest()->get('/products', [
                'sku' => $sku,
                'per_page' => 1,
            ]);
        } catch (ConnectionException $exception) {
            throw new CariPlusException('Cari Plus servisine şu anda ulaşılamıyor.', previous: $exception);
        }

        if (! $response->successful()) {
            throw $this->exceptionFrom($response);
        }

        $product = $response->json('data.0');

        if (! is_array($product) || ($product['sku'] ?? null) !== $sku) {
            return null;
        }

        $id = $product['id'] ?? null;

        return is_int($id) ? $id : null;
    }

    public function createProduct(array $payload, string $idempotencyKey): array
    {
        try {
            $response = $this->authorizedRequest()
                ->withHeader('Idempotency-Key', $idempotencyKey)
                ->post('/products', $payload);
        } catch (ConnectionException $exception) {
            throw new CariPlusException('Cari Plus servisine şu anda ulaşılamıyor.', previous: $exception);
        }

        return $this->dataFrom($response, 201);
    }

    public function isConfigured(): bool
    {
        return filled(config('services.cari_plus.client_id'))
            && filled(config('services.cari_plus.client_secret'));
    }

    private function authorizedRequest(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new CariPlusException('Cari Plus bağlantı bilgileri henüz tanımlanmadı.');
        }

        return $this->request()->withToken($this->accessToken());
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.cari_plus.base_url'), '/').'/v1')
            ->acceptJson()
            ->connectTimeout((int) config('services.cari_plus.connect_timeout', 3))
            ->timeout((int) config('services.cari_plus.timeout', 10));
    }

    private function accessToken(): string
    {
        $cacheKey = 'cari_plus.access_token';
        $cachedToken = Cache::get($cacheKey);

        if (is_string($cachedToken) && $cachedToken !== '') {
            return $cachedToken;
        }

        try {
            return Cache::lock($cacheKey.'.lock', 10)->block(5, function () use ($cacheKey): string {
                $cachedToken = Cache::get($cacheKey);

                if (is_string($cachedToken) && $cachedToken !== '') {
                    return $cachedToken;
                }

                $response = $this->request()
                    ->asForm()
                    ->post('/auth/token', [
                        'client_id' => config('services.cari_plus.client_id'),
                        'client_secret' => config('services.cari_plus.client_secret'),
                    ]);

                if (! $response->successful()) {
                    throw $this->exceptionFrom($response);
                }

                $token = $response->json('data.access_token');
                $expiresIn = (int) $response->json('data.expires_in', 3600);

                if (! is_string($token) || $token === '') {
                    throw new CariPlusException('Cari Plus geçerli bir erişim anahtarı döndürmedi.');
                }

                Cache::put($cacheKey, $token, now()->addSeconds(max(60, $expiresIn - 60)));

                return $token;
            });
        } catch (CariPlusException $exception) {
            throw $exception;
        } catch (ConnectionException) {
            throw new CariPlusException('Cari Plus servisine şu anda ulaşılamıyor.');
        } catch (Throwable $exception) {
            throw new CariPlusException('Cari Plus erişim anahtarı alınamadı.', previous: $exception);
        }
    }

    /** @return array<string, mixed> */
    private function dataFrom(Response $response, int $expectedStatus): array
    {
        if ($response->status() !== $expectedStatus) {
            throw $this->exceptionFrom($response);
        }

        $data = $response->json('data');

        if (! is_array($data)) {
            throw new CariPlusException('Cari Plus beklenmeyen bir yanıt döndürdü.');
        }

        return $data;
    }

    private function exceptionFrom(Response $response): CariPlusException
    {
        $message = $response->json('error.message');
        $code = $response->json('error.code');

        return new CariPlusException(
            is_string($message) && $message !== ''
                ? $message
                : 'Cari Plus işlemi tamamlanamadı.',
            is_string($code) ? $code : null,
            $response->status(),
        );
    }
}
