<?php

namespace Tests\Fakes;

use App\Contracts\ToslaGateway;
use App\Exceptions\ToslaException;

class FakeToslaGateway implements ToslaGateway
{
    public bool $configured = true;

    /** @var list<array{order_id: string, amount_kurus: int, callback_url: string, description: string, echo: string}> */
    public array $started = [];

    public ?ToslaException $startException = null;

    /** @var array{three_d_session_id: string, transaction_id: ?string} */
    public array $startResponse = [
        'three_d_session_id' => 'ACBB40D1C34314940B7CCAC126E06E898DCDD6F75E102422B923B37D5D3688F0C',
        'transaction_id' => '2000000000054218',
    ];

    public ?ToslaException $inquiryException = null;

    /** @var list<string> */
    public array $inquired = [];

    /** @var array<string, mixed> */
    public ?array $inquiryResponse = null;

    public function startThreeDPayment(
        string $orderId,
        int $amountKurus,
        string $callbackUrl,
        string $description,
        string $echo,
    ): array {
        if ($this->startException !== null) {
            throw $this->startException;
        }

        $this->started[] = [
            'order_id' => $orderId,
            'amount_kurus' => $amountKurus,
            'callback_url' => $callbackUrl,
            'description' => $description,
            'echo' => $echo,
        ];

        return $this->startResponse;
    }

    public function inquiry(string $orderId): array
    {
        if ($this->inquiryException !== null) {
            throw $this->inquiryException;
        }

        $this->inquired[] = $orderId;

        return $this->inquiryResponse ?? [
            'order_id' => $orderId,
            'transaction_id' => '2000000000054218',
            'request_status' => 1,
            'bank_response_code' => '00',
            'bank_response_message' => 'Onaylandı',
            'amount' => 120000,
            'currency' => 949,
        ];
    }

    public function sharedPaymentUrl(string $threeDSessionId): string
    {
        return 'https://prepentegrasyon.tosla.com/api/Payment/threeDSecure/'.$threeDSessionId;
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }
}
