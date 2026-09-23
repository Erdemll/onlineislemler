<?php

namespace App\Services\Tosla;

use App\Contracts\ToslaGateway;
use App\Exceptions\ToslaException;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class ToslaClient implements ToslaGateway
{
    public function __construct(private ToslaHash $hash) {}

    public function startThreeDPayment(
        string $orderId,
        int $amountKurus,
        string $callbackUrl,
        string $description,
        string $echo,
    ): array {
        try {
            $response = $this->request()->post('/threeDPayment', [
                ...$this->commonPayload(),
                'OrderId' => $orderId,
                'Amount' => $amountKurus,
                'Currency' => 949,
                'InstallmentCount' => 0,
                'CallbackUrl' => $callbackUrl,
                'Description' => $description,
                'Echo' => $echo,
            ]);
        } catch (ConnectionException $exception) {
            throw new ToslaException('Ödeme servisine şu anda ulaşılamıyor.', previous: $exception);
        }

        if (! $response->successful()) {
            throw $this->exceptionFrom($response);
        }

        $data = $response->json();

        if (! is_array($data) || (int) ($data['Code'] ?? -1) !== 0) {
            throw new ToslaException('Ödeme işlemi başlatılamadı.');
        }

        $sessionId = $data['ThreeDSessionId'] ?? null;

        if (! is_string($sessionId) || $sessionId === '') {
            throw new ToslaException('Ödeme servisi geçerli bir oturum döndürmedi.');
        }

        $transactionId = $data['TransactionId'] ?? null;

        return [
            'three_d_session_id' => $sessionId,
            'transaction_id' => is_string($transactionId) && $transactionId !== '' ? $transactionId : null,
        ];
    }

    public function inquiry(string $orderId): array
    {
        try {
            $response = $this->request()->post('/inquiry', [
                ...$this->commonPayload(),
                'OrderId' => $orderId,
            ]);
        } catch (ConnectionException $exception) {
            throw new ToslaException('Ödeme servisine şu anda ulaşılamıyor.', previous: $exception);
        }

        if (! $response->successful()) {
            throw $this->exceptionFrom($response);
        }

        $data = $response->json();

        if (! is_array($data) || (int) ($data['Code'] ?? -1) !== 0) {
            throw new ToslaException('Ödeme sorgulaması tamamlanamadı.');
        }

        $requestStatus = $data['RequestStatus'] ?? null;
        $amount = $data['Amount'] ?? null;
        $transactionId = (string) ($data['TransactionId'] ?? '');
        $orderId = $data['OrderId'] ?? null;
        $currency = $data['Currency'] ?? null;

        if ($transactionId === '' || ! is_int($amount) || $amount < 0
            || ! is_int($requestStatus) || ! is_string($orderId)
            || ! is_int($currency)) {
            throw new ToslaException('Ödeme servisi geçersiz bir sorgu yanıtı döndürdü.');
        }

        return [
            'order_id' => $orderId,
            'transaction_id' => $transactionId,
            'request_status' => $requestStatus,
            'bank_response_code' => (string) ($data['BankResponseCode'] ?? ''),
            'bank_response_message' => (string) ($data['BankResponseMessage'] ?? ''),
            'amount' => $amount,
            'currency' => $currency,
        ];
    }

    public function sharedPaymentUrl(string $threeDSessionId): string
    {
        return rtrim((string) config('services.akode.base_url'), '/').'/threeDSecure/'.$threeDSessionId;
    }

    public function isConfigured(): bool
    {
        return filled(config('services.akode.client_id'))
            && filled(config('services.akode.api_user'))
            && filled(config('services.akode.api_pass'));
    }

    /** @return array<string, string|int> */
    private function commonPayload(): array
    {
        $clientId = (string) config('services.akode.client_id');
        $apiUser = (string) config('services.akode.api_user');
        $rnd = Str::random(24);
        $timeSpan = now()->setTimezone('Europe/Istanbul')->format('YmdHis');

        return [
            'ClientId' => $clientId,
            'ApiUser' => $apiUser,
            'Rnd' => $rnd,
            'TimeSpan' => $timeSpan,
            'Hash' => $this->hash->requestHash($clientId, $apiUser, $rnd, $timeSpan),
        ];
    }

    /** @param Closure(PendingRequest): Response $send */
    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.akode.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout((int) config('services.akode.connect_timeout', 3))
            ->timeout((int) config('services.akode.timeout', 15))
            ->retry(
                [200, 500],
                when: fn (Throwable $exception): bool => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException
                        && ($exception->response->status() === 429 || $exception->response->serverError())),
                throw: false,
            );
    }

    private function exceptionFrom(Response $response): ToslaException
    {
        $data = $response->json();
        $code = is_array($data) ? (string) ($data['Code'] ?? '') : '';

        return new ToslaException(
            match ($response->status()) {
                401, 403 => 'Ödeme servisi kimlik doğrulaması başarısız oldu.',
                422 => 'Ödeme servisi gönderilen veriyi kabul etmedi.',
                429 => 'Ödeme servisi istek sınırına ulaşıldı. Lütfen daha sonra tekrar deneyin.',
                default => 'Ödeme işlemi tamamlanamadı.',
            },
            $code !== '' ? $code : null,
            $response->status(),
        );
    }
}
