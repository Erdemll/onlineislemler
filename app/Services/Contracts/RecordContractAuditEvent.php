<?php

namespace App\Services\Contracts;

use App\Models\ContractAcceptance;
use App\Models\ContractAcceptanceEvent;
use App\Models\ServiceOrder;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;

class RecordContractAuditEvent
{
    private const HmacVersion = 'hmac-sha256-v2';

    private const LegacyVersion = 'sha256-v1';

    /**
     * @param  array<string, mixed>  $metadata
     *
     * @throws JsonException
     */
    public function record(
        ServiceOrder $order,
        string $eventType,
        array $metadata = [],
        ?ContractAcceptance $acceptance = null,
    ): ContractAcceptanceEvent {
        return DB::transaction(function () use ($order, $eventType, $metadata, $acceptance): ContractAcceptanceEvent {
            ServiceOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $previous = ContractAcceptanceEvent::query()
                ->whereBelongsTo($order)
                ->orderByDesc('sequence')
                ->lockForUpdate()
                ->first();
            $occurredAt = now()->startOfSecond();
            $sequence = ($previous?->sequence ?? 0) + 1;
            $acceptanceId = $acceptance?->id;
            $payload = $this->payload(
                version: self::HmacVersion,
                order: $order,
                acceptanceId: $acceptanceId,
                customerId: $order->customer_id,
                sequence: $sequence,
                eventType: $eventType,
                metadata: $metadata,
                previousHash: $previous?->event_hash,
                occurredAt: $occurredAt->toISOString(),
            );

            return ContractAcceptanceEvent::query()->create([
                'service_order_id' => $order->id,
                'contract_acceptance_id' => $acceptanceId,
                'customer_id' => $order->customer_id,
                'sequence' => $sequence,
                'event_type' => $eventType,
                'metadata' => $metadata,
                'previous_hash' => $previous?->event_hash,
                'event_hash' => hash_hmac(
                    'sha256',
                    $this->encode($payload),
                    $this->hmacKey(),
                ),
                'hash_version' => self::HmacVersion,
                'occurred_at' => $occurredAt,
            ]);
        });
    }

    public function verify(ServiceOrder $order): bool
    {
        $previousHash = null;
        $expectedSequence = 1;

        foreach ($order->events()->orderBy('sequence')->orderBy('id')->cursor() as $event) {
            if ($event->sequence !== $expectedSequence
                || $event->customer_id !== $order->customer_id
                || $event->previous_hash !== $previousHash) {
                return false;
            }

            $payload = $this->payload(
                version: $event->hash_version,
                order: $order,
                acceptanceId: $event->contract_acceptance_id,
                customerId: $event->customer_id,
                sequence: $event->sequence,
                eventType: $event->event_type,
                metadata: $event->metadata ?? [],
                previousHash: $event->previous_hash,
                occurredAt: $event->occurred_at->toISOString(),
            );
            $isValid = match ($event->hash_version) {
                self::LegacyVersion => preg_match('/\A[a-f0-9]{64}\z/', $event->event_hash) === 1,
                self::HmacVersion => hash_equals(
                    hash_hmac('sha256', $this->encode($payload), $this->hmacKey()),
                    $event->event_hash,
                ),
                default => false,
            };

            if (! $isValid) {
                return false;
            }

            $previousHash = $event->event_hash;
            $expectedSequence++;
        }

        return $expectedSequence > 1;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function payload(
        string $version,
        ServiceOrder $order,
        ?int $acceptanceId,
        int $customerId,
        int $sequence,
        string $eventType,
        array $metadata,
        ?string $previousHash,
        string $occurredAt,
    ): array {
        $payload = [
            'service_order_uuid' => $order->uuid,
            'customer_id' => $customerId,
            'sequence' => $sequence,
            'event_type' => $eventType,
            'metadata' => $metadata,
            'previous_hash' => $previousHash,
            'occurred_at' => $occurredAt,
        ];

        if ($version === self::HmacVersion) {
            $payload['contract_acceptance_id'] = $acceptanceId;
            $payload['hash_version'] = $version;
        }

        return $payload;
    }

    /** @param array<string, mixed> $payload */
    private function encode(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function hmacKey(): string
    {
        $key = config('services.contract_audit.hmac_key');

        if (is_string($key) && $key !== '') {
            return $key;
        }

        if (! app()->isProduction()) {
            return (string) config('app.key');
        }

        throw new RuntimeException('CONTRACT_AUDIT_HMAC_KEY production ortamında tanımlanmalıdır.');
    }
}
