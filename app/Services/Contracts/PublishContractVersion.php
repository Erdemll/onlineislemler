<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\ContractVersion;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Throwable;

class PublishContractVersion
{
    public function __construct(private EncryptedContractDocumentStorage $documents) {}

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

        try {
            $pdf = new Fpdi;
            $pageCount = $pdf->setSourceFile(StreamReader::createByString($bytes));

            if ($pageCount < 1 || $pageCount > 500) {
                throw new RuntimeException('PDF sayfa sayısı geçersiz.');
            }
        } catch (Throwable $exception) {
            throw new RuntimeException('PDF dosyası yapısal olarak doğrulanamadı.', previous: $exception);
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

                $this->documents->put($storedPath, $bytes);

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
                $this->documents->delete($storedPath);
            }

            throw $exception;
        }
    }
}
