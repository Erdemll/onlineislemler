<?php

namespace App\Console\Commands;

use App\Models\ServiceOrder;
use App\Services\Contracts\RecordContractAuditEvent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('contracts:audit-verify {--order= : Doğrulanacak hizmet talebinin UUID değeri}')]
#[Description('Sözleşme kabul olaylarının kriptografik hash zincirini doğrular')]
class VerifyContractAuditChain extends Command
{
    public function handle(RecordContractAuditEvent $audit): int
    {
        $orders = ServiceOrder::query()
            ->whereHas('events')
            ->when(
                $this->option('order'),
                fn ($query, string $uuid) => $query->where('uuid', $uuid),
            )
            ->orderBy('id')
            ->cursor();
        $checked = 0;
        $legacyEvents = 0;

        foreach ($orders as $order) {
            $checked++;
            $legacyEvents += $order->events()
                ->where('hash_version', 'sha256-v1')
                ->count();

            if (! $audit->verify($order)) {
                $this->components->error("Audit zinciri geçersiz: {$order->uuid}");

                return self::FAILURE;
            }
        }

        if ($checked === 0 && $this->option('order')) {
            $this->components->error('Doğrulanacak sözleşme audit zinciri bulunamadı.');

            return self::FAILURE;
        }

        if ($legacyEvents > 0) {
            $this->components->warn("{$legacyEvents} eski audit olayı yalnızca yapısal olarak doğrulandı; HMAC koruması migration sonrasındaki olaylarda geçerlidir.");
        }

        $this->components->info("{$checked} sözleşme audit zinciri doğrulandı.");

        return self::SUCCESS;
    }
}
