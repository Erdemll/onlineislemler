<?php

namespace App\Contracts;

interface ToslaGateway
{
    /**
     * @return array{three_d_session_id: string, transaction_id: ?string}
     */
    public function startThreeDPayment(
        string $orderId,
        int $amountKurus,
        string $callbackUrl,
        string $description,
        string $echo,
    ): array;

    /**
     * @return array{order_id: string, transaction_id: string, request_status: int, bank_response_code: string, bank_response_message: string, amount: int, currency: int}
     */
    public function inquiry(string $orderId): array;

    public function sharedPaymentUrl(string $threeDSessionId): string;

    public function isConfigured(): bool;
}
