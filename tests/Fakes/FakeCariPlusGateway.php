<?php

namespace Tests\Fakes;

use App\Contracts\CariPlusGateway;
use App\Exceptions\CariPlusException;

class FakeCariPlusGateway implements CariPlusGateway
{
    /** @var list<array{payload: array<string, mixed>, idempotency_key: string}> */
    public array $created = [];

    /** @var list<array{invoice_id: int, idempotency_key: string}> */
    public array $issued = [];

    /** @var list<array<string, mixed>> */
    public array $remoteInvoices = [];

    public bool $configured = true;

    public ?CariPlusException $createException = null;

    public ?CariPlusException $issueException = null;

    public ?CariPlusException $listException = null;

    public ?int $currentAccountId = 55;

    /** @var list<string> */
    public array $resolvedCodes = [];

    public ?int $productId = null;

    /** @var list<string> */
    public array $resolvedSkus = [];

    /** @var list<array{payload: array<string, mixed>, idempotency_key: string}> */
    public array $createdProducts = [];

    public function createSalesInvoice(array $payload, string $idempotencyKey): array
    {
        if ($this->createException !== null) {
            throw $this->createException;
        }

        $this->created[] = [
            'payload' => $payload,
            'idempotency_key' => $idempotencyKey,
        ];

        return [
            'id' => 902,
            'invoice_number' => 'FTR-2026-0043',
            'status' => 'draft',
            'currency' => 'TRY',
            'subtotal' => 749.92,
            'tax_amount' => 149.98,
            'total' => 899.90,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'collection_status' => 'to_collect',
        ];
    }

    public function issueSalesInvoice(int $invoiceId, string $idempotencyKey): array
    {
        if ($this->issueException !== null) {
            throw $this->issueException;
        }

        $this->issued[] = [
            'invoice_id' => $invoiceId,
            'idempotency_key' => $idempotencyKey,
        ];

        return [
            'id' => $invoiceId,
            'invoice_number' => 'FTR-2026-0043',
            'status' => 'issued',
            'currency' => 'TRY',
            'subtotal' => 749.92,
            'tax_amount' => 149.98,
            'total' => 899.90,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'collection_status' => 'to_collect',
        ];
    }

    public function listSalesInvoices(int $currentAccountId, int $page = 1): array
    {
        if ($this->listException !== null) {
            throw $this->listException;
        }

        return [
            'data' => $this->remoteInvoices,
            'meta' => ['page' => $page, 'total_pages' => 1],
        ];
    }

    public function findCurrentAccountIdByCode(string $code): ?int
    {
        $this->resolvedCodes[] = $code;

        return $this->currentAccountId;
    }

    public function findProductIdBySku(string $sku): ?int
    {
        $this->resolvedSkus[] = $sku;

        return $this->productId;
    }

    public function createProduct(array $payload, string $idempotencyKey): array
    {
        $this->createdProducts[] = [
            'payload' => $payload,
            'idempotency_key' => $idempotencyKey,
        ];

        return [
            'id' => 30112,
            ...$payload,
        ];
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }
}
