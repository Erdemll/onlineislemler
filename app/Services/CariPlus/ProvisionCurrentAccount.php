<?php

namespace App\Services\CariPlus;

use App\Contracts\CariPlusGateway;
use App\Enums\CustomerType;
use App\Exceptions\CariPlusException;
use App\Models\Customer;

class ProvisionCurrentAccount
{
    public function __construct(private CariPlusGateway $gateway) {}

    public function handle(Customer $customer): int
    {
        if ($customer->cari_plus_current_account_id !== null) {
            return (int) $customer->cari_plus_current_account_id;
        }

        if ($customer->email_verified_at === null) {
            throw new CariPlusException('Cari Plus hesabı oluşturulmadan önce e-posta doğrulanmalıdır.');
        }

        if (filled($customer->cari_plus_current_account_code)) {
            $existingId = $this->gateway->findCurrentAccountIdByCode($customer->cari_plus_current_account_code);

            if ($existingId !== null) {
                $customer->forceFill(['cari_plus_current_account_id' => $existingId])->save();

                return $existingId;
            }
        }

        $this->ensureProfileIsComplete($customer);
        $response = $this->gateway->createCurrentAccount(
            $this->payloadFor($customer),
            'portal-customer-'.$customer->uuid,
        );
        $id = $response['id'] ?? null;

        if (! is_int($id)) {
            throw new CariPlusException('Cari Plus müşteri hesabı için geçerli bir kimlik döndürmedi.');
        }

        $code = $response['code'] ?? null;
        $customer->forceFill([
            'cari_plus_current_account_id' => $id,
            'cari_plus_current_account_code' => is_string($code) && $code !== '' ? $code : null,
        ])->save();

        return $id;
    }

    /** @return array<string, mixed> */
    private function payloadFor(Customer $customer): array
    {
        $isIndividual = $customer->account_type === CustomerType::Individual;
        $payload = [
            'type' => 'customer',
            'title' => $customer->billingTitle(),
            'is_individual' => $isIndividual,
            'trade_name' => $customer->company_title,
            'tax_office' => $isIndividual ? null : $customer->tax_office,
            'tax_number' => $isIndividual ? $customer->national_id : $customer->tax_number,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'mobile' => $isIndividual ? null : $customer->mobile_phone,
            'address' => [
                'line' => $customer->address_line,
                'district' => $customer->district,
                'province_code' => $customer->province_code,
                'city' => $customer->province,
                'country' => 'TR',
            ],
            'is_active' => true,
        ];

        if (! $isIndividual) {
            $payload['contact_person'] = [
                'name' => trim($customer->first_name.' '.$customer->last_name),
                'role' => 'Yetkili',
                'email' => $customer->email,
                'phone' => $customer->mobile_phone ?: $customer->phone,
            ];
        }

        return array_filter($payload, fn (mixed $value): bool => $value !== null);
    }

    private function ensureProfileIsComplete(Customer $customer): void
    {
        $required = [$customer->email, $customer->phone, $customer->province_code, $customer->province, $customer->district, $customer->address_line];

        if ($customer->account_type === CustomerType::Individual) {
            $required[] = $customer->national_id;
        } else {
            array_push($required, $customer->tax_number, $customer->tax_office, $customer->company_title);
        }

        if (collect($required)->contains(fn (mixed $value): bool => blank($value))) {
            throw new CariPlusException('Cari Plus müşteri hesabı için kayıt bilgileri eksik.');
        }
    }
}
