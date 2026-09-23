<?php

namespace App\Console\Commands;

use App\Models\Customer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('customers:reindex-identities {--chunk=200 : Her adımda işlenecek müşteri sayısı}')]
#[Description('Müşteri kimlik blind-index değerlerini güncel bağımsız anahtarla yeniden üretir')]
class ReindexCustomerIdentities extends Command
{
    public function handle(): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $updated = 0;

        Customer::query()
            ->where(fn ($query) => $query
                ->whereNotNull('national_id')
                ->orWhereNotNull('tax_number'))
            ->chunkById($chunk, function ($customers) use (&$updated): void {
                foreach ($customers as $customer) {
                    $nationalId = $customer->national_id;
                    $taxNumber = $customer->tax_number;
                    $customer->forceFill([
                        'national_id' => $nationalId,
                        'tax_number' => $taxNumber,
                    ])->save();
                    $updated++;
                }
            });

        $this->components->info("{$updated} müşteri kimlik indeksi güncellendi.");

        return self::SUCCESS;
    }
}
