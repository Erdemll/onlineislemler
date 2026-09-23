<?php

namespace App\Contracts;

interface CariPlusGateway
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createCurrentAccount(array $payload, string $idempotencyKey): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createSalesInvoice(array $payload, string $idempotencyKey): array;

    /** @return array<string, mixed> */
    public function issueSalesInvoice(int $invoiceId, string $idempotencyKey): array;

    /** @return array{data: list<array<string, mixed>>, meta: array<string, mixed>} */
    public function listSalesInvoices(int $currentAccountId, int $page = 1): array;

    /** @return array{data: list<array<string, mixed>>, meta: array<string, mixed>} */
    public function listProducts(int $page = 1, bool $archived = false): array;

    public function findCurrentAccountIdByCode(string $code): ?int;

    public function findProductIdBySku(string $sku): ?int;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createProduct(array $payload, string $idempotencyKey): array;

    /** @return list<array<string, mixed>> */
    public function listCompanyAccounts(): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createInvoiceCollection(array $payload, string $idempotencyKey): array;

    public function isConfigured(): bool;
}
