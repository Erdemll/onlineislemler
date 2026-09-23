<?php

namespace App\Services\Billing;

use App\Contracts\CariPlusGateway;
use App\Exceptions\CariPlusException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\CariPlus\ResolveCurrentAccount;

class GetCustomerSalesInvoice
{
    public function __construct(
        private CariPlusGateway $cariPlus,
        private ResolveCurrentAccount $resolveCurrentAccount,
    ) {}

    /**
     * @return array{number: string, title: ?string, status: string, subtotal: float, tax_amount: float, total: float, paid_amount: float, items: list<array{name: string, quantity: float, unit_price: float, tax_rate: float, amount: float}>}
     */
    public function for(Customer $customer, Invoice $invoice): array
    {
        if ($invoice->cari_plus_invoice_id === null) {
            throw new CariPlusException('Cari Plus faturası henüz oluşturulmadı.');
        }

        $remote = $this->cariPlus->getSalesInvoice($invoice->cari_plus_invoice_id);
        $accountId = $this->resolveCurrentAccount->for($customer);

        if (($remote['id'] ?? null) !== $invoice->cari_plus_invoice_id
            || ($remote['current_account']['id'] ?? null) !== $accountId
            || ($remote['currency'] ?? null) !== $invoice->currency
            || ! is_string($remote['invoice_number'] ?? null)
            || trim($remote['invoice_number']) === ''
            || ! in_array($remote['status'] ?? null, ['draft', 'issued', 'paid', 'cancelled', 'partial_refund', 'refunded'], true)
            || ! is_array($remote['items'] ?? null)
            || ! array_is_list($remote['items'])) {
            throw new CariPlusException('Cari Plus faturası müşteri kaydıyla eşleşmiyor.');
        }

        $items = [];

        foreach ($remote['items'] as $item) {
            if (! is_array($item)) {
                throw new CariPlusException('Cari Plus fatura kalemleri geçersiz.');
            }

            $name = $item['item_name'] ?? $item['description'] ?? null;

            if (! is_string($name) || trim($name) === '') {
                throw new CariPlusException('Cari Plus fatura kalemleri geçersiz.');
            }

            $items[] = [
                'name' => $name,
                'quantity' => $this->amount($item['quantity'] ?? null),
                'unit_price' => $this->amount($item['unit_price'] ?? null),
                'tax_rate' => $this->amount($item['tax_rate'] ?? null),
                'amount' => $this->amount($item['amount'] ?? null),
            ];
        }

        return [
            'number' => $remote['invoice_number'],
            'title' => is_string($remote['title'] ?? null) ? $remote['title'] : null,
            'status' => $remote['status'],
            'subtotal' => $this->amount($remote['subtotal'] ?? null),
            'tax_amount' => $this->amount($remote['tax_amount'] ?? null),
            'total' => $this->amount($remote['total'] ?? null),
            'paid_amount' => $this->amount($remote['paid_amount'] ?? null),
            'items' => $items,
        ];
    }

    private function amount(mixed $value): float
    {
        if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value) || $value < 0) {
            throw new CariPlusException('Cari Plus faturası geçersiz bir tutar döndürdü.');
        }

        return (float) $value;
    }
}
