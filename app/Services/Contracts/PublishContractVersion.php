<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\ContractVersion;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PublishContractVersion
{
    /** @param list<int> $serviceIds */
    public function handle(
        ?Contract $contract,
        ?string $newContractName,
        string $version,
        CarbonInterface $effectiveAt,
        UploadedFile $document,
        array $serviceIds,
    ): ContractVersion {
        $bytes = file_get_contents($document->getRealPath());

        if ($bytes === false || ! str_starts_with($bytes, '%PDF-')) {
            throw new RuntimeException('PDF dosyası okunamadı veya geçerli bir PDF başlığı taşımıyor.');
        }

        $storedPath = null;

        try {
            return DB::transaction(function () use (
                $contract,
                $newContractName,
                $version,
                $effectiveAt,
                $bytes,
                $serviceIds,
                &$storedPath,
            ): ContractVersion {
                $resolvedContract = $contract ?? Contract::query()->create([
                    'name' => $newContractName,
                    'slug' => Str::slug((string) $newContractName),
                    'is_active' => true,
                ]);
                $versionUuid = (string) Str::uuid();
                $storedPath = "contracts/versions/{$resolvedContract->uuid}/{$version}-{$versionUuid}.pdf";

                if (! Storage::disk('local')->put($storedPath, $bytes)) {
                    throw new RuntimeException('Sözleşme PDF dosyası güvenli depolamaya yazılamadı.');
                }

                $contractVersion = ContractVersion::query()->create([
                    'uuid' => $versionUuid,
                    'contract_id' => $resolvedContract->id,
                    'version' => $version,
                    'source_document_path' => $storedPath,
                    'source_document_hash' => hash('sha256', $bytes),
                    'effective_at' => $effectiveAt,
                    'published_at' => now(),
                ]);

                $contractVersion->services()->attach(
                    collect($serviceIds)
                        ->mapWithKeys(fn (int $serviceId): array => [$serviceId => ['is_required' => true]])
                        ->all()
                );

                return $contractVersion->load(['contract', 'services']);
            });
        } catch (Throwable $exception) {
            if ($storedPath !== null) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $exception;
        }
    }
}
