<?php

use App\Models\ContractAcceptance;
use App\Models\ContractAcceptanceEvent;
use App\Models\ServiceOrder;
use App\Services\Contracts\RecordContractAuditEvent;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('verifies a valid HMAC audit chain', function () {
    $order = ServiceOrder::factory()->create();
    app(RecordContractAuditEvent::class)->record($order, 'service_order_created', [
        'service_id' => $order->service_id,
    ]);

    $this->artisan('contracts:audit-verify', ['--order' => $order->uuid])
        ->expectsOutputToContain('1 sözleşme audit zinciri doğrulandı.')
        ->assertSuccessful();

    expect($order->events()->firstOrFail()->hash_version)->toBe('hmac-sha256-v2');
});

it('reports an invalid audit chain', function () {
    $order = ServiceOrder::factory()->create();
    ContractAcceptanceEvent::factory()->create([
        'service_order_id' => $order->id,
        'customer_id' => $order->customer_id,
        'event_hash' => str_repeat('0', 64),
        'hash_version' => 'hmac-sha256-v2',
    ]);

    $this->artisan('contracts:audit-verify', ['--order' => $order->uuid])
        ->expectsOutputToContain("Audit zinciri geçersiz: {$order->uuid}")
        ->assertFailed();
});

it('reports legacy events as a structurally verified baseline', function () {
    $order = ServiceOrder::factory()->create();
    ContractAcceptanceEvent::factory()->create([
        'service_order_id' => $order->id,
        'customer_id' => $order->customer_id,
        'event_hash' => str_repeat('a', 64),
        'hash_version' => 'sha256-v1',
    ]);

    $this->artisan('contracts:audit-verify', ['--order' => $order->uuid])
        ->expectsOutputToContain('1 eski audit olayı yalnızca yapısal olarak doğrulandı')
        ->assertSuccessful();
});

it('blocks direct updates and deletes of acceptance records at the database layer', function () {
    $order = ServiceOrder::factory()->create();
    $event = app(RecordContractAuditEvent::class)->record($order, 'service_order_created');
    $acceptance = ContractAcceptance::factory()->create();

    $eventUpdate = fn () => DB::table('contract_acceptance_events')
        ->where('id', $event->id)
        ->update(['event_type' => 'tampered']);
    $eventDelete = fn () => DB::table('contract_acceptance_events')
        ->where('id', $event->id)
        ->delete();
    $acceptanceUpdate = fn () => DB::table('contract_acceptances')
        ->where('id', $acceptance->id)
        ->update(['signer_name_snapshot' => 'Tampered']);
    $acceptanceDelete = fn () => DB::table('contract_acceptances')
        ->where('id', $acceptance->id)
        ->delete();

    expect($eventUpdate)->toThrow(QueryException::class)
        ->and($eventDelete)->toThrow(QueryException::class)
        ->and($acceptanceUpdate)->toThrow(QueryException::class)
        ->and($acceptanceDelete)->toThrow(QueryException::class);
});
