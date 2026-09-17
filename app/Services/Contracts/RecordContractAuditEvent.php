<?php

namespace App\Services\Contracts;

use App\Models\ContractAcceptance;
use App\Models\ContractAcceptanceEvent;
use App\Models\ServiceOrder;
use Illuminate\Support\Facades\DB;
use JsonException;

class RecordContractAuditEvent
{
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
            $occurredAt = now();
            $sequence = ($previous?->sequence ?? 0) + 1;
            $payload = [
                'service_order_uuid' => $order->uuid,
                'customer_id' => $order->customer_id,
                'sequence' => $sequence,
                'event_type' => $eventType,
                'metadata' => $metadata,
                'previous_hash' => $previous?->event_hash,
                'occurred_at' => $occurredAt->toISOString(),
            ];

            return ContractAcceptanceEvent::query()->create([
                'service_order_id' => $order->id,
                'contract_acceptance_id' => $acceptance?->id,
                'customer_id' => $order->customer_id,
                'sequence' => $sequence,
                'event_type' => $eventType,
                'metadata' => $metadata,
                'previous_hash' => $previous?->event_hash,
                'event_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                'occurred_at' => $occurredAt,
            ]);
        });
    }
}
