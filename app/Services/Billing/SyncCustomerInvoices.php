<?php

namespace App\Services\Billing;

use App\Contracts\CariPlusGateway;
use App\Enums\InvoiceStatus;
use App\Exceptions\CariPlusException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\CariPlus\ResolveCurrentAccount;
use Illuminate\Support\Str;

class SyncCustomerInvoices
{
    public function __construct(
        private CariPlusGateway $cariPlus,
        private ResolveCurrentAccount $resolveCurrentAccount,
    ) {}

    public function handle(Customer $customer): int
    {
        $currentAccountId = $this->resolveCurrentAccount->for($customer);

        $synced = 0;
        $page = 1;

        do {
            $result = $this->cariPlus->listSalesInvoices(
                $currentAccountId,
                $page,
            );

            foreach ($result['data'] as $remote) {
                $this->upsert($customer, $remote);
                $synced++;
            }

            $totalPages = (int) ($result['meta']['total_pages'] ?? 1);
            $page++;
        } while ($page <= $totalPages);

        return $synced;
    }

    /** @param array<string, mixed> $remote */
    private function upsert(Customer $customer, array $remote): void
    {
        $remoteId = (int) $remote['id'];
        $invoice = Invoice::query()->firstOrNew([
            'cari_plus_invoice_id' => $remoteId,
        ]);

        if ($invoice->exists && $invoice->customer_id !== $customer->getKey()) {
            throw new CariPlusException('Cari Plus faturası farklı bir müşteriye bağlı görünüyor.');
        }

        if (! $invoice->exists) {
            $uuid = (string) Str::uuid();
            $invoice->uuid = $uuid;
            $invoice->customer()->associate($customer);
            $invoice->idempotency_key = 'sync-'.$uuid;
        }

        $invoice->fill([
            'invoice_number' => $remote['invoice_number'] ?? null,
            'status' => $this->statusFrom($remote),
            'collection_status' => $remote['collection_status'] ?? null,
            'currency' => $remote['currency'] ?? 'TRY',
            'subtotal' => $remote['subtotal'] ?? 0,
            'tax_amount' => $remote['tax_amount'] ?? 0,
            'total' => $remote['total'] ?? 0,
            'invoice_date' => $remote['invoice_date'] ?? null,
            'due_date' => $remote['due_date'] ?? null,
            'last_sync_error' => null,
            'synced_at' => now(),
        ])->save();
    }

    /** @param array<string, mixed> $remote */
    private function statusFrom(array $remote): InvoiceStatus
    {
        return match ($remote['status'] ?? null) {
            'draft' => InvoiceStatus::Draft,
            'paid' => InvoiceStatus::Paid,
            'cancelled' => InvoiceStatus::Cancelled,
            'partial_refund' => InvoiceStatus::PartialRefund,
            'refunded' => InvoiceStatus::Refunded,
            'issued' => ($remote['collection_status'] ?? null) === 'collected'
                ? InvoiceStatus::Paid
                : InvoiceStatus::Unpaid,
            default => InvoiceStatus::Pending,
        };
    }
}
