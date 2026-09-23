<?php

namespace App\Services\Billing;

use App\Contracts\CariPlusGateway;
use App\Enums\InvoiceStatus;
use App\Enums\ServiceOrderStatus;
use App\Exceptions\CariPlusException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\CariPlus\ResolveCurrentAccount;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
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

        $remoteInvoices = [];
        $page = 1;

        do {
            $result = $this->cariPlus->listSalesInvoices(
                $currentAccountId,
                $page,
            );

            foreach ($result['data'] as $remote) {
                $remoteInvoices[] = $this->validatedInvoice($remote);
            }

            $totalPages = $result['meta']['total_pages'] ?? 1;

            if (! is_int($totalPages) || $totalPages < 1 || $totalPages > 1000) {
                throw new CariPlusException('Cari Plus fatura sayfalama bilgisi geçersiz.');
            }

            $page++;
        } while ($page <= $totalPages);

        DB::transaction(function () use ($customer, $remoteInvoices): void {
            foreach ($remoteInvoices as $remote) {
                $this->upsert($customer, $remote);
            }
        });

        return count($remoteInvoices);
    }

    /** @param array<string, mixed> $remote */
    private function upsert(Customer $customer, array $remote): void
    {
        $remoteId = $remote['id'];
        $invoice = Invoice::query()
            ->where('cari_plus_invoice_id', $remoteId)
            ->lockForUpdate()
            ->first() ?? new Invoice(['cari_plus_invoice_id' => $remoteId]);

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
            'status' => $this->nextStatus($invoice->status, $remote['status']),
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

        if ($invoice->status === InvoiceStatus::Paid
            && $invoice->serviceOrder !== null
            && $invoice->serviceOrder->status !== ServiceOrderStatus::ServiceProvisioned) {
            $invoice->serviceOrder->update(['status' => ServiceOrderStatus::Paid]);
        }
    }

    private function nextStatus(?InvoiceStatus $current, InvoiceStatus $incoming): InvoiceStatus
    {
        if (in_array($current, [InvoiceStatus::Cancelled, InvoiceStatus::Refunded], true)) {
            return $current;
        }

        if ($current === InvoiceStatus::PartialRefund
            && ! in_array($incoming, [InvoiceStatus::Refunded, InvoiceStatus::Cancelled], true)) {
            return $current;
        }

        if ($current === InvoiceStatus::Paid
            && in_array($incoming, [InvoiceStatus::Pending, InvoiceStatus::Draft, InvoiceStatus::Unpaid], true)) {
            return $current;
        }

        return $incoming;
    }

    /** @param array<string, mixed> $remote
     * @return array<string, mixed>
     */
    private function validatedInvoice(array $remote): array
    {
        if (! is_int($remote['id'] ?? null) || $remote['id'] <= 0) {
            throw new CariPlusException('Cari Plus geçersiz bir fatura kimliği döndürdü.');
        }

        $status = match ($remote['status'] ?? null) {
            'draft' => InvoiceStatus::Draft,
            'paid' => InvoiceStatus::Paid,
            'cancelled' => InvoiceStatus::Cancelled,
            'partial_refund' => InvoiceStatus::PartialRefund,
            'refunded' => InvoiceStatus::Refunded,
            'issued' => InvoiceStatus::Unpaid,
            default => throw new CariPlusException('Cari Plus bilinmeyen bir fatura durumu döndürdü.'),
        };

        if (($remote['currency'] ?? null) !== 'TRY') {
            throw new CariPlusException('Cari Plus desteklenmeyen bir fatura para birimi döndürdü.');
        }

        foreach (['subtotal', 'tax_amount', 'total'] as $field) {
            if (! is_numeric($remote[$field] ?? null)
                || ! is_finite((float) $remote[$field])
                || (float) $remote[$field] < 0) {
                throw new CariPlusException("Cari Plus faturasındaki {$field} alanı geçersiz.");
            }

            $remote[$field] = round((float) $remote[$field], 2);
        }

        foreach (['invoice_date', 'due_date'] as $field) {
            if (($remote[$field] ?? null) !== null) {
                try {
                    $remote[$field] = CarbonImmutable::parse($remote[$field])->toDateString();
                } catch (\Throwable) {
                    throw new CariPlusException("Cari Plus faturasındaki {$field} alanı geçersiz.");
                }
            }
        }

        $remote['status'] = $status;

        return $remote;
    }
}
