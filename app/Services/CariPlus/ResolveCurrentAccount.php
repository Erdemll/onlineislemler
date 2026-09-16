<?php

namespace App\Services\CariPlus;

use App\Contracts\CariPlusGateway;
use App\Exceptions\CariPlusException;
use App\Models\Customer;

class ResolveCurrentAccount
{
    public function __construct(private CariPlusGateway $cariPlus) {}

    public function for(Customer $customer): int
    {
        if ($customer->cari_plus_current_account_id !== null) {
            return $customer->cari_plus_current_account_id;
        }

        $code = $customer->cari_plus_current_account_code;

        if (! is_string($code) || $code === '') {
            throw new CariPlusException('Cari Plus müşteri eşleşmesi henüz yapılmadı.');
        }

        $currentAccountId = $this->cariPlus->findCurrentAccountIdByCode($code);

        if ($currentAccountId === null) {
            throw new CariPlusException("Cari Plus’ta {$code} kodlu cari hesap bulunamadı.");
        }

        $customer->forceFill([
            'cari_plus_current_account_id' => $currentAccountId,
        ])->save();

        return $currentAccountId;
    }
}
