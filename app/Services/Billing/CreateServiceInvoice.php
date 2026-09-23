<?php

namespace App\Services\Billing;

use App\Contracts\CariPlusGateway;
use App\Enums\InvoiceStatus;
use App\Enums\ServiceOrderStatus;
use App\Exceptions\CariPlusException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Services\CariPlus\ResolveCurrentAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateServiceInvoice
{
    public function __construct(
        private CariPlusGateway $cariPlus,
        private ResolveCurrentAccount $resolveCurrentAccount,
    ) {}

    public function create(Customer $customer, Service $service): Invoice
    {
        $this->resolveCurrentAccount->for($customer);

        if ($service->cari_plus_product_id === null) {
            throw new CariPlusException('Hizmet Cari Plus ürünüyle eşleşmiyor. Önce ürün kataloğunu güncelleyin.');
        }

        $invoice = DB::transaction(function () use ($customer, $service): Invoice {
            $uuid = (string) Str::uuid();
            $grossTotal = $service->grossPrice();
            $taxRate = (float) $service->tax_rate;
            $subtotal = round($grossTotal / (1 + ($taxRate / 100)), 2);

            $invoice = $customer->invoices()->create([
                'uuid' => $uuid,
                'service_id' => $service->id,
                'status' => InvoiceStatus::Pending,
                'currency' => $service->currency,
                'subtotal' => $subtotal,
                'tax_amount' => $grossTotal - $subtotal,
                'total' => $grossTotal,
                'invoice_date' => today(),
                'due_date' => today()->addDays(14),
                'idempotency_key' => 'portal-'.$uuid,
            ]);

            $invoice->items()->create([
                'service_id' => $service->id,
                'cari_plus_product_id' => $service->cari_plus_product_id,
                'name' => $service->name,
                'description' => $service->description,
                'quantity' => 1,
                'unit_price' => $grossTotal,
                'tax_rate' => $service->tax_rate,
                'line_total' => $grossTotal,
            ]);

            return $invoice;
        });

        return $this->send($invoice->load('service', 'items'));
    }

    public function retry(Invoice $invoice): Invoice
    {
        if (! in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Failed], true)) {
            throw new CariPlusException('Bu fatura yeniden gönderilmeye uygun değil.');
        }

        try {
            $invoice = $this->send($invoice->loadMissing('customer', 'service', 'items', 'serviceOrder'));
            $invoice->serviceOrder?->update([
                'status' => ServiceOrderStatus::Invoiced,
                'last_error' => null,
            ]);

            return $invoice;
        } catch (CariPlusException $exception) {
            $invoice->serviceOrder?->update([
                'status' => ServiceOrderStatus::InvoiceFailed,
                'last_error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function createForOrder(ServiceOrder $order): Invoice
    {
        $order->loadMissing('customer', 'service', 'acceptance', 'invoice');

        if ($order->acceptance === null) {
            throw new CariPlusException('Fatura oluşturulmadan önce sözleşme kabul edilmelidir.');
        }

        if ($order->invoice !== null) {
            return $order->invoice;
        }

        $this->resolveCurrentAccount->for($order->customer);
        [$invoice, $wasCreated] = DB::transaction(function () use ($order): array {
            $lockedOrder = ServiceOrder::query()->lockForUpdate()->findOrFail($order->id);
            $existing = Invoice::query()->whereBelongsTo($lockedOrder, 'serviceOrder')->first();

            if ($existing !== null) {
                return [$existing, false];
            }

            $grossTotal = (float) $lockedOrder->unit_price;
            $taxRate = (float) $lockedOrder->tax_rate;
            $subtotal = round($grossTotal / (1 + ($taxRate / 100)), 2);
            $invoice = $lockedOrder->customer->invoices()->create([
                'uuid' => (string) Str::uuid(),
                'service_id' => $lockedOrder->service_id,
                'service_order_id' => $lockedOrder->id,
                'status' => InvoiceStatus::Pending,
                'currency' => $lockedOrder->currency,
                'subtotal' => $subtotal,
                'tax_amount' => $grossTotal - $subtotal,
                'total' => $grossTotal,
                'invoice_date' => today(),
                'due_date' => today()->addDays(14),
                'idempotency_key' => 'portal-order-'.$lockedOrder->uuid,
            ]);
            $invoice->items()->create([
                'service_id' => $lockedOrder->service_id,
                'cari_plus_product_id' => $lockedOrder->cari_plus_product_id_snapshot,
                'name' => $lockedOrder->service_name_snapshot,
                'description' => $lockedOrder->service_description_snapshot,
                'quantity' => 1,
                'unit_price' => $grossTotal,
                'tax_rate' => $taxRate,
                'line_total' => $grossTotal,
            ]);

            return [$invoice, true];
        });

        if (! $wasCreated) {
            return $invoice;
        }

        try {
            $invoice = $this->send($invoice->load('customer', 'service', 'items'));
            $order->update(['status' => ServiceOrderStatus::Invoiced, 'last_error' => null]);

            return $invoice;
        } catch (CariPlusException $exception) {
            $order->update([
                'status' => ServiceOrderStatus::InvoiceFailed,
                'last_error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function send(Invoice $invoice): Invoice
    {
        $customer = $invoice->customer;

        $this->resolveCurrentAccount->for($customer);

        try {
            if ($invoice->cari_plus_invoice_id === null) {
                $draft = $this->cariPlus->createSalesInvoice(
                    $this->payload($invoice),
                    $invoice->idempotency_key,
                );

                $this->updateFromRemote($invoice, $draft, InvoiceStatus::Draft);
            }

            $issued = $this->cariPlus->issueSalesInvoice(
                (int) $invoice->cari_plus_invoice_id,
                $invoice->idempotency_key.'-issue',
            );

            if (($issued['id'] ?? null) !== $invoice->cari_plus_invoice_id) {
                throw new CariPlusException('Cari Plus beklenmeyen bir fatura kimliği döndürdü.');
            }

            $this->updateFromRemote($invoice, $issued, InvoiceStatus::Unpaid);

            return $invoice->refresh();
        } catch (CariPlusException $exception) {
            report($exception);

            $invoice->update([
                'status' => $invoice->cari_plus_invoice_id === null
                    ? InvoiceStatus::Failed
                    : InvoiceStatus::Draft,
                'last_sync_error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    private function payload(Invoice $invoice): array
    {
        $item = $invoice->items->firstOrFail();
        $service = $invoice->service;
        $productId = $this->productIdFor($item, $service);

        $remoteItem = [
            'product_id' => $productId,
            'description' => $item->name,
            'quantity' => (float) $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'tax_rate' => (float) $item->tax_rate,
        ];

        return [
            'current_account_id' => $invoice->customer->cari_plus_current_account_id,
            'invoice_date' => $invoice->invoice_date->toDateString(),
            'currency' => $invoice->currency,
            'price_includes_tax' => true,
            'items' => [$remoteItem],
        ];
    }

    private function productIdFor(InvoiceItem $item, ?Service $service): int
    {
        $productId = $item->cari_plus_product_id ?? $service?->cari_plus_product_id;

        if ($productId === null) {
            throw new CariPlusException('Fatura kalemi Cari Plus ürünüyle eşleşmiyor. Önce ürün kataloğunu güncelleyin.');
        }

        if ($item->cari_plus_product_id === null) {
            $item->update(['cari_plus_product_id' => $productId]);
        }

        return $productId;
    }

    /** @param array<string, mixed> $remote */
    private function updateFromRemote(
        Invoice $invoice,
        array $remote,
        InvoiceStatus $status,
    ): void {
        $remoteId = $remote['id'] ?? null;

        if (! is_int($remoteId) || $remoteId <= 0) {
            throw new CariPlusException('Cari Plus geçerli bir fatura kimliği döndürmedi.');
        }

        $expectedRemoteStatus = $status === InvoiceStatus::Draft ? 'draft' : 'issued';
        $remoteTotal = $remote['total'] ?? null;

        if (($remote['status'] ?? null) !== $expectedRemoteStatus
            || ($remote['currency'] ?? null) !== $invoice->currency
            || (! is_int($remoteTotal) && ! is_float($remoteTotal))
            || ! is_finite((float) $remoteTotal)
            || (int) round($remoteTotal * 100) !== (int) round((float) $invoice->total * 100)) {
            throw new CariPlusException('Cari Plus faturası sözleşmedeki tutar, para birimi veya durumla eşleşmiyor.');
        }

        $invoice->update([
            'cari_plus_invoice_id' => $remoteId,
            'invoice_number' => $remote['invoice_number'] ?? null,
            'status' => $status,
            'collection_status' => $remote['collection_status'] ?? null,
            'currency' => $remote['currency'] ?? 'TRY',
            'subtotal' => $remote['subtotal'] ?? $invoice->subtotal,
            'tax_amount' => $remote['tax_amount'] ?? $invoice->tax_amount,
            'total' => $remote['total'] ?? $invoice->total,
            'invoice_date' => $remote['invoice_date'] ?? $invoice->invoice_date,
            'due_date' => $remote['due_date'] ?? $invoice->due_date,
            'last_sync_error' => null,
            'synced_at' => now(),
        ]);
    }
}
