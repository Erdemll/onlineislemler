<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\ContractVersion;
use App\Services\Contracts\EncryptedContractDocumentStorage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @extends Factory<ContractVersion>
 */
class ContractVersionFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (ContractVersion $version): void {
            if (! Storage::disk('local')->exists($version->source_document_path)) {
                app(EncryptedContractDocumentStorage::class)
                    ->put($version->source_document_path, $this->sourceBytes());
            }
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $uuid = (string) Str::uuid();

        return [
            'uuid' => $uuid,
            'contract_id' => Contract::factory(),
            'version' => '1.0',
            'source_document_path' => "contracts/versions/test/{$uuid}.pdf",
            'source_document_hash' => hash('sha256', $this->sourceBytes()),
            'effective_at' => now()->subDay(),
            'published_at' => now()->subDay(),
        ];
    }

    private function sourceBytes(): string
    {
        $bytes = file_get_contents(public_path('sozlesmeler/Kamera-Sistemleri-Abonelik-Sozlesmesi-Tepenet.pdf'));

        return $bytes === false ? '' : $bytes;
    }
}
