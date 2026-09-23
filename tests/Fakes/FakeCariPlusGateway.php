<?php

namespace Tests\Fakes;

use App\Contracts\CariPlusGateway;
use App\Exceptions\CariPlusException;

class FakeCariPlusGateway implements CariPlusGateway
{
    /** @var list<array{payload: array<string, mixed>, idempotency_key: string}> */
    public array $createdCurrentAccounts = [];

    public ?CariPlusException $currentAccountCreateException = null;

    /** @var array<string, mixed> */
    public array $currentAccountResponse = [
        'id' => 701,
        'code' => 'MUS000701',
    ];

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

    public ?CariPlusException $productListException = null;

    public ?int $currentAccountId = 55;

    /** @var array<string, mixed> */
    public array $salesInvoiceResponse = [
        'id' => 902,
        'invoice_number' => 'FTR-2026-0043',
        'status' => 'draft',
        'currency' => 'TRY',
        'collection_status' => 'to_collect',
    ];

    /** @var array<string, mixed>|null */
    public ?array $issuedInvoiceResponse = null;

    /** @var list<string> */
    public array $resolvedCodes = [];

    public ?int $productId = null;

    /** @var list<string> */
    public array $resolvedSkus = [];

    /** @var list<array{payload: array<string, mixed>, idempotency_key: string}> */
    public array $createdProducts = [];

    /** @var list<array{payload: array<string, mixed>, idempotency_key: string}> */
    public array $createdCollections = [];

    /** @var list<array<string, mixed>> */
    public array $companyAccounts = [
        ['id' => 1, 'name' => 'Merkez Kasa', 'type' => 'cash', 'currency' => 'TRY', 'is_active' => true],
        ['id' => 2, 'name' => 'Ziraat TL', 'type' => 'bank', 'currency' => 'TRY', 'is_active' => true],
    ];

    /** @var array<string, mixed> */
    public array $collectionResponse = [
        'id' => 4001,
        'type' => 'bank_transfer',
        'currency' => 'TRY',
    ];

    public ?CariPlusException $collectionException = null;

    /** @var list<array<string, mixed>> */
    public array $remoteProducts = [];

    /** @var list<array<string, mixed>> */
    public array $archivedProducts = [];

    /** @var list<array{page: int, archived: bool}> */
    public array $listedProducts = [];

    public function createCurrentAccount(array $payload, string $idempotencyKey): array
    {
        if ($this->currentAccountCreateException !== null) {
            throw $this->currentAccountCreateException;
        }

        $this->createdCurrentAccounts[] = [
            'payload' => $payload,
            'idempotency_key' => $idempotencyKey,
        ];

        return $this->currentAccountResponse;
    }

    public function createSalesInvoice(array $payload, string $idempotencyKey): array
    {
        if ($this->createException !== null) {
            throw $this->createException;
        }

        $this->created[] = [
            'payload' => $payload,
            'idempotency_key' => $idempotencyKey,
        ];

        $financials = $this->invoiceFinancials($payload);

        return [
            ...$financials,
            ...$this->salesInvoiceResponse,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
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

        $lastCreated = end($this->created);
        $financials = is_array($lastCreated)
            ? $this->invoiceFinancials($lastCreated['payload'])
            : ['subtotal' => 749.92, 'tax_amount' => 149.98, 'total' => 899.90];

        return $this->issuedInvoiceResponse ?? [
            'id' => $invoiceId,
            'invoice_number' => 'FTR-2026-0043',
            'status' => 'issued',
            'currency' => 'TRY',
            ...$financials,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'collection_status' => 'to_collect',
        ];
    }

    /** @param array<string, mixed> $payload
     * @return array{subtotal: float, tax_amount: float, total: float}
     */
    private function invoiceFinancials(array $payload): array
    {
        $item = $payload['items'][0];
        $gross = (float) $item['unit_price'] * (float) $item['quantity'];
        $taxRate = (float) ($item['tax_rate'] ?? 0);
        $subtotal = round($gross / (1 + $taxRate / 100), 2);

        return [
            'subtotal' => $subtotal,
            'tax_amount' => round($gross - $subtotal, 2),
            'total' => round($gross, 2),
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

    public function listProducts(int $page = 1, bool $archived = false): array
    {
        if ($this->productListException !== null) {
            throw $this->productListException;
        }

        $this->listedProducts[] = [
            'page' => $page,
            'archived' => $archived,
        ];

        return [
            'data' => $archived ? $this->archivedProducts : $this->remoteProducts,
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

    public function listCompanyAccounts(): array
    {
        return $this->companyAccounts;
    }

    public function createInvoiceCollection(array $payload, string $idempotencyKey): array
    {
        if ($this->collectionException !== null) {
            throw $this->collectionException;
        }

        $this->createdCollections[] = [
            'payload' => $payload,
            'idempotency_key' => $idempotencyKey,
        ];

        return [
            'sales_invoice_id' => $payload['sales_invoice_id'],
            'amount' => $payload['amount'],
            'company_account_id' => $payload['company_account_id'],
            ...$this->collectionResponse,
        ];
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }
}
