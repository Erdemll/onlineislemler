<?php

namespace App\Services\CariPlus;

use App\Contracts\CariPlusGateway;
use App\Exceptions\CariPlusException;
use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Throwable;

class CariPlusClient implements CariPlusGateway
{
    public function createCurrentAccount(array $payload, string $idempotencyKey): array
    {
        try {
            $response = $this->sendAuthorized(fn (PendingRequest $request): Response => $request
                ->withHeader('Idempotency-Key', $idempotencyKey)
                ->post('/current-accounts', $payload));
        } catch (ConnectionException $exception) {
            throw new CariPlusException('Cari Plus servisine şu anda ulaşılamıyor.', previous: $exception);
        }

        return $this->dataFrom($response, 201);
    }

    public function createSalesInvoice(array $payload, string $idempotencyKey): array
    {
        try {
            $response = $this->sendAuthorized(fn (PendingRequest $request): Response => $request
                ->withHeader('Idempotency-Key', $idempotencyKey)
                ->post('/sales-invoices', $payload));
        } catch (ConnectionException $exception) {
            throw new CariPlusException('Cari Plus servisine şu anda ulaşılamıyor.', previous: $exception);
        }

        return $this->dataFrom($response, 201);
    }

    public function issueSalesInvoice(int $invoiceId, string $idempotencyKey): array
    {
        try {
            $response = $this->sendAuthorized(fn (PendingRequest $request): Response => $request
                ->withHeader('Idempotency-Key', $idempotencyKey)
                ->post("/sales-invoices/{$invoiceId}/issue", []));
        } catch (ConnectionException $exception) {
            throw new CariPlusException('Cari Plus servisine şu anda ulaşılamıyor.', previous: $exception);
        }

        return $this->dataFrom($response, 200);
    }

    public function listSalesInvoices(int $currentAccountId, int $page = 1): array
    {
        try {
            $response = $this->sendAuthorized(fn (PendingRequest $request): Response => $request->get('/sales-invoices', [
                'current_account_id' => $currentAccountId,
                'page' => $page,
                'per_page' => 200,
                'sort' => '-invoice_date',
            ]));
        } catch (ConnectionException $exception) {
            throw new CariPlusException('Cari Plus servisine şu anda ulaşılamıyor.', previous: $exception);
        }

        return $this->listDataFrom($response);
    }

    public function listProducts(int $page = 1, bool $archived = false): array
    {
        try {
            $response = $this->sendAuthorized(fn (PendingRequest $request): Response => $request->get('/products', [
                'page' => $page,
                'per_page' => 200,
                'archived' => $archived ? 'true' : 'false',
                'sort' => 'created_at',
            ]));
        } catch (ConnectionException $exception) {
            throw new CariPlusException('Cari Plus servisine şu anda ulaşılamıyor.', previous: $exception);
        }

        return $this->listDataFrom($response);
    }

    public function findCurrentAccountIdByCode(string $code): ?int
    {
        try {
            $response = $this->sendAuthorized(fn (PendingRequest $request): Response => $request->get('/current-accounts', [
                'code' => $code,
                'per_page' => 1,
            ]));
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

        return is_int($id) && $id > 0 ? $id : null;
    }

    public function findProductIdBySku(string $sku): ?int
    {
        try {
            $response = $this->sendAuthorized(fn (PendingRequest $request): Response => $request->get('/products', [
                'sku' => $sku,
                'per_page' => 1,
            ]));
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

        return is_int($id) && $id > 0 ? $id : null;
    }

    public function createProduct(array $payload, string $idempotencyKey): array
    {
        try {
            $response = $this->sendAuthorized(fn (PendingRequest $request): Response => $request
                ->withHeader('Idempotency-Key', $idempotencyKey)
                ->post('/products', $payload));
        } catch (ConnectionException $exception) {
            throw new CariPlusException('Cari Plus servisine şu anda ulaşılamıyor.', previous: $exception);
        }

        return $this->dataFrom($response, 201);
    }

    public function listCompanyAccounts(): array
    {
        try {
            $response = $this->sendAuthorized(fn (PendingRequest $request): Response => $request->get('/company-accounts'));
        } catch (ConnectionException $exception) {
            throw new CariPlusException('Cari Plus servisine şu anda ulaşılamıyor.', previous: $exception);
        }

        $result = $this->listDataFrom($response);

        return $result['data'];
    }

    public function createInvoiceCollection(array $payload, string $idempotencyKey): array
    {
        try {
            $response = $this->sendAuthorized(fn (PendingRequest $request): Response => $request
                ->withHeader('Idempotency-Key', $idempotencyKey)
                ->post('/invoice-collections', $payload));
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

    /** @param Closure(PendingRequest): Response $send */
    private function sendAuthorized(Closure $send): Response
    {
        if (! $this->isConfigured()) {
            throw new CariPlusException('Cari Plus bağlantı bilgileri henüz tanımlanmadı.');
        }

        $token = $this->accessToken();
        $response = $send($this->request()->withToken($token));

        if ($response->status() !== 401) {
            return $response;
        }

        Cache::forget($this->tokenCacheKey());

        return $send($this->request()->withToken($this->accessToken()));
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.cari_plus.base_url'), '/').'/v1')
            ->acceptJson()
            ->connectTimeout((int) config('services.cari_plus.connect_timeout', 3))
            ->timeout((int) config('services.cari_plus.timeout', 10))
            ->retry(
                [200, 500],
                when: fn (Throwable $exception): bool => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException
                        && ($exception->response->status() === 429 || $exception->response->serverError())),
                throw: false,
            );
    }

    private function accessToken(): string
    {
        $cacheKey = $this->tokenCacheKey();
        $cachedToken = $this->cachedToken($cacheKey);

        if ($cachedToken !== null) {
            return $cachedToken;
        }

        try {
            return Cache::lock($cacheKey.'.lock', 10)->block(5, function () use ($cacheKey): string {
                $cachedToken = $this->cachedToken($cacheKey);

                if ($cachedToken !== null) {
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

                Cache::put(
                    $cacheKey,
                    Crypt::encryptString($token),
                    now()->addSeconds(max(1, $expiresIn - 60)),
                );

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

    private function tokenCacheKey(): string
    {
        return 'cari_plus.access_token.encrypted.'.hash('sha256', implode('|', [
            (string) config('services.cari_plus.base_url'),
            (string) config('services.cari_plus.client_id'),
        ]));
    }

    private function cachedToken(string $cacheKey): ?string
    {
        $encrypted = Cache::get($cacheKey);

        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            $token = Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            Cache::forget($cacheKey);

            return null;
        }

        return $token !== '' ? $token : null;
    }

    /** @return array<string, mixed> */
    private function dataFrom(Response $response, int $expectedStatus): array
    {
        if ($response->status() !== $expectedStatus) {
            throw $this->exceptionFrom($response);
        }

        $data = $response->json('data');

        if (! is_array($data)
            || ! is_int($data['id'] ?? null)
            || $data['id'] <= 0) {
            throw new CariPlusException('Cari Plus beklenmeyen bir yanıt döndürdü.');
        }

        return $data;
    }

    /** @return array{data: list<array<string, mixed>>, meta: array<string, mixed>} */
    private function listDataFrom(Response $response): array
    {
        if (! $response->successful()) {
            throw $this->exceptionFrom($response);
        }

        $data = $response->json('data');
        $meta = $response->json('meta', []);

        if (! is_array($data)
            || ! array_is_list($data)
            || collect($data)->contains(fn (mixed $item): bool => ! is_array($item))
            || ! is_array($meta)) {
            throw new CariPlusException('Cari Plus beklenmeyen bir liste yanıtı döndürdü.');
        }

        return ['data' => $data, 'meta' => $meta];
    }

    private function exceptionFrom(Response $response): CariPlusException
    {
        $code = $response->json('error.code');

        return new CariPlusException(
            match ($response->status()) {
                401, 403 => 'Cari Plus kimlik doğrulaması başarısız oldu.',
                422 => 'Cari Plus gönderilen veriyi kabul etmedi.',
                429 => 'Cari Plus istek sınırına ulaşıldı. Lütfen daha sonra tekrar deneyin.',
                default => 'Cari Plus işlemi tamamlanamadı.',
            },
            is_string($code) ? $code : null,
            $response->status(),
        );
    }
}
