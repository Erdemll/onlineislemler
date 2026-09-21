<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('a partially created events table is completed without being dropped', function () {
    Schema::drop('contract_acceptance_events');
    Schema::create('contract_acceptance_events', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('service_order_id')->constrained()->restrictOnDelete();
        $table->foreignId('contract_acceptance_id')->nullable()->constrained()->restrictOnDelete();
        $table->foreignId('customer_id')->constrained()->restrictOnDelete();
        $table->unsignedInteger('sequence');
        $table->string('event_type', 60);
        $table->json('metadata')->nullable();
        $table->char('previous_hash', 64)->nullable();
        $table->char('event_hash', 64);
        $table->timestamp('occurred_at', 6);
        $table->timestamps();
        $table->unique(['service_order_id', 'sequence']);
    });

    $migration = require database_path('migrations/2026_09_17_131708_create_contract_acceptance_events_table.php');
    $migration->up();

    expect(Schema::hasTable('contract_acceptance_events'))->toBeTrue();
    expect(Schema::hasIndex('contract_acceptance_events', 'ca_events_acceptance_occurred_idx'))->toBeTrue();
    expect(Schema::hasIndex('contract_acceptance_events', ['service_order_id', 'sequence'], 'unique'))->toBeTrue();
});
