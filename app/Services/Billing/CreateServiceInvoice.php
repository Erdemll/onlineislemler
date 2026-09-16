<?php

namespace App\Services\Billing;

use App\Contracts\CariPlusGateway;
use App\Enums\InvoiceStatus;
use App\Exceptions\CariPlusException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Service;
use App\Services\CariPlus\ResolveCurrentAccount;
use App\Services\CariPlus\ResolveProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateServiceInvoice
{
    public function __construct(
        private CariPlusGateway $cariPlus,
        private ResolveCurrentAccount $resolveCurrentAccount,
        private ResolveProduct $resolveProduct,
    ) {}

    public function create(Customer $customer, Service $service): Invoice
    {
        $this->resolveCurrentAccount->for($customer);

        $invoice = DB::transaction(function () use ($customer, $service): Invoice {
            $uuid = (string) Str::uuid();
            $grossTotal = (float) $service->price;
            $taxRate = (float) $service->tax_rate;
            $subtotal = round($grossTotal / (1 + ($taxRate / 100)), 2);

            $invoice = $customer->invoices()->create([
                'uuid' => $uuid,
                'service_id' => $service->id,
                'status' => InvoiceStatus::Pending,
                'currency' => 'TRY',
                'subtotal' => $subtotal,
                'tax_amount' => $grossTotal - $subtotal,
                'total' => $grossTotal,
                'invoice_date' => today(),
                'due_date' => today()->addDays(14),
                'idempotency_key' => 'portal-'.$uuid,
            ]);

            $invoice->items()->create([
                'service_id' => $service->id,
                'name' => $service->name,
                'description' => $service->description,
                'quantity' => 1,
                'unit_price' => $service->price,
                'tax_rate' => $service->tax_rate,
                'line_total' => $service->price,
            ]);

            return $invoice;
        });

        return $this->send($invoice->load('service', 'items'));
    }

    public function retry(Invoice $invoice): Invoice
    {
        return $this->send($invoice->loadMissing('customer', 'service', 'items'));
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
        $taxRate = (float) $item->tax_rate;
        $netUnitPrice = round(
            (float) $item->unit_price / (1 + ($taxRate / 100)),
            2,
        );

        $remoteItem = [
            'description' => $item->name,
            'quantity' => (float) $item->quantity,
            'unit_price' => $netUnitPrice,
            'tax_rate' => $taxRate,
        ];

        if ($service?->cari_plus_service_id !== null) {
            $remoteItem['service_id'] = $service->cari_plus_service_id;
        } elseif ($service !== null) {
            $remoteItem['product_id'] = $this->resolveProduct->for($service);
        }

        return [
            'current_account_id' => $invoice->customer->cari_plus_current_account_id,
            'invoice_date' => $invoice->invoice_date->toDateString(),
            'items' => [$remoteItem],
        ];
    }

    /** @param array<string, mixed> $remote */
    private function updateFromRemote(
        Invoice $invoice,
        array $remote,
        InvoiceStatus $status,
    ): void {
        $invoice->update([
            'cari_plus_invoice_id' => $remote['id'],
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
