<?php

namespace App\Console\Commands;

use App\Models\Customer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('customers:backfill-uuids {--chunk=200 : Her adımda işlenecek müşteri sayısı}')]
#[Description('UUID değeri boş veya null olan müşterilere benzersiz UUID atar')]
class BackfillCustomerUuids extends Command
{
    public function handle(): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $updated = 0;

        Customer::query()
            ->where(fn ($query) => $query
                ->whereNull('uuid')
                ->orWhere('uuid', ''))
            ->chunkById($chunk, function ($customers) use (&$updated): void {
                foreach ($customers as $customer) {
                    $customer->forceFill(['uuid' => Str::uuid()->toString()])->save();
                    $updated++;
                }
            });

        $this->components->info("{$updated} müşteri UUID'si güncellendi.");

        return self::SUCCESS;
    }
}
