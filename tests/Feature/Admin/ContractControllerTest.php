<?php

use App\Contracts\CariPlusGateway;
use App\Models\Contract;
use App\Models\ContractVersion;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\FakeCariPlusGateway;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->cariPlus = new FakeCariPlusGateway;
    $this->app->instance(CariPlusGateway::class, $this->cariPlus);
    $this->admin = User::factory()->admin()->create();
});

test('an administrator can publish a new contract and associate products', function () {
    $services = Service::factory()->count(2)->sequence(
        ['name' => 'Kamera Sistemi', 'cari_plus_product_id' => 101],
        ['name' => 'Alarm Hizmeti', 'cari_plus_product_id' => 102],
    )->create();
    $pdfBytes = "%PDF-1.4\nadmin contract\n%%EOF";

    $response = $this->actingAs($this->admin)->post(route('admin.contracts.store'), [
        'contract_mode' => 'new',
        'new_contract_name' => 'Güvenlik Hizmeti Sözleşmesi',
        'version' => '1.0',
        'effective_at' => '2026-09-17 15:00:00',
        'document' => UploadedFile::fake()->createWithContent('sozlesme.pdf', $pdfBytes),
        'service_ids' => $services->pluck('id')->all(),
    ]);

    $response->assertRedirect(route('admin.contracts.index'));
    $response->assertSessionHas('status');

    $contract = Contract::query()->where('slug', 'guvenlik-hizmeti-sozlesmesi')->firstOrFail();
    $version = ContractVersion::query()->whereBelongsTo($contract)->firstOrFail();

    expect($version->version)->toBe('1.0')
        ->and($version->source_document_hash)->toBe(hash('sha256', $pdfBytes))
        ->and($version->published_at)->not->toBeNull()
        ->and($version->services()->pluck('services.id')->all())->toEqualCanonicalizing($services->pluck('id')->all());

    Storage::disk('local')->assertExists($version->source_document_path);
});

test('an administrator can add a version to an existing contract', function () {
    $contract = Contract::factory()->create();
    $service = Service::factory()->create(['cari_plus_product_id' => 301]);

    $this->actingAs($this->admin)->post(route('admin.contracts.store'), [
        'contract_mode' => 'existing',
        'contract_id' => $contract->id,
        'version' => '2.0',
        'effective_at' => now()->addDay()->format('Y-m-d H:i:s'),
        'document' => UploadedFile::fake()->createWithContent('sozlesme.pdf', "%PDF-1.4\nversion two\n%%EOF"),
        'service_ids' => [$service->id],
    ])->assertRedirect(route('admin.contracts.index'));

    $this->assertDatabaseHas('contract_versions', [
        'contract_id' => $contract->id,
        'version' => '2.0',
    ]);
});

test('duplicate versions and products without a cari plus identity are rejected', function () {
    $contract = Contract::factory()->create();
    ContractVersion::factory()->for($contract)->create(['version' => '1.0']);
    $service = Service::factory()->create(['cari_plus_product_id' => null]);

    $this->actingAs($this->admin)->post(route('admin.contracts.store'), [
        'contract_mode' => 'existing',
        'contract_id' => $contract->id,
        'version' => '1.0',
        'effective_at' => now()->format('Y-m-d H:i:s'),
        'document' => UploadedFile::fake()->createWithContent('sozlesme.pdf', "%PDF-1.4\ninvalid\n%%EOF"),
        'service_ids' => [$service->id],
    ])->assertSessionHasErrors(['version', 'service_ids.0']);

    expect(ContractVersion::query()->whereBelongsTo($contract)->count())->toBe(1);
});

test('a file with a forged pdf extension is not persisted', function () {
    $service = Service::factory()->create(['cari_plus_product_id' => 501]);

    $this->actingAs($this->admin)->post(route('admin.contracts.store'), [
        'contract_mode' => 'new',
        'new_contract_name' => 'Sahte Belge',
        'version' => '1.0',
        'effective_at' => now()->format('Y-m-d H:i:s'),
        'document' => UploadedFile::fake()->createWithContent('sozlesme.pdf', 'this is not a pdf'),
        'service_ids' => [$service->id],
    ])->assertSessionHas('error');

    $this->assertDatabaseMissing('contracts', ['slug' => 'sahte-belge']);
});

test('contract management is restricted to administrators', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.contracts.index'))
        ->assertForbidden();
});
