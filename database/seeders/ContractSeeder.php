<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\ContractVersion;
use App\Models\Service;
use App\Services\Contracts\EncryptedContractDocumentStorage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

class ContractSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sourcePath = public_path('sozlesmeler/Kamera-Sistemleri-Abonelik-Sozlesmesi-Tepenet.pdf');

        if (! is_file($sourcePath)) {
            throw new RuntimeException('Kamera sistemleri sözleşme PDF dosyası bulunamadı.');
        }

        $bytes = file_get_contents($sourcePath);

        if ($bytes === false) {
            throw new RuntimeException('Kamera sistemleri sözleşme PDF dosyası okunamadı.');
        }

        $contract = Contract::query()->firstOrCreate(
            ['slug' => 'kamera-sistemleri-abonelik-sozlesmesi'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Kamera Sistemleri Abonelik Sözleşmesi',
                'is_active' => true,
            ],
        );
        $documentHash = hash('sha256', $bytes);
        $documentPath = "contracts/versions/{$contract->uuid}/1.0.pdf";
        $existingVersion = ContractVersion::query()
            ->where('contract_id', $contract->id)
            ->where('version', '1.0')
            ->first();

        if ($existingVersion !== null && ! hash_equals($existingVersion->source_document_hash, $documentHash)) {
            throw new RuntimeException('Yayımlanmış 1.0 sözleşme dosyası değiştirilmiş. Yeni bir sürüm oluşturun.');
        }

        app(EncryptedContractDocumentStorage::class)->put($documentPath, $bytes);

        $version = ContractVersion::query()->firstOrCreate(
            ['contract_id' => $contract->id, 'version' => '1.0'],
            [
                'uuid' => (string) Str::uuid(),
                'source_document_path' => $documentPath,
                'source_document_hash' => $documentHash,
                'effective_at' => now(),
                'published_at' => now(),
            ],
        );

        $service = Service::query()->where('name', 'Kamera Sistemleri')->first();

        if ($service !== null) {
            $version->services()->syncWithoutDetaching([
                $service->id => ['is_required' => true],
            ]);
        }
    }
}
